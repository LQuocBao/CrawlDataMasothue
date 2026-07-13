# Báo cáo Kỹ thuật - Hệ thống Cào Dữ liệu MaSoThue

## 1. Tổng quan dự án

### Mục đích
Hệ thống tự động cào dữ liệu doanh nghiệp mới đăng ký từ `masothue.com`, xuất PDF báo cáo, và gửi thông báo real-time qua Telegram khi phát hiện doanh nghiệp mới có số điện thoại. Đồng thời lưu trữ toàn bộ dữ liệu vào Google Sheets theo ngày.

### Yêu cầu nghiệp vụ
- Phát hiện doanh nghiệp mới trong vòng **< 4 phút** từ khi xuất hiện trên masothue.com
- Chỉ gửi Telegram cho doanh nghiệp **có số điện thoại hợp lệ** (≥ 10 số)
- Ghi **tất cả** doanh nghiệp (có và không có SĐT) vào Google Sheets
- PDF output theo mẫu cố định (header xanh `#163D8E`, bảng ngành nghề, trạng thái xanh lá `#079569`)
- Lưu trữ Google Sheets 7 ngày, tự xóa tab cũ

---

## 2. Kiến trúc hệ thống

### Tech Stack

| Layer | Công nghệ |
|---|---|
| Backend | Laravel 12.x (PHP 8.3) |
| Frontend | Next.js 14 (React, TypeScript, Tailwind CSS) |
| Database | MySQL 8.0 |
| Cache/Queue | Redis 7 |
| PDF | barryvdh/laravel-dompdf 3.x |
| Proxy | TMProxy.com (rotating IP, API-based) |
| Notification | Telegram Bot API (sendDocument) |
| Data Export | Google Sheets via Apps Script webhook |
| Infrastructure | Docker Compose, VPS Ubuntu |

### Luồng xử lý chính (Production Flow)

```
┌────────────────────────────────────────────────────────────────────┐
│                    SCHEDULER CONTAINER                              │
│         (while true; do php artisan scrape:companies; sleep 10)    │
└────────────────────────┬───────────────────────────────────────────┘
                         │
                         ▼
┌────────────────────────────────────────────────────────────────────┐
│                    ScraperService                                   │
│  1. Gọi TMProxy API → lấy IP proxy hiện tại                      │
│  2. GET masothue.com (qua proxy) → parse listing page             │
│  3. Lọc DN đã có trong Redis cache (dedup) → chỉ giữ DN mới      │
│  4. Với mỗi DN mới:                                               │
│     a. GET detail page → parse thông tin (table-taxinfo + ngành)  │
│     b. Lưu vào MySQL (companies table)                            │
│     c. Đánh dấu MST vào Redis cache (30 ngày TTL)                │
└────────────────────────┬───────────────────────────────────────────┘
                         │
                         ▼
┌────────────────────────────────────────────────────────────────────┐
│                 ScrapeCompanies Command                             │
│  Với mỗi DN mới trả về từ ScraperService:                         │
│                                                                    │
│  ┌─── GoogleSheetService::appendCompany() ◄── TẤT CẢ DN          │
│  │    (gọi Apps Script webhook → ghi vào Google Sheet)            │
│  │                                                                 │
│  ├─── Kiểm tra: có SĐT hợp lệ (≥ 10 số)?                        │
│  │        │                                                        │
│  │        ├── KHÔNG → skip (không gửi Telegram)                   │
│  │        │                                                        │
│  │        └── CÓ → PdfService::generateCompanyPdf()               │
│  │                  TelegramService::sendDocument()                │
│  │                  Cleanup PDF file                               │
│  │                  Update notification_sent = true                │
│  └─────────────────────────────────────────────────────────────────│
└────────────────────────────────────────────────────────────────────┘
```

### Lưu ý quan trọng về kiến trúc
- **KHÔNG sử dụng Queue/Job cho luồng chính.** Lý do: giảm latency tối đa. Telegram được gửi đồng bộ (synchronous) ngay trong vòng lặp scrape. Mỗi DN mất ~2-3 giây (PDF + Telegram).
- **KHÔNG sử dụng `Bus::chain`.** Flow là sequential loop đơn giản.
- File `ProcessCompanyNotification.php` (Job) tồn tại trong codebase nhưng **không được sử dụng** trên production. Nó là phần code dự phòng nếu sau này cần chuyển sang async processing.

---

## 3. Cấu trúc codebase

### Backend (`backend/`)

```
app/
├── Console/Commands/
│   ├── ScrapeCompanies.php      ← Entry point chính (scheduler gọi)
│   ├── TestScrape.php           ← Command test thủ công
│   ├── TestSendPdf.php          ← Command test PDF + Telegram
│   └── DebugScrape.php          ← Command debug HTML structure
│
├── Services/
│   ├── ScraperService.php       ← Logic cào data (HTTP client, proxy, parse HTML)
│   ├── PdfService.php           ← Render PDF từ Blade template (DomPDF)
│   ├── TelegramService.php      ← Gửi document qua Telegram Bot API
│   ├── GoogleSheetService.php   ← Ghi data vào Google Sheet (Apps Script webhook)
│   └── FilterService.php        ← Logic lọc DN theo bộ lọc người dùng
│
├── Models/
│   ├── Company.php              ← Model DN (MST, tên, SĐT, địa chỉ, ngành nghề...)
│   ├── TelegramConfig.php       ← Model cấu hình bot Telegram
│   └── Filter.php               ← Model bộ lọc (tỉnh, ngành, từ khóa)
│
├── Http/Controllers/Api/
│   ├── CompanyController.php    ← API: danh sách DN, thống kê dashboard
│   ├── FilterController.php     ← API: CRUD bộ lọc
│   ├── TelegramConfigController.php ← API: CRUD cấu hình Telegram
│   └── GoogleSheetController.php    ← API: danh sách sheets theo ngày
│
├── Jobs/
│   └── ProcessCompanyNotification.php ← (KHÔNG dùng trên production)
│
└── Providers/
    └── AppServiceProvider.php

config/
└── scraper.php                  ← Config: proxy, TMProxy key, Google Drive, intervals

database/migrations/
├── create_telegram_configs_table
├── create_companies_table
├── create_filters_table
├── add_date_phone_filters_to_filters_table
└── create_jobs_table

resources/views/pdf/
└── company-report.blade.php     ← Template PDF (Blade + inline CSS)

routes/
├── api.php                      ← REST API routes (prefix /api/v1)
└── console.php                  ← Scheduler registration
```

### Frontend (`frontend/`)

```
src/
├── app/(dashboard)/
│   ├── page.tsx                 ← Dashboard (thống kê)
│   ├── filters/page.tsx         ← Quản lý bộ lọc
│   ├── telegram/page.tsx        ← Quản lý Telegram config
│   ├── sheets/page.tsx          ← Danh sách Google Sheets
│   ├── layout.tsx               ← Layout chung (sidebar + main)
│   └── loading.tsx              ← Loading skeleton
│
├── components/
│   ├── Sidebar.tsx
│   ├── DashboardContent.tsx
│   ├── FilterManager.tsx
│   ├── TelegramManager.tsx
│   └── SheetsManager.tsx
│
├── lib/
│   ├── api.ts                   ← HTTP client gọi backend API
│   └── utils.ts                 ← Helpers (cn, formatDate...)
│
└── types/
    └── index.ts                 ← TypeScript interfaces
```

---

## 4. Database Schema

### `companies` table
| Cột | Type | Mô tả |
|---|---|---|
| id | bigint PK | |
| mst | varchar(20) UNIQUE | Mã số thuế |
| name | varchar | Tên doanh nghiệp |
| international_name | varchar nullable | Tên quốc tế |
| short_name | varchar nullable | Tên viết tắt / Loại hình DN |
| address | text nullable | Địa chỉ đăng ký |
| province | varchar(100) nullable | Tỉnh/TP (extract từ address) |
| district | varchar(100) nullable | Quận/Huyện |
| representative | varchar nullable | Người đại diện pháp luật |
| phone | varchar(20) nullable | Số điện thoại |
| registration_date | date nullable | Ngày cấp GCN |
| operation_date | date nullable | Ngày hoạt động |
| status | varchar(50) | Trạng thái (Đang hoạt động...) |
| industries | JSON nullable | Mảng ngành nghề [{code, description, is_primary}] |
| managing_tax_authority | varchar nullable | Cơ quan thuế quản lý |
| notification_sent | boolean | Đã gửi Telegram chưa |
| scraped_at | timestamp | Thời điểm quét |

### `telegram_configs` table
| Cột | Type | Mô tả |
|---|---|---|
| id | bigint PK | |
| name | varchar | Tên config |
| bot_token | varchar | Telegram Bot API token |
| chat_id | varchar | Chat/Group ID |
| is_active | boolean | Đang sử dụng |

### `filters` table
| Cột | Type | Mô tả |
|---|---|---|
| id | bigint PK | |
| name | varchar | Tên bộ lọc |
| provinces | JSON nullable | Danh sách tỉnh |
| industry_keywords | JSON nullable | Từ khóa ngành |
| industry_codes | JSON nullable | Mã ngành VSIC |
| registration_days_back | smallint nullable | Lọc N ngày gần nhất |
| require_phone | boolean | Yêu cầu có SĐT |
| telegram_config_id | FK nullable | Gửi thông báo đến config nào |

---

## 5. Chi tiết các Service

### ScraperService
- **Nhiệm vụ:** Cào HTML từ masothue.com, parse thông tin DN
- **Proxy:** Tích hợp TMProxy API (`get-current-proxy` / `get-new-proxy`), tự xoay IP mỗi 4 phút
- **Anti-duplicate:** Redis SET (`scraped_mst:{MST}`, TTL 30 ngày) + MySQL UNIQUE constraint
- **Parse HTML:**
  - Listing page: regex `href='/MST-slug'` → extract danh sách DN mới
  - Detail page: `table.table-taxinfo` → extract fields
  - Industries: `<td><a>CODE</a></td><td><a>Description</a></td>` pattern
- **User-Agent rotation:** 5 browser strings ngẫu nhiên

### PdfService
- **Thư viện:** `barryvdh/laravel-dompdf` 3.x
- **Template:** `resources/views/pdf/company-report.blade.php`
- **Output:** A4 portrait, font DejaVu Sans (Unicode Vietnamese)
- **Naming:** `{TEN_DN_KHONG_DAU}.pdf` (sanitize tiếng Việt → uppercase + underscore)
- **Cleanup:** File PDF bị xóa sau khi gửi Telegram thành công

### TelegramService
- **API:** `https://api.telegram.org/bot{TOKEN}/sendDocument`
- **Caption format:**
  ```
  TÊN CÔNG TY
  MST: 0123456789
  SĐT: 0912345678
  Ngày TL: 07/07/2026
  ```
- **File name trên Telegram:** `CONG_TY_TNHH_....pdf` (không dấu, underscore)
- **Validation:** `verifyBotToken()` kiểm tra token hợp lệ

### GoogleSheetService
- **Phương pháp:** HTTP POST tới Google Apps Script webhook
- **URL:** `https://script.google.com/macros/s/AKfycby.../exec`
- **Apps Script tự động:**
  - Tạo tab mới mỗi ngày (format `dd-MM-yyyy`)
  - Header xanh đậm + filter + freeze row 1
  - Cột MST và SĐT format Plain Text (không bị mất số 0)
  - Xóa tab cũ > 7 ngày
- **Data ghi:** TẤT CẢ DN (có SĐT và không có SĐT)

---

## 6. Deployment (Production)

### Infrastructure
- **VPS:** Vietnix, Ubuntu 20.04, IP `222.255.214.169`
- **Docker Compose:** 6 containers
- **Proxy:** TMProxy.com (IPv4, rotating, API key-based)

### Docker Containers

| Container | Image | Vai trò | Port |
|---|---|---|---|
| mst-app | PHP 8.3 FPM Alpine | Laravel API server | 8000 |
| mst-frontend | Node 20 Alpine | Next.js dashboard | 3000 |
| mst-scheduler | PHP 8.3 FPM Alpine | Scraper loop (10s interval) | - |
| mst-worker | PHP 8.3 FPM Alpine | Queue worker (backup) | - |
| mst-mysql | MySQL 8.0 | Database | 3306 |
| mst-redis | Redis 7 Alpine | Cache + Queue broker | 6379 |

### Scheduler Command
```bash
# Container mst-scheduler chạy:
while true; do php artisan scrape:companies; sleep 10; done
```

### Biến môi trường Production (.env)
```env
APP_ENV=production
APP_DEBUG=false

DB_HOST=mysql
DB_DATABASE=masothue_scraper
DB_USERNAME=scraper
DB_PASSWORD=***

REDIS_HOST=redis
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

SCRAPER_BASE_URL=https://masothue.com
SCRAPER_TMPROXY_KEY=32b34a823a0f86dbf...
SCRAPER_MAX_PER_RUN=50
```

---

## 7. Latency Budget

| Bước | Thời gian |
|---|---|
| Scheduler trigger (worst case) | 0-10 giây |
| Fetch listing page (qua proxy) | ~1 giây |
| Check dedup Redis (skip DN cũ) | <1ms/DN |
| Fetch detail page DN mới | ~1-2 giây |
| Ghi Google Sheet (webhook) | ~1 giây |
| Render PDF (DomPDF) | ~1 giây |
| Gửi Telegram (sendDocument) | ~1-2 giây |
| **Tổng worst case** | **~15-20 giây** |

---

## 8. Cách mở rộng: Thêm source cào mới

Để tích hợp thêm 1 trang web khác (VD: `dangkykinhdoanh.gov.vn`), cần:

### Bước 1: Tạo Service mới
```php
// app/Services/DkkdScraperService.php
class DkkdScraperService
{
    public function scrapeLatest(): array
    {
        // 1. Fetch listing page từ source mới
        // 2. Parse HTML → extract danh sách DN mới
        // 3. Check dedup (Redis cache)
        // 4. Fetch detail → parse → store Company model
        // 5. Return array of new Company objects
    }
}
```

### Bước 2: Tạo Command mới hoặc sửa ScrapeCompanies
```php
// Option A: Command riêng
// app/Console/Commands/ScrapeDkkd.php

// Option B: Thêm vào ScrapeCompanies (multi-source)
```

### Bước 3: Đăng ký scheduler
```yaml
# docker-compose.yml - thêm container scheduler mới
dkkd-scheduler:
  command: sh -c "while true; do php artisan scrape:dkkd; sleep 10; done"
```

### Những gì KHÔNG cần thay đổi:
- `PdfService` — template PDF có thể tái sử dụng hoặc tạo template mới
- `TelegramService` — gửi document, không phụ thuộc source
- `GoogleSheetService` — webhook chung, chỉ cần truyền Company object
- Database schema `companies` — đủ generic cho mọi source
- Frontend dashboard — đọc từ `companies` table, tự hiện

### Những gì CẦN thêm:
- Service scraper mới (parse HTML khác)
- Config `.env` cho source mới (URL, proxy settings)
- Có thể thêm cột `source` vào `companies` table để phân biệt

---

## 9. Monitoring & Troubleshooting

```bash
# Xem trạng thái containers
docker compose ps

# Xem log scraper realtime
docker compose logs scheduler -f --tail=20

# Xem DN mới nhất trong DB
docker compose exec app php artisan test:scrape --limit=0 --skip-pdf --skip-telegram

# Restart nếu bị crash
docker compose restart scheduler app

# Kiểm tra proxy đang dùng IP nào
curl "https://tmproxy.com/api/proxy/get-current-proxy?api_key=API_KEY"
```

---

## 10. Giới hạn đã biết

1. **masothue.com cache listing page** — DN mới có thể mất 2-5 phút mới hiện trên trang chính
2. **TMProxy đổi IP tối thiểu 4 phút** — nếu bị block giữa chừng phải đợi hết 4 phút
3. **DomPDF không hỗ trợ font phức tạp** — một số ký tự đặc biệt có thể không render đẹp
4. **Google Apps Script quota** — tối đa ~20,000 calls/ngày (đủ cho ~2,800 DN/ngày)
5. **Không có auth** trên dashboard — ai có IP VPS đều truy cập được (nên thêm middleware auth nếu cần)
