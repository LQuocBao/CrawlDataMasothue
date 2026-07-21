# MaSoThue Scraper

Hệ thống tự động cào dữ liệu doanh nghiệp mới đăng ký từ **masothue.com** + **tramasothue.com.vn**, xuất PDF báo cáo, gửi Telegram, và lưu Google Sheets.

---

## Tính năng

- **Dual-source scraping** — cào song song 2 trang, dedup tự động khi trùng MST
- **Bus::chain architecture** — RotateProxy → ScrapeMasothue → ScrapeTramasothue (tuần tự, chung 1 IP)
- **Telegram real-time** — DN mới có SĐT → PDF → gửi Telegram trong < 4 phút
- **Google Sheets** — tất cả DN (có/không SĐT) ghi vào Sheet theo ngày
- **Source tracking** — phân biệt rõ DN đến từ trang nào (masothue / tramasothue / cả hai)
- **Dashboard** — quản lý bộ lọc, Telegram config, Google Sheet URL, xem thống kê
- **Rotating proxy** — TMProxy API tự xoay IP, chống block

---

## Tech Stack

| Layer | Công nghệ |
|-------|-----------|
| Backend | Laravel 11.x (PHP 8.3) |
| Frontend | Next.js 14 (TypeScript, Tailwind CSS) |
| Database | MySQL 8.0 |
| Cache/Queue | Redis 7 |
| PDF | barryvdh/laravel-dompdf |
| Proxy | TMProxy.com |
| Container | Docker Compose (7 services) |

---

## Quick Start (Development)

```bash
# Clone
git clone -b refactor/bus-chain https://github.com/LQuocBao/CrawlDataMasothue.git
cd CrawlDataMasothue

# Setup
cp backend/.env.example backend/.env
docker compose up -d
sleep 15
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Truy cập:
- Dashboard: http://localhost:3000
- API: http://localhost:8000

---

## Deploy Production (VPS)

```bash
ssh root@YOUR_VPS_IP
apt update && apt install -y curl git
curl -fsSL https://raw.githubusercontent.com/LQuocBao/CrawlDataMasothue/refactor/bus-chain/deploy-staging.sh | bash
```

Chi tiết: xem [HUONG-DAN-DEPLOY-PRODUCTION.md](HUONG-DAN-DEPLOY-PRODUCTION.md)

---

## Kiến trúc

```
Scheduler (mỗi 1 phút)
  → Bus::chain [RotateProxy → ScrapeMasothue → ScrapeTramasothue]
  → Queue "scraping" (tuần tự)

DN mới phát hiện
  → GoogleSheetService (tất cả DN)
  → ProcessCompanyNotification → Queue "notifications" (song song)
      → PdfService → TelegramService
```

Chi tiết: xem [ARCHITECTURE.md](ARCHITECTURE.md)

---

## Cấu trúc Docker

| Container | Vai trò | Port |
|-----------|---------|------|
| mst-app | Laravel API | 8000 |
| mst-frontend | Next.js Dashboard | 3000 |
| mst-scheduler | Trigger chain mỗi phút | - |
| mst-worker-scraping | Queue worker (chain) | - |
| mst-worker-notifications | Queue worker (PDF+Telegram) | - |
| mst-mysql | Database | 3306 |
| mst-redis | Cache + Queue | 6379 |

---

## Tài liệu

| File | Mô tả |
|------|--------|
| [AI-CONTEXT.md](AI-CONTEXT.md) | Context đầy đủ cho AI Agent (deploy, debug, phát triển) |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Kiến trúc hệ thống chi tiết |
| [BAO-CAO-KY-THUAT-PRODUCTION.md](BAO-CAO-KY-THUAT-PRODUCTION.md) | Báo cáo kỹ thuật cho Tech Lead |
| [HUONG-DAN-DEPLOY-PRODUCTION.md](HUONG-DAN-DEPLOY-PRODUCTION.md) | Hướng dẫn deploy VPS |
| [HUONG-DAN-TEST-DEV.md](HUONG-DAN-TEST-DEV.md) | Hướng dẫn test local |

---

## Bàn giao cho khách

Chỉ cần thay đổi 2 thứ trên Dashboard (`/settings` + `/telegram`):

1. **Google Sheet URL** → Sheet mới của khách
2. **Telegram Chat ID** → Group mới của khách

Không cần sửa code. Không cần redeploy.

---

## Monitoring

```bash
docker compose ps                                  # Trạng thái
docker compose logs worker-scraping --tail=10 -f   # Log cào
docker compose logs worker-notifications --tail=10 # Log PDF/Telegram
docker compose exec app php artisan queue:failed   # Jobs lỗi
```

---

## License

Private repository - All rights reserved.
