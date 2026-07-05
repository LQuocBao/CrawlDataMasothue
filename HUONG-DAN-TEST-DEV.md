# Hướng dẫn Test trên môi trường Dev

## Mục lục

1. [Khởi động môi trường](#1-khởi-động-môi-trường)
2. [Kiểm tra hệ thống hoạt động](#2-kiểm-tra-hệ-thống-hoạt-động)
3. [Test Scraper thủ công](#3-test-scraper-thủ-công)
4. [Cấu hình Telegram Bot](#4-cấu-hình-telegram-bot)
5. [Tạo bộ lọc](#5-tạo-bộ-lọc-với-điều-kiện-ngày--sđt)
6. [Test toàn luồng end-to-end](#6-test-toàn-luồng-end-to-end)
7. [Xem logs & debug](#7-xem-logs--debug)
8. [Dọn dẹp & reset](#8-dọn-dẹp--reset)

---

## 1. Khởi động môi trường

### Yêu cầu

- Docker Desktop đã cài đặt và đang chạy
- Git
- (Tùy chọn) PHP 8.2+, Composer, Node.js 20+ nếu muốn chạy không qua Docker

### Cách 1: Chạy bằng Docker (khuyến nghị)

```bash
# Bạn đang ở thư mục CrawlDataMasothue rồi, chỉ cần copy file cấu hình
copy .env.docker .env
cd backend && copy .env.example .env && cd ..
cd frontend && copy .env.local.example .env.local && cd ..

# Khởi động tất cả services
docker compose up -d

# Đợi MySQL sẵn sàng (~10 giây)
timeout /t 10

# Tạo APP_KEY + chạy migrations
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

### Cách 2: Chạy thủ công (không Docker)

**Terminal 1 - Backend:**
```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
# Sửa DB_HOST, DB_USERNAME, DB_PASSWORD trong .env cho đúng MySQL local
php artisan migrate
php artisan db:seed
php artisan serve
```

**Terminal 2 - Queue Worker:**
```bash
cd backend
php artisan queue:work database --queue=notifications,default --sleep=2 --tries=3
```

**Terminal 3 - Frontend:**
```bash
cd frontend
npm install
npm run dev
```

---

## 2. Kiểm tra hệ thống hoạt động

### Kiểm tra API

```bash
# Lấy thống kê (nếu trả về JSON = OK)
curl http://localhost:8000/api/v1/companies/stats

# Lấy danh sách filters (sẽ thấy 3 filter từ seeder)
curl http://localhost:8000/api/v1/filters
```

### Kiểm tra Frontend

Mở trình duyệt → http://localhost:3000

Bạn sẽ thấy:
- Dashboard với số liệu thống kê
- Menu bên trái: Dashboard / Bộ lọc / Telegram

---

## 3. Test Scraper thủ công

### Chạy dry-run (không lưu DB, không gửi thông báo)

```bash
# Docker:
docker compose exec app php artisan scrape:companies --dry-run

# Không Docker:
cd backend && php artisan scrape:companies --dry-run
```

Kết quả mong đợi:
```
Starting company scrape...
Found X new companies.
  [DRY-RUN] Match: 0123456789 -> Filter: DN Hà Nội - CNTT
Completed in 5.2s. Scraped: X, Matched: Y, Jobs dispatched: 0
```

### Chạy thật (lưu DB + dispatch jobs)

```bash
docker compose exec app php artisan scrape:companies
```

> ⚠️ **Lưu ý:** Trên dev không có proxy, nếu masothue.com chặn IP thì scraper sẽ không lấy được data. Trong trường hợp đó, dùng cách fake data bên dưới.

### Fake data để test (không cần scrape thật)

Tạo file `backend/database/seeders/FakeCompanySeeder.php`:

```bash
docker compose exec app php artisan make:seeder FakeCompanySeeder
```

Rồi chạy lệnh tinker để tạo data giả:

```bash
docker compose exec app php artisan tinker
```

Trong tinker, paste đoạn sau:

```php
use App\Models\Company;

// DN có SĐT + đăng ký hôm nay (sẽ match filter)
Company::create([
    'mst' => '0109876543',
    'name' => 'CÔNG TY TNHH CÔNG NGHỆ ABC',
    'address' => '123 Trần Duy Hưng, Cầu Giấy, Thành phố Hà Nội',
    'province' => 'Hà Nội',
    'district' => 'Cầu Giấy',
    'representative' => 'Nguyễn Văn A',
    'phone' => '0901234567',
    'registration_date' => now()->format('Y-m-d'),
    'status' => 'Đang hoạt động',
    'industries' => [
        ['code' => '6201', 'description' => 'Lập trình máy vi tính', 'is_primary' => true],
        ['code' => '6202', 'description' => 'Tư vấn máy vi tính và quản trị hệ thống máy vi tính', 'is_primary' => false],
        ['code' => '4651', 'description' => 'Bán buôn máy vi tính, thiết bị ngoại vi và phần mềm', 'is_primary' => false],
    ],
    'scraped_at' => now(),
]);

// DN không có SĐT (sẽ KHÔNG match nếu require_phone = true)
Company::create([
    'mst' => '0109876544',
    'name' => 'CÔNG TY CP THƯƠNG MẠI XYZ',
    'address' => '456 Nguyễn Trãi, Thanh Xuân, Thành phố Hà Nội',
    'province' => 'Hà Nội',
    'district' => 'Thanh Xuân',
    'representative' => 'Trần Thị B',
    'phone' => null,
    'registration_date' => now()->format('Y-m-d'),
    'status' => 'Đang hoạt động',
    'industries' => [
        ['code' => '6201', 'description' => 'Lập trình máy vi tính', 'is_primary' => true],
    ],
    'scraped_at' => now(),
]);

// DN đăng ký 5 ngày trước (sẽ KHÔNG match nếu registration_days_back = 3)
Company::create([
    'mst' => '0109876545',
    'name' => 'CÔNG TY TNHH PHẦN MỀM DEF',
    'address' => '789 Lê Văn Lương, Nam Từ Liêm, Thành phố Hà Nội',
    'province' => 'Hà Nội',
    'district' => 'Nam Từ Liêm',
    'representative' => 'Lê Văn C',
    'phone' => '0912345678',
    'registration_date' => now()->subDays(5)->format('Y-m-d'),
    'status' => 'Đang hoạt động',
    'industries' => [
        ['code' => '6201', 'description' => 'Lập trình máy vi tính', 'is_primary' => true],
    ],
    'scraped_at' => now()->subDays(5),
]);

echo "Đã tạo 3 DN test!\n";
```

---

## 4. Cấu hình Telegram Bot

### Bước 1: Tạo Bot

1. Mở Telegram, tìm `@BotFather`
2. Gửi `/newbot`
3. Đặt tên bot (VD: `MSTScraper Test Bot`)
4. Sao chép **Bot Token** (dạng: `7123456789:AAHxxxxxxxxxxxxxxxxxxxxxxxx`)

### Bước 2: Lấy Chat ID

**Cách đơn giản nhất:**
1. Tạo 1 group mới trên Telegram
2. Thêm bot vào group
3. Gửi 1 tin nhắn bất kỳ trong group
4. Mở trình duyệt:
   ```
   https://api.telegram.org/bot<BOT_TOKEN>/getUpdates
   ```
5. Tìm `"chat":{"id":-100xxxxxxxxxx}` → đó là Chat ID

### Bước 3: Cấu hình trong Dashboard

1. Mở http://localhost:3000/telegram
2. Bấm **"Thêm cấu hình"**
3. Điền:
   - Tên: `Test Group`
   - Bot Token: (paste token từ BotFather)
   - Chat ID: (paste Chat ID vừa lấy)
   - ✅ Kích hoạt ngay
4. Bấm **"Tạo cấu hình"**
5. Bấm ⚡ (biểu tượng tia sét) để test kết nối

---

## 5. Tạo bộ lọc với điều kiện Ngày + SĐT

### Qua Dashboard (http://localhost:3000/filters)

1. Bấm **"Thêm bộ lọc"**
2. Điền:
   - **Tên bộ lọc:** `Test - HN CNTT 3 ngày`
   - **Tỉnh/Thành phố:** `Hà Nội`
   - **Từ khóa ngành nghề:** `công nghệ, phần mềm, lập trình`
   - **Mã ngành:** `6201, 6202`
   - **Ngày đăng ký:** `3` (lấy DN đăng ký trong 3 ngày qua)
   - ✅ **Chỉ lấy DN có số điện thoại**
   - **Gửi thông báo đến:** Chọn config Telegram vừa tạo
   - ✅ **Kích hoạt bộ lọc**
3. Bấm **"Tạo bộ lọc"**

### Qua API (curl)

```bash
curl -X POST http://localhost:8000/api/v1/filters \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"Test - HN CNTT 3 ngay\",\"provinces\":[\"Hà Nội\"],\"industry_keywords\":[\"công nghệ\",\"phần mềm\",\"lập trình\"],\"industry_codes\":[\"6201\",\"6202\"],\"registration_days_back\":3,\"require_phone\":true,\"is_active\":true,\"telegram_config_id\":1}"
```

---

## 6. Test toàn luồng end-to-end

### Bước 1: Đảm bảo Queue Worker đang chạy

```bash
# Kiểm tra worker đang chạy:
docker compose logs worker --tail=5

# Nếu dùng manual:
php artisan queue:work database --queue=notifications,default --sleep=2 --tries=3
```

### Bước 2: Chạy test FilterService (tinker)

```bash
docker compose exec app php artisan tinker
```

```php
use App\Models\Company;
use App\Services\FilterService;

$filterService = app(FilterService::class);

// Lấy DN có SĐT + đăng ký hôm nay
$company = Company::where('mst', '0109876543')->first();
$matches = $filterService->getMatchingFilters($company);
echo "DN có SĐT + ngày hôm nay: " . $matches->count() . " filter match\n";
// Mong đợi: >= 1 filter match

// Lấy DN không có SĐT
$company2 = Company::where('mst', '0109876544')->first();
$matches2 = $filterService->getMatchingFilters($company2);
echo "DN không có SĐT: " . $matches2->count() . " filter match\n";
// Mong đợi: 0 (bị loại vì require_phone)

// Lấy DN đăng ký 5 ngày trước
$company3 = Company::where('mst', '0109876545')->first();
$matches3 = $filterService->getMatchingFilters($company3);
echo "DN 5 ngày trước: " . $matches3->count() . " filter match\n";
// Mong đợi: 0 (bị loại vì quá 3 ngày)
```

### Bước 3: Dispatch Job thủ công (test PDF + Telegram)

```php
use App\Models\Company;
use App\Models\Filter;
use App\Jobs\ProcessCompanyNotification;

$company = Company::where('mst', '0109876543')->first();
$filter = Filter::where('is_active', true)->whereNotNull('telegram_config_id')->first();

// Dispatch job (queue worker sẽ xử lý)
ProcessCompanyNotification::dispatch($company, $filter);
echo "Job dispatched! Kiểm tra Telegram...\n";
```

### Bước 4: Kiểm tra kết quả

- ✅ Telegram group nhận được file PDF kèm caption
- ✅ PDF có 2 phần: "Thông tin chung" + "Danh mục ngành nghề"
- ✅ Company record có `notification_sent = true`

Kiểm tra trong DB:
```php
Company::where('mst', '0109876543')->value('notification_sent');
// Mong đợi: true
```

---

## 7. Xem logs & debug

### Logs chính

```bash
# Scraper log
docker compose exec app cat storage/logs/scraper.log

# Laravel log (errors, info)
docker compose exec app cat storage/logs/laravel.log

# Queue worker log
docker compose logs worker --tail=50

# Scheduler log
docker compose logs scheduler --tail=20
```

### Kiểm tra jobs trong queue

```bash
docker compose exec app php artisan tinker
```

```php
// Xem jobs đang chờ
DB::table('jobs')->count();

// Xem failed jobs
DB::table('failed_jobs')->get();

// Retry tất cả failed jobs
// (exit tinker trước)
```

```bash
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
```

### Kiểm tra PDF được tạo

```bash
docker compose exec app ls storage/app/pdfs/
```

---

## 8. Dọn dẹp & reset

### Reset DB hoàn toàn

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Xóa cache dedup (để scrape lại các MST đã xử lý)

```bash
docker compose exec app php artisan tinker
```

```php
// Xóa tất cả cache dedup
Illuminate\Support\Facades\Cache::flush();
echo "Cache cleared!\n";
```

### Tắt môi trường

```bash
docker compose down          # Tắt containers (giữ data)
docker compose down -v       # Tắt + xóa volumes (reset DB)
```

---

## Tóm tắt logic lọc mới

| Điều kiện | Ý nghĩa | Ví dụ |
|-----------|---------|-------|
| `registration_days_back = 3` | Chỉ lấy DN đăng ký từ ngày 27/06 đến 29/06 (nếu hôm nay là 29/06) | DN đăng ký 25/06 → bị loại |
| `require_phone = true` | DN phải có số điện thoại | DN phone = null → bị loại |
| Cả hai cùng bật | DN phải thỏa **cả hai** điều kiện + các filter khác (tỉnh, ngành) | Chỉ DN có SĐT + đăng ký 3 ngày gần nhất + đúng tỉnh + đúng ngành → mới gửi PDF |

---

## Checklist test nhanh

- [ ] Docker compose up chạy không lỗi
- [ ] API `http://localhost:8000/api/v1/companies/stats` trả về JSON
- [ ] Frontend `http://localhost:3000` hiển thị Dashboard
- [ ] Tạo được Telegram config + test kết nối thành công
- [ ] Tạo được Filter với điều kiện ngày + SĐT
- [ ] Fake data 3 DN (có SĐT/không SĐT/quá hạn ngày)
- [ ] FilterService match đúng 1 DN (có SĐT + trong 3 ngày)
- [ ] Dispatch job → nhận PDF trên Telegram
- [ ] PDF hiển thị đầy đủ 2 phần thông tin
