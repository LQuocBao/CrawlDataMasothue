<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Filter;
use App\Models\TelegramConfig;
use App\Services\PdfService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Command dùng để test toàn luồng: tạo DN mẫu → xuất PDF → gửi Telegram.
 * Chạy: php artisan test:send-pdf
 */
class TestSendPdf extends Command
{
    protected $signature = 'test:send-pdf {--skip-create : Bỏ qua tạo DN mẫu, dùng DN đã có}';
    protected $description = 'Test tạo DN mẫu AVIDA, xuất PDF và gửi Telegram';

    public function handle(PdfService $pdfService, TelegramService $telegramService): int
    {
        $this->info('=== BẮT ĐẦU TEST TOÀN LUỒNG ===');

        // Step 1: Tạo hoặc lấy DN mẫu
        $this->info('');
        $this->info('[1/4] Kiểm tra / Tạo DN mẫu AVIDA...');

        $company = Company::where('mst', '0111551034')->first();

        if (!$company && !$this->option('skip-create')) {
            $company = Company::create([
                'mst' => '0111551034',
                'name' => 'CÔNG TY CỔ PHẦN SẢN XUẤT THỰC PHẨM VÀ XUẤT NHẬP KHẨU AVIDA',
                'address' => 'Số 5, ngõ 160/57, phố Ngọc Trì, Phường Long Biên, TP Hà Nội, Việt Nam',
                'province' => 'Hà Nội',
                'district' => 'Long Biên',
                'representative' => 'NGUYỄN ĐÌNH VIỆT',
                'phone' => '035 5156836',
                'registration_date' => '2026-06-26',
                'operation_date' => '2026-06-26',
                'status' => 'Đang hoạt động',
                'short_name' => 'Công ty cổ phần ngoài NN',
                'managing_tax_authority' => 'Thuế cơ sở 11 thành phố Hà Nội',
                'industries' => [
                    ['code' => '0321', 'description' => 'Nuôi trồng thuỷ sản biển', 'is_primary' => false],
                    ['code' => '0322', 'description' => 'Nuôi trồng thuỷ sản nội địa', 'is_primary' => false],
                    ['code' => '1010', 'description' => 'Chế biến, bảo quản thịt và các sản phẩm từ thịt', 'is_primary' => false],
                    ['code' => '1020', 'description' => 'Chế biến, bảo quản thuỷ sản và các sản phẩm từ thuỷ sản', 'is_primary' => false],
                    ['code' => '1030', 'description' => 'Chế biến và bảo quản rau quả', 'is_primary' => false],
                    ['code' => '1050', 'description' => 'Chế biến sữa và các sản phẩm từ sữa', 'is_primary' => false],
                    ['code' => '1071', 'description' => 'Sản xuất các loại bánh từ bột', 'is_primary' => false],
                    ['code' => '1072', 'description' => 'Sản xuất đường', 'is_primary' => false],
                    ['code' => '1073', 'description' => 'Sản xuất ca cao, sôcôla và mứt kẹo', 'is_primary' => false],
                    ['code' => '1074', 'description' => 'Sản xuất mì ống, mỳ sợi và sản phẩm tương tự', 'is_primary' => false],
                    ['code' => '1075', 'description' => 'Sản xuất món ăn, thức ăn chế biến sẵn', 'is_primary' => false],
                    ['code' => '1076', 'description' => 'Sản xuất chè', 'is_primary' => false],
                    ['code' => '1077', 'description' => 'Sản xuất cà phê', 'is_primary' => false],
                    ['code' => '1079', 'description' => 'Sản xuất thực phẩm khác chưa được phân vào đâu', 'is_primary' => false],
                    ['code' => '1101', 'description' => 'Chưng, tinh cất và pha chế các loại rượu mạnh', 'is_primary' => false],
                    ['code' => '1102', 'description' => 'Sản xuất rượu vang', 'is_primary' => false],
                    ['code' => '1103', 'description' => 'Sản xuất bia và mạch nha ủ men bia', 'is_primary' => false],
                    ['code' => '1104', 'description' => 'Sản xuất đồ uống không cồn, nước khoáng', 'is_primary' => false],
                    ['code' => '1105', 'description' => 'Sản xuất đồ uống không cồn, nước khoáng', 'is_primary' => false],
                    ['code' => '4610', 'description' => 'Đại lý, môi giới, đấu giá', 'is_primary' => false],
                    ['code' => '4632', 'description' => 'Bán buôn thực phẩm', 'is_primary' => true],
                    ['code' => '4633', 'description' => 'Bán buôn đồ uống', 'is_primary' => false],
                    ['code' => '4721', 'description' => 'Bán lẻ lương thực trong các cửa hàng chuyên doanh', 'is_primary' => false],
                    ['code' => '4722', 'description' => 'Bán lẻ thực phẩm trong các cửa hàng chuyên doanh', 'is_primary' => false],
                    ['code' => '4723', 'description' => 'Bán lẻ đồ uống trong các cửa hàng chuyên doanh', 'is_primary' => false],
                    ['code' => '4932', 'description' => 'Vận tải hành khách đường bộ khác', 'is_primary' => false],
                    ['code' => '4933', 'description' => 'Vận tải hàng hóa bằng đường bộ', 'is_primary' => false],
                    ['code' => '8299', 'description' => 'Hoạt động dịch vụ hỗ trợ kinh doanh khác còn lại chưa được phân vào đâu', 'is_primary' => false],
                ],
                'scraped_at' => now(),
            ]);
            $this->info('  ✅ Đã tạo DN: ' . $company->name);
        } elseif ($company) {
            $this->info('  ✅ DN đã tồn tại: ' . $company->name);
        } else {
            $this->error('  ❌ Không tìm thấy DN và --skip-create được bật.');
            return self::FAILURE;
        }

        $this->info("  MST: {$company->mst}");
        $this->info("  Ngành nghề: " . count($company->industries ?? []) . " ngành");

        // Step 2: Xuất PDF
        $this->info('');
        $this->info('[2/4] Đang xuất PDF...');

        try {
            $pdfPath = $pdfService->generateCompanyPdf($company);
            $this->info("  ✅ PDF đã tạo: {$pdfPath}");
            $this->info("  📄 Kích thước: " . round(filesize($pdfPath) / 1024, 1) . " KB");
        } catch (\Throwable $e) {
            $this->error("  ❌ Lỗi xuất PDF: " . $e->getMessage());
            return self::FAILURE;
        }

        // Step 3: Kiểm tra Telegram config
        $this->info('');
        $this->info('[3/4] Kiểm tra cấu hình Telegram...');

        $telegramConfig = TelegramConfig::where('is_active', true)->first();

        if (!$telegramConfig) {
            $this->warn('  ⚠️  Không có Telegram config active.');
            $this->warn('  PDF đã xuất thành công tại: ' . $pdfPath);
            $this->warn('  Bạn cần tạo Telegram config trước khi gửi.');
            $this->info('');
            $this->info('=== KẾT QUẢ: PDF OK, TELEGRAM CHƯA CẤU HÌNH ===');
            return self::SUCCESS;
        }

        $this->info("  ✅ Config: {$telegramConfig->name}");
        $this->info("  Chat ID: {$telegramConfig->chat_id}");

        // Verify bot token
        $botInfo = $telegramService->verifyBotToken($telegramConfig->bot_token);
        if (!$botInfo) {
            $this->error('  ❌ Bot token không hợp lệ!');
            return self::FAILURE;
        }
        $this->info("  ✅ Bot: @{$botInfo['username']}");

        // Step 4: Gửi PDF qua Telegram
        $this->info('');
        $this->info('[4/4] Đang gửi PDF qua Telegram...');

        $sent = $telegramService->sendDocument($pdfPath, $telegramConfig, $company);

        if ($sent) {
            $this->info('  ✅ GỬI THÀNH CÔNG! Kiểm tra Telegram group.');
        } else {
            $this->error('  ❌ Gửi thất bại. Kiểm tra log.');
        }

        // Cleanup
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }

        $this->info('');
        $this->info('=== ' . ($sent ? 'HOÀN TẤT - TEST THÀNH CÔNG' : 'TEST THẤT BẠI') . ' ===');

        return $sent ? self::SUCCESS : self::FAILURE;
    }
}
