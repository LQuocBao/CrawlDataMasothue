# Báo cáo Kỹ thuật - Hệ thống Cào Dữ liệu Doanh nghiệp

> Cập nhật: Branch `refactor/bus-chain` - Kiến trúc Bus::chain + Dual-source

---

## 1. Tổng quan

### Mục đích
Hệ thống tự động cào dữ liệu doanh nghiệp mới đăng ký từ **2 nguồn** (`masothue.com` + `tramasothue.com.vn`), xuất PDF báo cáo, gửi thông báo Telegram, và lưu trữ vào Google Sheets.

### Yêu cầu nghiệp vụ
- Phát hiện doanh nghiệp mới trong vòng < 4 phút
- Ghi **tất cả DN** (có/không SĐT) vào Google Sheets
- Chỉ gửi Telegram cho DN **có SĐT hợp lệ** (10 số, bắt đầu bằng 0)
- Phân biệt rõ nguồn dữ liệu (masothue.com / tramasothue.com.vn / cả hai)
- Không gửi trùng khi cùng DN xuất hiện ở cả 2 trang

---

## 2. Kiến trúc (Bus::chain + Queue Workers)

### Luồng chính

```
Scheduler (everyMinute, withoutOverlapping)
    │
    ▼ dispatch Bus::chain → Queue "scraping"
    │
    ├─ [1] RotateProxyJob
    │       └─ Gọi TMProxy API → lưu IP vào Redis (TTL 5 phút)
    │
    ├─ [2] ScrapeMasothueJob
    │       └─ GET masothue.com (qua proxy) → regex parse
    │       └─ Dedup: Redis cache + DB unique constraint
    │       └─ Lưu DB (source='masothue') + ghi Google Sheet
    │       └─ DN có SĐT → dispatch ProcessCompanyNotification
    │
    └─ [3] ScrapeTramasothueJob
            └─ GET tramasothue.com.vn → DomCrawler parse
            └─ sleep(rand(1,3)) giữa mỗi request (chống WAF)
            └─ Cross-source dedup: MST đã có → merge source, skip notify
            └─ DN mới → lưu DB (source='tramasothue') + Sheet + Telegram
```

### Tách biệt Notification

```
Queue "notifications" (song song, độc lập):
    │
    └─ ProcessCompanyNotification
        ├─ PdfService::generateCompanyPdf()   → render A4 PDF (DomPDF)
        ├─ TelegramService::sendDocument()    → upload PDF + caption
        └─ Cleanup file PDF
```

### Tại sao Bus::chain?
- **Tối ưu proxy:** 3 Job dùng chung 1 IP, không lãng phí rotation
- **Tuần tự an toàn:** tramasothue chờ masothue xong → dedup cross-source chính xác
- **Không block notification:** PDF/Telegram chạy song song trên queue riêng

---

## 3. Tech Stack

| Layer | Công nghệ | Version |
|-------|-----------|---------|
| Backend | Laravel | 11.x |
| Runtime | PHP | 8.3 |
| Frontend | Next.js + TypeScript + Tailwind | 14.x |
| Database | MySQL | 8.0 |
| Cache/Queue | Redis | 7.x |
| PDF | barryvdh/laravel-dompdf | 3.x |
| HTML Parse | symfony/dom-crawler + css-selector | 7.x |
| Proxy | TMProxy.com | API-based |
| Notification | Telegram Bot API | sendDocument |
| Sheet | Google Apps Script | Webhook |
| Container | Docker Compose | 7 services |

---

## 4. Docker Containers (7 services)

| # | Container | Command | Vai trò |
|---|-----------|---------|---------|
| 1 | mst-app | `php artisan serve` | Laravel API (:8000) |
| 2 | mst-frontend | `npm run dev` | Dashboard (:3000) |
| 3 | mst-scheduler | `schedule:run` loop | Trigger chain mỗi phút |
| 4 | mst-worker-scraping | `queue:work --queue=scraping` | Xử lý chain tuần tự |
| 5 | mst-worker-notifications | `queue:work --queue=notifications` | PDF + Telegram song song |
| 6 | mst-mysql | MySQL 8.0 | Database |
| 7 | mst-redis | Redis 7 Alpine | Cache + Queue broker |

---

## 5. Source Tracking + Dedup

### Cột `source` trong bảng `companies`

| Giá trị | Ý nghĩa |
|---------|---------|
| `masothue` | DN chỉ thấy ở masothue.com |
| `tramasothue` | DN chỉ thấy ở tramasothue.com.vn |
| `both` | DN xuất hiện ở cả 2 nguồn |

### Logic Hướng C (First-write wins, second merges)

1. **DN hoàn toàn mới** → lưu + Sheet + Telegram (nếu có SĐT)
2. **DN trùng MST từ nguồn khác** → merge source='both' + bổ sung field trống + KHÔNG gửi lại
3. **DN trùng MST cùng nguồn** → skip (Redis dedup, TTL 30 ngày)

### Hiển thị nguồn cho khách hàng

| Kênh | Format |
|------|--------|
| Google Sheet | Cột "Nguồn": `masothue.com` / `tramasothue.com.vn` / `cả hai` |
| Telegram caption | Dòng `Nguồn: masothue.com` |
| PDF | Section "Nguồn dữ liệu: ..." |

---

## 6. Cấu trúc codebase chính

```
backend/app/
├── Console/Commands/
│   └── RunScraperChain.php         ← php artisan scraper:run
├── Jobs/
│   ├── RotateProxyJob.php          ← TMProxy → Redis cache
│   ├── ScrapeMasothueJob.php       ← Cào + source tracking
│   ├── ScrapeTramasothueJob.php    ← Cào + cross-source dedup
│   └── ProcessCompanyNotification.php ← PDF + Telegram
├── Services/
│   ├── ScraperService.php          ← Parse masothue.com (regex)
│   ├── TraMaSoThueScraperService.php ← Parse tramasothue (DomCrawler)
│   ├── PdfService.php              ← DomPDF render
│   ├── TelegramService.php         ← Bot API
│   └── GoogleSheetService.php      ← Apps Script webhook
├── Models/
│   ├── Company.php                 ← source, mergeSource(), source_label
│   ├── AppSetting.php             ← Key-value config (Sheet URL)
│   ├── TelegramConfig.php
│   └── Filter.php
└── Http/Controllers/Api/
    ├── SettingsController.php      ← Dashboard cài đặt
    └── ...
```

---

## 7. API Endpoints

| Method | Endpoint | Mô tả |
|--------|----------|--------|
| GET | /api/v1/companies/stats | Thống kê dashboard |
| GET | /api/v1/companies | Danh sách DN (paginated) |
| CRUD | /api/v1/filters | Quản lý bộ lọc |
| CRUD | /api/v1/telegram-configs | Quản lý Telegram bot |
| POST | /api/v1/telegram-configs/{id}/test | Test kết nối |
| GET/PUT | /api/v1/settings | Cài đặt hệ thống |
| POST | /api/v1/settings/test-sheet | Test Google Sheet |
| GET | /api/v1/sheets | Danh sách sheets |

---

## 8. Latency Budget

| Bước | Thời gian | Ghi chú |
|------|-----------|---------|
| Scheduler trigger | 0-60s | everyMinute, withoutOverlapping |
| RotateProxyJob | ~1s | API call TMProxy |
| ScrapeMasothueJob (listing + details) | 3-10s | Tùy số DN mới |
| ScrapeTramasothueJob | 5-30s | sleep(1-3s) mỗi DN |
| Google Sheet webhook | ~1s/DN | POST HTTP |
| ProcessCompanyNotification (PDF) | 1-2s | DomPDF render |
| ProcessCompanyNotification (Telegram) | 1-2s | sendDocument |
| **Tổng worst case** | **~60-90s** | Nằm trong budget 4 phút |

---

## 9. Cách mở rộng: Thêm source cào mới

### Những gì CẦN tạo:
1. `Services/NewSourceScraperService.php` — parse HTML nguồn mới
2. `Jobs/ScrapeNewSourceJob.php` — job cào, dùng `storeWithSourceTracking()`
3. Thêm constant `SOURCE_NEWSOURCE` vào `Company.php`
4. Thêm Job vào Bus::chain trong `RunScraperChain.php`

### Những gì KHÔNG cần thay đổi:
- PdfService, TelegramService, GoogleSheetService — tái sử dụng
- ProcessCompanyNotification — nhận Company object, không quan tâm source
- Frontend Dashboard — đọc từ `companies` table, tự hiện
- Database schema — cột `source` đủ generic

---

## 10. Deployment

### VPS hiện tại
| Môi trường | IP | Branch |
|------------|-------|--------|
| Production (khách) | 222.255.214.169 | main (arch cũ) |
| Staging (test) | 14.225.205.98 | refactor/bus-chain |

### Deploy 1 lệnh
```bash
curl -fsSL https://raw.githubusercontent.com/LQuocBao/CrawlDataMasothue/refactor/bus-chain/deploy-staging.sh | bash
```

### Bàn giao khách mới
Chỉ cần đổi 2 thứ trên Dashboard:
1. `/settings` → Google Sheet webhook URL mới
2. `/telegram` → Chat ID group mới

---

## 11. Monitoring

```bash
docker compose ps                                    # Trạng thái containers
docker compose logs worker-scraping --tail=20 -f     # Log chain
docker compose logs worker-notifications --tail=10   # Log PDF/Telegram
docker compose exec app php artisan queue:failed     # Jobs lỗi
```
