# Hướng dẫn Test trên môi trường Dev

> Dành cho developer muốn chạy và test hệ thống trên máy local.

---

## 1. Khởi động môi trường

### Yêu cầu
- Docker Desktop đang chạy
- Git

### Khởi động (Docker)

```bash
# Clone repo (nếu chưa có)
git clone -b refactor/bus-chain https://github.com/LQuocBao/CrawlDataMasothue.git
cd CrawlDataMasothue

# Copy env
cp backend/.env.example backend/.env

# Khởi động 7 containers
docker compose up -d

# Đợi MySQL ready (~15s)
sleep 15

# Setup Laravel
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

### Kiểm tra containers

```bash
docker compose ps
```

Phải thấy 7 containers `Up`:
- mst-app (API :8000)
- mst-frontend (Dashboard :3000)
- mst-scheduler
- mst-worker-scraping
- mst-worker-notifications
- mst-mysql
- mst-redis

### Truy cập

| Service | URL |
|---------|-----|
| Dashboard | http://localhost:3000 |
| API | http://localhost:8000 |
| API stats | http://localhost:8000/api/v1/companies/stats |

---

## 2. Kiến trúc hoạt động (trên dev)

```
Scheduler (mỗi 1 phút)
  → dispatch Bus::chain lên queue "scraping"
  → worker-scraping xử lý tuần tự:
      1. RotateProxyJob (skip nếu không có TMProxy key)
      2. ScrapeMasothueJob
      3. ScrapeTramasothueJob
  → DN mới → dispatch ProcessCompanyNotification lên queue "notifications"
  → worker-notifications xử lý: PDF + Telegram
```

**Trên dev không có proxy**: `RotateProxyJob` sẽ log "No TMProxy key, running without proxy" và các job scrape sẽ gọi trực tiếp (có thể bị block sau vài giờ).

---

## 3. Test Scraper thủ công

### Dispatch chain 1 lần

```bash
docker compose exec app php artisan scraper:run
```

### Xem log chain xử lý

```bash
docker compose logs worker-scraping --tail=20
```

Kết quả mong đợi:
```
RotateProxyJob ................ DONE
ScrapeMasothueJob ............. DONE
ScrapeTramasothueJob .......... DONE
```

### Xem DN đã cào được

```bash
docker compose exec app php artisan tinker --execute="echo App\Models\Company::count() . ' companies in DB';"
```

---

## 4. Fake data để test (không cần scrape thật)

Nếu bị block IP hoặc muốn test nhanh:

```bash
docker compose exec app php artisan tinker
```

Paste vào tinker:

```php
use App\Models\Company;

// DN từ masothue, có SĐT (sẽ gửi Telegram)
Company::create([
    'mst' => '0109876543',
    'name' => 'CONG TY TNHH CONG NGHE ABC',
    'source' => 'masothue',
    'address' => '123 Tran Duy Hung, Cau Giay, Ha Noi',
    'province' => 'Ha Noi',
    'representative' => 'Nguyen Van A',
    'phone' => '0901234567',
    'operation_date' => now(),
    'status' => 'Dang hoat dong',
    'industries' => [
        ['code' => '6201', 'description' => 'Lap trinh may vi tinh', 'is_primary' => true],
        ['code' => '6202', 'description' => 'Tu van may vi tinh', 'is_primary' => false],
    ],
    'scraped_at' => now(),
]);

// DN từ tramasothue, không có SĐT (chỉ vào Sheet, không Telegram)
Company::create([
    'mst' => '0109876544',
    'name' => 'CONG TY CP THUONG MAI XYZ',
    'source' => 'tramasothue',
    'address' => '456 Nguyen Trai, Thanh Xuan, Ha Noi',
    'province' => 'Ha Noi',
    'representative' => 'Tran Thi B',
    'phone' => null,
    'operation_date' => now(),
    'status' => 'Dang hoat dong',
    'industries' => [
        ['code' => '4711', 'description' => 'Ban le trong sieu thi', 'is_primary' => true],
    ],
    'scraped_at' => now(),
]);

echo "Da tao 2 DN test!\n";
```

---

## 5. Cấu hình Telegram Bot (test)

### Tạo bot test

1. Mở Telegram → tìm `@BotFather`
2. Gửi `/newbot` → đặt tên → copy **Bot Token**
3. Tạo group test → thêm bot vào
4. Lấy Chat ID: mở `https://api.telegram.org/bot<TOKEN>/getUpdates`

### Cấu hình qua Dashboard

1. Mở http://localhost:3000/telegram
2. Bấm "Thêm cấu hình"
3. Điền Token + Chat ID
4. Bấm test (biểu tượng tia sét)

---

## 6. Test luồng PDF + Telegram

```bash
docker compose exec app php artisan tinker
```

```php
use App\Models\Company;
use App\Jobs\ProcessCompanyNotification;

$company = Company::whereNotNull('phone')->latest()->first();
ProcessCompanyNotification::dispatch($company)->onQueue('notifications');
echo "Job dispatched! Check Telegram...\n";
```

Kiểm tra:
```bash
docker compose logs worker-notifications --tail=10
```

Mong đợi: Telegram group nhận PDF với caption có dòng "Nguồn: masothue.com".

---

## 7. Test Google Sheet

### Cấu hình

1. Mở http://localhost:3000/settings
2. Paste webhook URL Apps Script
3. Bấm "Test kết nối" → phải hiện xanh "Thành công"

### Test ghi DN vào Sheet

```bash
docker compose exec app php artisan tinker
```

```php
$company = App\Models\Company::latest()->first();
$gs = app(App\Services\GoogleSheetService::class);
$ok = $gs->appendCompany($company);
echo $ok ? "SHEET OK!" : "FAILED";
```

---

## 8. Xem Logs

```bash
# Scraper chain
docker compose logs worker-scraping --tail=30 -f

# Notifications (PDF + Telegram)
docker compose logs worker-notifications --tail=20

# Scheduler
docker compose logs scheduler --tail=10

# Laravel log (errors)
docker compose exec app tail -50 storage/logs/laravel.log
```

---

## 9. Reset / Dọn dẹp

```bash
# Reset DB (xóa toàn bộ data)
docker compose exec app php artisan migrate:fresh

# Xóa Redis dedup cache (để cào lại MST đã xử lý)
docker compose exec app php artisan tinker --execute="Illuminate\Support\Facades\Cache::flush(); echo 'Cache cleared';"

# Tắt hoàn toàn
docker compose down

# Tắt + xóa data (volumes)
docker compose down -v
```

---

## 10. Checklist test nhanh

- [ ] `docker compose up -d` chạy không lỗi (7 containers Up)
- [ ] http://localhost:3000 hiển thị Dashboard
- [ ] http://localhost:8000/api/v1/companies/stats trả về JSON
- [ ] `php artisan scraper:run` dispatch chain OK
- [ ] worker-scraping log: 3 jobs DONE (Rotate → Masothue → Tramasothue)
- [ ] Tạo Telegram config + test kết nối thành công
- [ ] Dispatch ProcessCompanyNotification → nhận PDF trên Telegram
- [ ] PDF hiện đúng 2 section + dòng "Nguồn dữ liệu"
- [ ] Test Google Sheet connection thành công
- [ ] DN ghi vào Sheet có cột "Nguồn"
