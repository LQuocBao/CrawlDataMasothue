# AI Agent Context - MaSoThue Scraper System

> File này chứa toàn bộ thông tin để AI Agent (hoặc developer mới) nắm được dự án và có thể deploy/debug/phát triển tiếp.

---

## 1. Tổng quan dự án

### Mục đích
Hệ thống tự động cào dữ liệu doanh nghiệp mới đăng ký từ **2 nguồn** (`masothue.com` + `tramasothue.com.vn`), xuất PDF, gửi thông báo Telegram, và ghi vào Google Sheets. Latency yêu cầu: < 4 phút từ khi DN xuất hiện trên web đến khi nhận Telegram.

### Tech Stack
| Layer | Công nghệ |
|-------|-----------|
| Backend | Laravel 11.x / PHP 8.3 |
| Frontend | Next.js 14 / React / TypeScript / Tailwind CSS |
| Database | MySQL 8.0 |
| Cache/Queue | Redis 7 |
| PDF | barryvdh/laravel-dompdf |
| Proxy | TMProxy.com (rotating IP, API-based) |
| Notification | Telegram Bot API (sendDocument) |
| Data Export | Google Sheets via Apps Script webhook |
| Parsing | Symfony DomCrawler (tramasothue), Regex (masothue) |
| Infrastructure | Docker Compose (7 containers) |

### Repository
- URL: `https://github.com/LQuocBao/CrawlDataMasothue.git`
- Branch chính: `refactor/bus-chain` (kiến trúc mới)
- Branch production cũ: `main` (architecture cũ, đang phục vụ khách hàng)

---

## 2. Kiến trúc hệ thống (Bus::chain + Queue)

```
┌─────────────── SCHEDULER (mỗi 1 phút) ─────────────────┐
│  php artisan scraper:run → dispatch Bus::chain           │
└──────────────────────────┬───────────────────────────────┘
                           │ Queue: "scraping"
                           ▼
┌──────────────────────────────────────────────────────────┐
│  Bus::chain (tuần tự, cùng 1 IP proxy):                  │
│                                                          │
│  1. RotateProxyJob                                       │
│     └─ Gọi TMProxy API → lưu IP vào Redis cache         │
│                                                          │
│  2. ScrapeMasothueJob                                    │
│     └─ GET masothue.com → parse → lưu DB (source=masothue)│
│     └─ Ghi Google Sheet (TẤT CẢ DN)                     │
│     └─ Dispatch notification (chỉ DN có SĐT)            │
│                                                          │
│  3. ScrapeTramasothueJob                                 │
│     └─ GET tramasothue.com.vn → DomCrawler parse         │
│     └─ Dedup cross-source: nếu MST đã có → merge, skip  │
│     └─ DN mới → lưu DB (source=tramasothue) + Sheet     │
│     └─ Dispatch notification (chỉ DN có SĐT)            │
└──────────────────────────────────────────────────────────┘
                           │
                           │ dispatch() → Queue: "notifications"
                           ▼
┌──────────────────────────────────────────────────────────┐
│  ProcessCompanyNotification (song song, nhiều job cùng lúc)│
│                                                          │
│  1. PdfService → render PDF (DomPDF, A4, DejaVu Sans)    │
│  2. TelegramService → sendDocument (PDF + caption)       │
│  3. Cleanup PDF file                                     │
└──────────────────────────────────────────────────────────┘
```

### Dedup Cross-Source (Hướng C)
- Mỗi DN dùng MST (mã số thuế) làm key duy nhất
- DN mới từ nguồn A → lưu `source = 'masothue'` hoặc `'tramasothue'`
- DN trùng MST từ nguồn B → merge `source = 'both'`, bổ sung field trống, KHÔNG gửi notification lại
- Redis cache: prefix `scraped_mst:` (masothue) + `scraped_tramasothue:` (tramasothue), TTL 30 ngày

---

## 3. Docker Containers (7 services)

| Container | Vai trò | Port |
|-----------|---------|------|
| `mst-app` | Laravel API (php artisan serve) | 8000 |
| `mst-frontend` | Next.js Dashboard (npm run dev) | 3000 |
| `mst-scheduler` | Chạy schedule:run mỗi 60s | - |
| `mst-worker-scraping` | Queue worker cho "scraping" (chain tuần tự) | - |
| `mst-worker-notifications` | Queue worker cho "notifications" (PDF+Telegram) | - |
| `mst-mysql` | MySQL 8.0 | 3306 |
| `mst-redis` | Redis 7 Alpine | 6379 |

---

## 4. Cấu trúc thư mục quan trọng

```
project-root/
├── docker-compose.yml              ← 7 containers dev setup
├── deploy-staging.sh               ← Script auto-deploy VPS staging
├── AI-CONTEXT.md                   ← FILE NÀY
│
├── backend/
│   ├── app/
│   │   ├── Console/Commands/
│   │   │   └── RunScraperChain.php       ← Entry point: dispatch Bus::chain
│   │   ├── Jobs/
│   │   │   ├── RotateProxyJob.php        ← Job 1: TMProxy API → lấy IP
│   │   │   ├── ScrapeMasothueJob.php     ← Job 2: cào masothue.com
│   │   │   ├── ScrapeTramasothueJob.php  ← Job 3: cào tramasothue.com.vn
│   │   │   └── ProcessCompanyNotification.php ← PDF + Telegram (queue khác)
│   │   ├── Services/
│   │   │   ├── ScraperService.php        ← Parse masothue.com (regex)
│   │   │   ├── TraMaSoThueScraperService.php ← Parse tramasothue (DomCrawler)
│   │   │   ├── PdfService.php            ← Render PDF (DomPDF)
│   │   │   ├── TelegramService.php       ← sendDocument API
│   │   │   └── GoogleSheetService.php    ← POST webhook Apps Script
│   │   ├── Models/
│   │   │   ├── Company.php              ← source tracking + mergeSource()
│   │   │   ├── AppSetting.php           ← Key-value settings (Sheet URL, etc.)
│   │   │   ├── TelegramConfig.php       ← Bot token + chat_id
│   │   │   └── Filter.php              ← Bộ lọc DN theo tỉnh/ngành
│   │   └── Http/Controllers/Api/
│   │       ├── CompanyController.php
│   │       ├── FilterController.php
│   │       ├── TelegramConfigController.php
│   │       ├── SettingsController.php    ← GET/PUT settings + test Sheet
│   │       └── GoogleSheetController.php
│   ├── config/scraper.php               ← Config proxy, TMProxy key
│   ├── routes/
│   │   ├── api.php                      ← REST API (prefix /api/v1)
│   │   └── console.php                  ← Scheduler: everyMinute
│   ├── database/migrations/             ← 6 migration files
│   ├── resources/views/pdf/
│   │   └── company-report.blade.php     ← PDF template
│   ├── .env.example
│   ├── Dockerfile
│   └── composer.json
│
├── frontend/
│   ├── src/
│   │   ├── app/(dashboard)/
│   │   │   ├── page.tsx                 ← Dashboard
│   │   │   ├── filters/page.tsx         ← Bộ lọc
│   │   │   ├── telegram/page.tsx        ← Telegram config
│   │   │   ├── sheets/page.tsx          ← Google Sheets view
│   │   │   └── settings/page.tsx        ← Cài đặt (Sheet URL, Telegram)
│   │   ├── components/
│   │   │   └── Sidebar.tsx              ← Navigation
│   │   ├── lib/api.ts                   ← API client
│   │   └── types/index.ts              ← TypeScript types
│   ├── Dockerfile
│   └── package.json
│
└── backend/docs/
    └── google-apps-script-updated.js    ← Code cho Google Apps Script
```

---

## 5. Database Schema

### `companies` (bảng chính)
| Cột | Type | Mô tả |
|-----|------|--------|
| id | bigint PK | Auto increment |
| mst | varchar(20) UNIQUE | Mã số thuế |
| **source** | varchar(20) | `masothue` / `tramasothue` / `both` |
| name | varchar | Tên DN |
| address | text | Địa chỉ |
| province | varchar(100) | Tỉnh/TP |
| representative | varchar | Đại diện pháp luật |
| phone | varchar(20) | SĐT (10 số, bắt đầu bằng 0) |
| operation_date | date | Ngày hoạt động |
| status | varchar(50) | Trạng thái |
| industries | JSON | [{code, description, is_primary}] |
| notification_sent | boolean | Đã gửi Telegram chưa |
| scraped_at | timestamp | Thời điểm quét |

### `app_settings` (cấu hình hệ thống)
| Key | Mô tả |
|-----|--------|
| `google_sheet_webhook_url` | URL Apps Script webhook |
| `google_sheet_enabled` | Bật/tắt ghi Sheet (1/0) |

### `telegram_configs` (bot Telegram)
| Cột | Mô tả |
|-----|--------|
| name | Tên config |
| bot_token | Token từ @BotFather |
| chat_id | Group/Chat ID |
| is_active | Đang sử dụng |

---

## 6. API Endpoints (prefix: `/api/v1`)

| Method | Path | Mô tả |
|--------|------|--------|
| GET | `/companies/stats` | Dashboard thống kê |
| GET | `/companies` | Danh sách DN (paginated) |
| CRUD | `/filters` | Quản lý bộ lọc |
| CRUD | `/telegram-configs` | Quản lý Telegram |
| POST | `/telegram-configs/{id}/test` | Test kết nối Telegram |
| GET | `/settings` | Lấy settings |
| PUT | `/settings` | Update settings |
| POST | `/settings/test-sheet` | Test kết nối Google Sheet |
| GET | `/sheets` | Danh sách sheets theo ngày |

---

## 7. Biến môi trường quan trọng (.env)

```env
# === BẮT BUỘC ===
APP_ENV=production
APP_KEY=                          # php artisan key:generate
DB_HOST=mysql                     # Tên container Docker
DB_DATABASE=masothue_scraper
DB_USERNAME=scraper
DB_PASSWORD=scraper_pass
REDIS_HOST=redis
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

# === PROXY (TMProxy.com) ===
SCRAPER_TMPROXY_KEY=              # API key từ tmproxy.com
SCRAPER_BASE_URL=https://masothue.com
SCRAPER_MAX_PER_RUN=50

# === TELEGRAM (backup, chính lưu trong DB) ===
TELEGRAM_DEFAULT_BOT_TOKEN=
TELEGRAM_DEFAULT_CHAT_ID=

# === GOOGLE SHEET (chính lưu trong DB app_settings) ===
# Webhook URL cấu hình qua Dashboard /settings
```

---

## 8. Deploy lên VPS mới

### Yêu cầu VPS
- RAM: 2GB+
- OS: Ubuntu 22.04+
- Docker + Docker Compose

### Lệnh deploy (1 command)
```bash
apt update -qq && apt install -y curl git && \
curl -fsSL https://raw.githubusercontent.com/LQuocBao/CrawlDataMasothue/refactor/bus-chain/deploy-staging.sh | bash
```

### Hoặc thủ công
```bash
# 1. Cài Docker
curl -fsSL https://get.docker.com | sh

# 2. Clone repo
cd /var/www
git clone -b refactor/bus-chain https://github.com/LQuocBao/CrawlDataMasothue.git masothue-staging
cd masothue-staging

# 3. Cấu hình .env (sửa backend/.env)
cp backend/.env.example backend/.env
# Edit: DB_HOST=mysql, REDIS_HOST=redis, QUEUE_CONNECTION=redis

# 4. Build & start
docker compose up -d --build

# 5. Wait for MySQL + migrate
sleep 15
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force

# 6. Verify
docker compose ps
docker compose logs worker-scraping --tail=10
```

### Sau deploy, cấu hình qua Dashboard (http://IP:3000):
1. **Settings** → Paste Google Sheet webhook URL
2. **Telegram** → Thêm Bot Token + Chat ID

---

## 9. Monitoring & Debug

```bash
# Xem trạng thái containers
docker compose ps

# Log scraper (chain execution)
docker compose logs worker-scraping --tail=30 -f

# Log notifications (PDF + Telegram)
docker compose logs worker-notifications --tail=20

# Log scheduler (cron)
docker compose logs scheduler --tail=10

# Kiểm tra DN mới nhất trong DB
docker compose exec app php artisan tinker --execute="echo App\Models\Company::latest()->first()->toJson(JSON_PRETTY_PRINT);"

# Restart workers (sau khi đổi code)
docker compose restart worker-scraping worker-notifications scheduler

# Check failed jobs
docker compose exec app php artisan queue:failed

# Retry failed
docker compose exec app php artisan queue:retry all

# Clear dedup cache (để cào lại tất cả)
docker compose exec app php artisan tinker --execute="Illuminate\Support\Facades\Cache::flush(); echo 'Done';"
```

---

## 10. Bàn giao cho khách hàng

Khi chuyển từ staging sang production cho khách mới, chỉ cần đổi 2 thứ:

1. **Google Sheet URL** → Trang Dashboard `/settings` → paste URL webhook mới
2. **Telegram Chat ID** → Trang Dashboard `/telegram` → thêm config mới với Chat ID group khách

Không cần sửa code. Không cần redeploy.

---

## 11. VPS hiện tại

| Môi trường | IP | Path | Branch | Trạng thái |
|------------|----|----|--------|-----------|
| Production (khách) | 222.255.214.169 | /var/www/masothue-scraper | main | Đang chạy |
| Staging (test) | 14.225.205.98 | /var/www/masothue-staging | refactor/bus-chain | Chờ deploy |

---

## 12. Files quan trọng cần đọc (theo mức độ ưu tiên)

### Nắm kiến trúc (5 phút)
1. `docker-compose.yml` — toàn bộ infrastructure
2. `backend/app/Console/Commands/RunScraperChain.php` — entry point
3. `backend/routes/console.php` — scheduler config

### Hiểu logic nghiệp vụ (15 phút)
4. `backend/app/Jobs/ScrapeMasothueJob.php` — cào + source tracking
5. `backend/app/Jobs/ScrapeTramasothueJob.php` — cào + cross-source dedup
6. `backend/app/Services/ScraperService.php` — parse HTML masothue
7. `backend/app/Services/TraMaSoThueScraperService.php` — parse HTML tramasothue
8. `backend/app/Models/Company.php` — model + mergeSource()

### Notification pipeline (5 phút)
9. `backend/app/Jobs/ProcessCompanyNotification.php` — PDF + Telegram flow
10. `backend/app/Services/GoogleSheetService.php` — Sheet webhook

### Cấu hình & deploy (5 phút)
11. `backend/.env.example` — tất cả env vars
12. `deploy-staging.sh` — auto-deploy script
13. `backend/config/scraper.php` — proxy/scraper config
