# Crawl Data Mã Số Thuế

Hệ thống tự động thu thập dữ liệu doanh nghiệp từ nguồn mã số thuế, lưu trữ và gửi thông báo qua Telegram, quản lý trên giao diện web.

## 🚀 Giới thiệu

Đây là hệ thống tự động cào dữ liệu doanh nghiệp (Scraper) sử dụng Laravel cho backend và Next.js cho frontend. Hệ thống hỗ trợ:

- **Tự động thu thập:** Cào dữ liệu theo lịch trình từ trang web mã số thuế.
- **Hỗ trợ Proxy:** Tích hợp proxy tự động xoay IP chống block.
- **Thông báo Telegram:** Tự động tạo file PDF và gửi báo cáo qua Telegram.
- **Giao diện Dashboard:** Quản lý dữ liệu doanh nghiệp qua giao diện Next.js hiện đại.
- **Queue & Worker:** Xử lý bất đồng bộ mượt mà, đảm bảo hiệu suất.

## 📁 Cấu trúc thư mục (Architecture)

- `backend/`: Chứa mã nguồn Laravel (API, Scraper, Queue Jobs).
- `frontend/`: Chứa mã nguồn Next.js (Dashboard).
- `mysql/`, `nginx/`, `supervisor/`: Các file cấu hình cho Docker.
- `scripts/`: Chứa shell scripts hỗ trợ setup và deploy.
- `docker-compose.yml` / `docker-compose.prod.yml`: Cấu hình Docker để khởi chạy môi trường Dev và Production.

---

## 🛠️ Hướng dẫn cài đặt & triển khai (Deployment)

### 1. Môi trường Development (Docker)

Cách nhanh nhất để khởi chạy hệ thống:

```bash
# Clone the repo
git clone https://github.com/LQuocBao/CrawlDataMasothue.git masothue-scraper
cd masothue-scraper

# Run setup script
chmod +x scripts/setup-dev.sh
./scripts/setup-dev.sh
```

**Các dịch vụ (Services):**
- Frontend: `http://localhost:3000`
- API Backend: `http://localhost:8000`
- MySQL: `localhost:3306`
- Redis: `localhost:6379`

### 2. Môi trường Production (VPS)

Yêu cầu VPS có Docker, RAM > 2GB (Ubuntu 22.04+).

1. Cài đặt Docker:
```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
```

2. Khởi tạo môi trường:
```bash
cd /var/www
git clone https://github.com/LQuocBao/CrawlDataMasothue.git masothue-scraper
cd masothue-scraper

# Copy env files
cp .env.docker .env
cp backend/.env.production.example backend/.env

# Cập nhật thông tin cấu hình vào file .env
```

3. Triển khai (Deploy):
```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh
```

---

## 🛡️ Hướng dẫn Troubleshooting

1. **Scraper bị block IP?**
- Kiểm tra proxy đang dùng trong `backend/.env` (ví dụ `SCRAPER_PROXY_ENDPOINT=http://...`)
- Check log: `docker compose -f docker-compose.prod.yml exec app cat storage/logs/scraper.log`
- Tăng delay scraping: Set `SCRAPER_DELAY_MIN` và `SCRAPER_DELAY_MAX` cao hơn.

2. **Lỗi khi tạo PDF?**
- Hãy chắc chắn thư mục `storage/app/pdfs` có quyền write.

3. **Jobs trong Queue bị fail?**
- Kiểm tra job lỗi: `docker compose exec app php artisan queue:failed`
- Thử chạy lại: `docker compose exec app php artisan queue:retry all`

---

> Chi tiết hơn xin tham khảo thêm tài liệu `ARCHITECTURE.md` và `DEPLOYMENT.md` có trong repo.
