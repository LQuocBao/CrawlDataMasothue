# Hướng dẫn Deploy Production

> Áp dụng cho branch `refactor/bus-chain` - Kiến trúc Bus::chain + Docker Compose

---

## Tổng quan

Sau khi deploy xong, hệ thống sẽ:
- Mỗi 1 phút quét masothue.com + tramasothue.com.vn tìm DN mới
- DN mới có SĐT → tạo PDF → gửi Telegram
- Tất cả DN (có/không SĐT) → ghi Google Sheets
- Phân biệt nguồn dữ liệu (masothue / tramasothue / cả hai)

---

## Yêu cầu VPS

| Thông số | Tối thiểu | Khuyến nghị |
|----------|-----------|-------------|
| RAM | 2GB | 4GB |
| CPU | 1 vCPU | 2 vCPU |
| Disk | 20GB SSD | 40GB SSD |
| OS | Ubuntu 22.04+ | Ubuntu 24.04 |
| Vị trí | Bất kỳ | Việt Nam/Singapore (gần source) |

---

## Deploy nhanh (1 lệnh)

SSH vào VPS rồi chạy:

```bash
apt update -qq && apt install -y curl git && \
curl -fsSL https://raw.githubusercontent.com/LQuocBao/CrawlDataMasothue/refactor/bus-chain/deploy-staging.sh | bash
```

Script tự động: cài Docker → clone repo → tạo .env → build containers → migrate.

---

## Deploy thủ công (từng bước)

### Bước 1: Cài Docker

```bash
curl -fsSL https://get.docker.com | sh
docker --version
docker compose version
```

### Bước 2: Clone repo

```bash
mkdir -p /var/www
cd /var/www
git clone -b refactor/bus-chain https://github.com/LQuocBao/CrawlDataMasothue.git masothue-scraper
cd masothue-scraper
```

### Bước 3: Tạo .env

```bash
cp backend/.env.example backend/.env
nano backend/.env
```

Sửa các giá trị quan trọng:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://IP_VPS:8000

DB_HOST=mysql
DB_DATABASE=masothue_scraper
DB_USERNAME=scraper
DB_PASSWORD=scraper_pass

REDIS_HOST=redis
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

# TMProxy (mua tại tmproxy.com)
SCRAPER_TMPROXY_KEY=your_api_key_here

LOG_LEVEL=warning
```

### Bước 4: Build và start

```bash
docker compose up -d --build
```

Đợi MySQL healthy (~15s):

```bash
sleep 15
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
```

### Bước 5: Verify

```bash
docker compose ps
```

Kết quả mong đợi - 7 containers `Up`:
```
mst-app                  Up
mst-frontend             Up
mst-mysql                Up (healthy)
mst-redis                Up (healthy)
mst-scheduler            Up
mst-worker-scraping      Up
mst-worker-notifications Up
```

---

## Cấu hình sau deploy

### Telegram Bot (bắt buộc)

Mở Dashboard: `http://IP_VPS:3000/telegram`

1. Bấm "Thêm cấu hình"
2. Điền Bot Token (từ @BotFather) + Chat ID (group khách)
3. Bấm "Test kết nối" để verify

### Google Sheet (bắt buộc)

Mở Dashboard: `http://IP_VPS:3000/settings`

1. Paste URL webhook Apps Script vào ô "Webhook URL"
2. Bấm "Test kết nối"
3. Bấm "Lưu cài đặt"

### Proxy TMProxy (khuyến nghị)

1. Mua tại [tmproxy.com](https://tmproxy.com)
2. Lấy API key
3. Thêm vào `.env`: `SCRAPER_TMPROXY_KEY=your_key`
4. Restart: `docker compose restart worker-scraping`

> Không có proxy: hệ thống vẫn chạy nhưng có thể bị block IP sau 1-3 ngày.

---

## Proxy: Chi phí & Lựa chọn

| Nhà cung cấp | Giá/tháng | Loại |
|---------------|-----------|------|
| TMProxy.com | ~150-300k | Rotating IPv4 (khuyến nghị) |
| WebShare | ~150k | Datacenter shared |
| SmartProxy | ~750k | Residential rotating |

Hệ thống hiện dùng **TMProxy API** (gọi endpoint lấy/xoay IP). Nếu đổi sang proxy khác cần sửa `RotateProxyJob.php`.

---

## Monitoring hàng ngày

```bash
# Xem chain có đang chạy không
docker compose logs worker-scraping --tail=10

# Xem notification (PDF + Telegram) 
docker compose logs worker-notifications --tail=10

# DN mới nhất
docker compose exec app php artisan tinker --execute="echo App\Models\Company::latest()->first()->name;"

# Jobs bị fail
docker compose exec app php artisan queue:failed

# Retry jobs fail
docker compose exec app php artisan queue:retry all
```

---

## Cập nhật code

```bash
cd /var/www/masothue-scraper
git pull origin refactor/bus-chain
docker compose restart worker-scraping worker-notifications scheduler
docker compose exec app php artisan migrate --force
```

Nếu có thay đổi Dockerfile:
```bash
docker compose up -d --build
```

---

## Bàn giao cho khách mới

Chỉ cần đổi **2 thứ** trên Dashboard (http://IP:3000):

1. **Cài đặt** → Google Sheet webhook URL → URL Sheet mới của khách
2. **Telegram** → Thêm config mới → Bot Token + Chat ID group khách

Không cần sửa code. Không cần restart.

---

## Xử lý sự cố

| Vấn đề | Giải pháp |
|--------|-----------|
| Container crash | `docker compose restart <tên-container>` |
| Bị block IP (không proxy) | Thêm TMProxy key hoặc đợi 1-2h |
| Telegram không gửi được | Check bot token + chat ID ở Dashboard |
| Sheet không ghi được | Check URL webhook ở Dashboard /settings |
| Worker scraping stuck | `docker compose restart worker-scraping` |
| Disk đầy | `docker system prune -f` + xóa log cũ |

---

## Chi phí vận hành

| Hạng mục | Giá/tháng |
|----------|-----------|
| VPS 2GB RAM (Vietnix/DigitalOcean) | 150-300k |
| TMProxy rotating | 150-300k |
| **Tổng** | **300-600k/tháng** |
