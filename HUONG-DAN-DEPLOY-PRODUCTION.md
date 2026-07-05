# Hướng dẫn Deploy Production (VPS + Proxy)

## Tổng quan

Khi deploy xong, hệ thống sẽ:
- Mỗi 2 phút quét masothue.com tìm DN mới
- DN mới có SĐT → tạo PDF (kèm ngành nghề) → gửi Telegram
- Toàn bộ dưới 4 phút từ lúc DN xuất hiện đến lúc nhận Telegram

---

## Bước 1: Mua VPS

### Khuyến nghị
- **RAM:** 2GB trở lên
- **CPU:** 1-2 vCPU
- **Disk:** 20GB SSD
- **OS:** Ubuntu 22.04 hoặc 24.04
- **Vị trí:** Singapore hoặc Việt Nam (gần masothue.com → nhanh hơn)

### Nhà cung cấp gợi ý
| Nhà cung cấp | Giá/tháng | Ghi chú |
|---|---|---|
| DigitalOcean | $12 (~300k) | Ổn định, dễ dùng |
| Vultr | $12 (~300k) | Nhiều datacenter châu Á |
| Contabo | €5 (~150k) | Rẻ nhất, RAM nhiều |
| VPS Việt Nam (VIETSERVER, 1HOSTING) | 100-200k | Ping thấp nhất |

### Sau khi mua
Bạn sẽ nhận được:
- **IP:** VD `103.45.67.89`
- **User:** `root`
- **Password** hoặc SSH Key

---

## Bước 2: Cài đặt VPS (chạy 1 lần)

SSH vào VPS:
```bash
ssh root@103.45.67.89
```

Chạy từng lệnh:

```bash
# 1. Cập nhật hệ thống
apt update && apt upgrade -y

# 2. Cài Docker
curl -fsSL https://get.docker.com | sh

# 3. Cài Docker Compose (đã có sẵn trong Docker mới)
docker compose version

# 4. Tạo thư mục project
mkdir -p /var/www/masothue-scraper
cd /var/www/masothue-scraper
```

---

## Bước 3: Upload code lên VPS

### Cách 1: Git (khuyến nghị)
```bash
# Trên VPS
cd /var/www/masothue-scraper
git clone <URL-repo-của-bạn> .
```

### Cách 2: SCP (nếu không dùng git)
```bash
# Trên máy local (Windows PowerShell)
scp -r ./* root@103.45.67.89:/var/www/masothue-scraper/
```

---

## Bước 4: Cấu hình Production

### 4.1 Tạo file .env gốc (cho Docker Compose)
```bash
cd /var/www/masothue-scraper
nano .env
```

Paste nội dung:
```env
DB_ROOT_PASSWORD=MatKhauRootMySQL123!
DB_USERNAME=scraper
DB_PASSWORD=MatKhauScraper456!
REDIS_PASSWORD=MatKhauRedis789!
```

### 4.2 Tạo file .env cho backend
```bash
nano backend/.env
```

Paste nội dung (THAY CÁC GIÁ TRỊ THẬT):
```env
APP_NAME="MaSoThue Scraper"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://103.45.67.89:8000

FRONTEND_URL=http://103.45.67.89:3000

# Database (dùng đúng tên container Docker)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=masothue_scraper
DB_USERNAME=scraper
DB_PASSWORD=MatKhauScraper456!

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=MatKhauRedis789!
REDIS_PORT=6379

# Queue: PHẢI dùng redis trên production
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

# Proxy (xem Bước 5)
SCRAPER_PROXY_ENDPOINT=http://user:pass@proxy-host:port

# Scraper config
SCRAPER_BASE_URL=https://masothue.com
SCRAPER_VERIFY_SSL=true
SCRAPER_MAX_PER_RUN=50
SCRAPER_INTERVAL_MINUTES=2

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

### 4.3 Tạo APP_KEY
```bash
# Khởi động tạm để generate key
docker compose up -d mysql redis
sleep 15
docker compose up -d app
sleep 5
docker compose exec app php artisan key:generate
```

---

## Bước 5: Mua và cấu hình Proxy

### Tại sao cần Proxy?
- masothue.com sẽ **block IP VPS** sau vài giờ chạy liên tục
- Proxy xoay (rotating) thay đổi IP mỗi request → không bị block
- Không có proxy = chạy được 1-2 ngày rồi chết

### Nhà cung cấp Proxy gợi ý

| Nhà cung cấp | Giá/tháng | Loại | Link |
|---|---|---|---|
| **SmartProxy** | $30 (~750k) | Rotating residential | smartproxy.com |
| **Oxylabs** | $49 (~1.2tr) | Datacenter rotating | oxylabs.io |
| **BrightData** | $50 (~1.2tr) | Residential/DC | brightdata.com |
| **WebShare** | $6 (~150k) | Datacenter shared | webshare.io |
| **ProxyScrape** | $10 (~250k) | Rotating | proxyscrape.com |

### Khuyến nghị cho project này:
- **WebShare** hoặc **ProxyScrape** nếu ngân sách thấp (~150-250k/tháng)
- **SmartProxy** nếu cần ổn định nhất

### Sau khi mua, bạn nhận được:
```
Proxy endpoint: http://username:password@gate.smartproxy.com:7000
```

### Cập nhật vào .env:
```env
SCRAPER_PROXY_ENDPOINT=http://username:password@gate.smartproxy.com:7000
```

### Nếu KHÔNG mua proxy (tạm thời):
- Để trống `SCRAPER_PROXY_ENDPOINT=`
- Hệ thống vẫn chạy nhưng delay lâu hơn (2-4 giây/request)
- Có thể bị block sau 1-3 ngày → cần restart/đổi VPS IP

---

## Bước 6: Deploy và chạy

```bash
cd /var/www/masothue-scraper

# Build và khởi động tất cả
docker compose -f docker-compose.prod.yml up -d --build

# Đợi MySQL healthy (~30 giây)
sleep 30

# Chạy migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Verify
docker compose -f docker-compose.prod.yml ps
```

Kết quả mong đợi - tất cả Status = `Up`:
```
mst-app        Up
mst-frontend   Up
mst-mysql      Up (healthy)
mst-redis      Up (healthy)
mst-worker     Up
mst-scheduler  Up
mst-nginx      Up
```

---

## Bước 7: Cấu hình Telegram cho khách

### Cách 1: Qua Dashboard
1. Mở `http://IP-VPS:3000/telegram`
2. Thêm cấu hình:
   - Bot Token của khách
   - Chat ID của group khách

### Cách 2: Qua command
Tạo file `/var/www/masothue-scraper/backend/setup_telegram.php`:
```php
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

App\Models\TelegramConfig::updateOrCreate(
    ['id' => 1],
    [
        'name' => 'Group khách hàng',
        'bot_token' => 'TOKEN_CỦA_KHÁCH',
        'chat_id' => 'CHAT_ID_CỦA_KHÁCH',
        'is_active' => true,
    ]
);
echo "Done!\n";
```

Chạy:
```bash
docker compose -f docker-compose.prod.yml exec app php setup_telegram.php
```

---

## Bước 8: Test trước khi bàn giao

```bash
# Test scrape 1 DN
docker compose -f docker-compose.prod.yml exec app php artisan test:scrape --limit=1

# Kiểm tra Telegram group có nhận PDF không
# Nếu OK → hệ thống đã chạy tự động (scheduler mỗi 2 phút)
```

---

## Bước 9: Kiểm tra hệ thống đang chạy

```bash
# Xem logs scraper
docker compose -f docker-compose.prod.yml logs scheduler --tail=20

# Xem logs worker (PDF + Telegram)
docker compose -f docker-compose.prod.yml logs worker --tail=20

# Xem số DN đã scrape
docker compose -f docker-compose.prod.yml exec app php artisan test:scrape --limit=0 --skip-pdf --skip-telegram
```

---

## Tóm tắt chi phí hàng tháng

| Hạng mục | Chi phí |
|---|---|
| VPS 2GB RAM | 150-300k |
| Proxy (rotating) | 150k-1.2tr |
| **Tổng tối thiểu** | **~300-500k/tháng** |
| **Tổng khuyến nghị** | **~500k-1.5tr/tháng** |

---

## Xử lý sự cố

### Bị block IP (không có proxy)
```bash
# Restart VPS hoặc đổi IP
# Hoặc tạm dừng scheduler 1-2 giờ:
docker compose -f docker-compose.prod.yml stop scheduler
# Chạy lại sau:
docker compose -f docker-compose.prod.yml start scheduler
```

### Worker bị lỗi
```bash
docker compose -f docker-compose.prod.yml restart worker
```

### Muốn xem tất cả DN đã crawl
Mở `http://IP-VPS:3000` → Dashboard

### Cập nhật code mới
```bash
cd /var/www/masothue-scraper
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

---

## Checklist bàn giao khách hàng

- [ ] VPS đã mua và truy cập được
- [ ] Docker đã cài trên VPS
- [ ] Code đã upload lên VPS
- [ ] .env đã cấu hình đúng (DB, Redis, Proxy)
- [ ] `docker compose up -d` chạy thành công
- [ ] Migration chạy OK
- [ ] Telegram bot + chat ID đã cấu hình
- [ ] Test `php artisan test:scrape --limit=1` → nhận PDF trên Telegram
- [ ] Scheduler đang chạy (tự động mỗi 2 phút)
- [ ] Gửi cho khách: IP truy cập Dashboard + hướng dẫn cơ bản
