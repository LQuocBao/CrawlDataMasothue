# System Architecture - MaSoThue Scraper

## Tổng quan

Hệ thống cào dữ liệu doanh nghiệp mới từ 2 nguồn, xử lý bất đồng bộ qua Queue, và gửi thông báo đa kênh.

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           SYSTEM ARCHITECTURE                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                  │
│  ┌───────────────┐       REST API        ┌─────────────────────────────────┐    │
│  │  Next.js App  │◄────────────────────►│         Laravel Backend          │    │
│  │  (Dashboard)  │    http://IP:8000     │                                 │    │
│  │               │                       │  ┌────────────────────────────┐ │    │
│  │ - Dashboard   │                       │  │   Scheduler (mỗi 1 phút)  │ │    │
│  │ - Bộ lọc     │                       │  │   artisan scraper:run      │ │    │
│  │ - Telegram    │                       │  └────────────┬───────────────┘ │    │
│  │ - Sheets      │                       │               │ dispatch         │    │
│  │ - Cài đặt    │                       │               ▼                  │    │
│  └───────────────┘                       │  ┌────────────────────────────┐ │    │
│                                          │  │   Bus::chain (Queue:       │ │    │
│                                          │  │   "scraping" - tuần tự)    │ │    │
│                                          │  │                            │ │    │
│                                          │  │   1. RotateProxyJob        │ │    │
│                                          │  │      └─ TMProxy API → IP   │ │    │
│                                          │  │                            │ │    │
│                                          │  │   2. ScrapeMasothueJob     │ │    │
│                                          │  │      └─ masothue.com       │ │    │
│                                          │  │      └─ source='masothue'  │ │    │
│                                          │  │                            │ │    │
│                                          │  │   3. ScrapeTramasothueJob  │ │    │
│                                          │  │      └─ tramasothue.com.vn │ │    │
│                                          │  │      └─ source='tramasothue│ │    │
│                                          │  └────────────┬───────────────┘ │    │
│                                          │               │                  │    │
│                                          │               │ dispatch (DN mới)│    │
│                                          │               ▼                  │    │
│                                          │  ┌────────────────────────────┐ │    │
│                                          │  │  ProcessCompanyNotification│ │    │
│                                          │  │  (Queue: "notifications"   │ │    │
│                                          │  │   - song song)             │ │    │
│                                          │  │                            │ │    │
│                                          │  │  1. PdfService → render    │ │    │
│                                          │  │  2. TelegramService → send │ │    │
│                                          │  │  3. Cleanup PDF file       │ │    │
│                                          │  └────────────────────────────┘ │    │
│                                          └─────────────────────────────────┘    │
│                                                                                  │
│  ┌──────────────────────────────────────────────────────────────────────────┐   │
│  │                         EXTERNAL SERVICES                                 │   │
│  │                                                                           │   │
│  │  ┌─────────────┐  ┌──────────────────┐  ┌────────────────────────────┐  │   │
│  │  │masothue.com │  │tramasothue.com.vn│  │   Telegram Bot API         │  │   │
│  │  │(Data Source)│  │(Data Source)      │  │   (sendDocument)           │  │   │
│  │  └─────────────┘  └──────────────────┘  └────────────────────────────┘  │   │
│  │                                                                           │   │
│  │  ┌─────────────┐  ┌──────────────────┐                                  │   │
│  │  │TMProxy.com  │  │Google Apps Script │                                  │   │
│  │  │(Rotate IP)  │  │(Sheet Webhook)   │                                  │   │
│  │  └─────────────┘  └──────────────────┘                                  │   │
│  └──────────────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow (< 4 phút end-to-end)

1. **Scheduler** trigger `scraper:run` mỗi 1 phút (withoutOverlapping)
2. **Bus::chain** dispatch 3 Job tuần tự trên queue "scraping"
3. **RotateProxyJob** → gọi TMProxy API lấy IP mới → lưu Redis cache
4. **ScrapeMasothueJob** → GET masothue.com qua proxy → parse regex → lưu DB
5. **ScrapeTramasothueJob** → GET tramasothue.com.vn → DomCrawler parse → dedup cross-source
6. Mỗi DN mới → **GoogleSheetService** ghi Sheet (TẤT CẢ DN)
7. DN có SĐT → dispatch **ProcessCompanyNotification** sang queue "notifications"
8. **PdfService** render PDF → **TelegramService** gửi sendDocument

---

## Dedup Cross-Source (Hướng C)

| Tình huống | Hành động |
|------------|-----------|
| MST chưa tồn tại | Lưu mới + ghi Sheet + gửi Telegram (nếu có SĐT) |
| MST đã có từ nguồn khác | Merge `source='both'` + bổ sung field trống + KHÔNG gửi lại |
| MST đã có từ cùng nguồn | Skip hoàn toàn (Redis dedup) |

---

## Docker Infrastructure (7 containers)

| Container | Image | Vai trò | Port |
|-----------|-------|---------|------|
| mst-app | PHP 8.3 Alpine | Laravel API server | 8000 |
| mst-frontend | Node 20 Alpine | Next.js dashboard | 3000 |
| mst-scheduler | PHP 8.3 Alpine | schedule:run loop | - |
| mst-worker-scraping | PHP 8.3 Alpine | Queue worker (scraping chain) | - |
| mst-worker-notifications | PHP 8.3 Alpine | Queue worker (PDF + Telegram) | - |
| mst-mysql | MySQL 8.0 | Database | 3306 |
| mst-redis | Redis 7 Alpine | Cache + Queue broker | 6379 |

---

## Key Design Decisions

| Quyết định | Lý do |
|------------|-------|
| Bus::chain (không while-true loop) | Tech Lead mandate: tách biệt scheduling vs execution |
| 2 queue riêng (scraping + notifications) | PDF/Telegram không block luồng cào |
| Redis dedup (O(1) lookup) | Nhanh hơn query DB cho mỗi MST |
| DomCrawler cho tramasothue | HTML phức tạp (Alpine.js x-data), regex không đủ |
| Regex cho masothue | HTML đơn giản (table-taxinfo), nhanh hơn DomCrawler |
| AppSetting (DB) thay vì .env | Đổi Google Sheet URL từ Dashboard không cần redeploy |
| Source tracking (source column) | Khách phân biệt DN đến từ trang nào |
| sleep(rand(1,3)) trong tramasothue | Chống WAF, giả lập nhịp người dùng |

---

## Cấu trúc thư mục

```
project-root/
├── backend/                           # Laravel 11.x
│   ├── app/
│   │   ├── Console/Commands/
│   │   │   └── RunScraperChain.php   # Entry point: Bus::chain dispatch
│   │   ├── Jobs/
│   │   │   ├── RotateProxyJob.php    # TMProxy API
│   │   │   ├── ScrapeMasothueJob.php # Cào masothue.com
│   │   │   ├── ScrapeTramasothueJob.php # Cào tramasothue.com.vn
│   │   │   └── ProcessCompanyNotification.php # PDF + Telegram
│   │   ├── Services/
│   │   │   ├── ScraperService.php         # Parse masothue (regex)
│   │   │   ├── TraMaSoThueScraperService.php # Parse tramasothue (DomCrawler)
│   │   │   ├── PdfService.php            # DomPDF render
│   │   │   ├── TelegramService.php       # Bot API sendDocument
│   │   │   └── GoogleSheetService.php    # Apps Script webhook
│   │   ├── Models/
│   │   │   ├── Company.php       # source tracking, mergeSource()
│   │   │   ├── AppSetting.php    # Key-value settings
│   │   │   ├── TelegramConfig.php
│   │   │   └── Filter.php
│   │   └── Http/Controllers/Api/
│   │       ├── CompanyController.php
│   │       ├── FilterController.php
│   │       ├── TelegramConfigController.php
│   │       ├── SettingsController.php
│   │       └── GoogleSheetController.php
│   ├── config/scraper.php
│   ├── database/migrations/
│   ├── resources/views/pdf/company-report.blade.php
│   ├── routes/api.php
│   └── routes/console.php            # Scheduler config
├── frontend/                          # Next.js 14
│   ├── src/app/(dashboard)/
│   │   ├── page.tsx                   # Dashboard
│   │   ├── filters/page.tsx
│   │   ├── telegram/page.tsx
│   │   ├── sheets/page.tsx
│   │   └── settings/page.tsx         # Cài đặt Sheet URL
│   ├── src/components/
│   ├── src/lib/api.ts
│   └── src/types/index.ts
├── docker-compose.yml                 # 7 containers
├── deploy-staging.sh                  # Auto-deploy VPS
└── AI-CONTEXT.md                      # Full context for AI agents
```
