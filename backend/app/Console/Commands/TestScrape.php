<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\TelegramConfig;
use App\Services\PdfService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Command test: scrape DN mới từ masothue.com → tạo PDF → gửi Telegram.
 * Chạy inline (không qua ScraperService) để dễ debug.
 */
class TestScrape extends Command
{
    protected $signature = 'test:scrape
                            {--limit=2 : Số DN tối đa}
                            {--skip-telegram : Không gửi Telegram}
                            {--skip-pdf : Không tạo PDF}';

    protected $description = 'Test scraper: lấy DN mới từ masothue.com, tạo PDF, gửi Telegram';

    public function handle(PdfService $pdfService, TelegramService $telegramService): int
    {
        $limit = (int) $this->option('limit');
        $skipTelegram = $this->option('skip-telegram');
        $skipPdf = $this->option('skip-pdf');

        $this->info("=== TEST SCRAPER ===");
        $this->newLine();

        // Step 1: Fetch listing page
        $this->info("[1] Fetching listing page...");
        $listUrl = 'https://masothue.com/tra-cuu-ma-so-thue-doanh-nghiep-moi-thanh-lap/';

        $response = Http::timeout(30)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language' => 'vi-VN,vi;q=0.9',
        ])->get($listUrl);

        if (!$response->successful()) {
            $this->error("Listing page failed: HTTP {$response->status()}");
            return self::FAILURE;
        }

        $this->info("  Status: {$response->status()} | Size: " . strlen($response->body()) . " bytes");

        // Step 2: Parse MST-slug links
        preg_match_all('/href=["\']\/(\d{10,14}-[^"\']+)["\']/', $response->body(), $matches);
        $paths = [];
        $seen = [];
        foreach ($matches[1] ?? [] as $path) {
            if (preg_match('/^(\d{10,14})/', $path, $m)) {
                $mst = $m[1];
                if (!isset($seen[$mst])) {
                    $seen[$mst] = true;
                    $paths[] = ['mst' => $mst, 'path' => $path];
                }
            }
        }

        $this->info("  Found: " . count($paths) . " unique companies on page");

        if (empty($paths)) {
            $this->error("Không tìm thấy DN nào trên listing page!");
            return self::FAILURE;
        }

        // Lọc bỏ DN đã có trong DB
        $existingMsts = Company::whereIn('mst', array_column($paths, 'mst'))->pluck('mst')->toArray();
        $newPaths = array_filter($paths, fn($p) => !in_array($p['mst'], $existingMsts));

        $this->info("  New (not in DB): " . count($newPaths) . " | Already in DB: " . count($existingMsts));

        if (empty($newPaths)) {
            $this->warn("Tất cả DN trên page đều đã có trong DB. Thử xóa DB hoặc đợi DN mới.");
            // Dùng DN đầu tiên từ page để test luồng
            $this->info("  → Dùng DN đầu tiên để test luồng (dù đã có)...");
            $newPaths = [array_values($paths)[0]];
        }

        $newPaths = array_slice(array_values($newPaths), 0, $limit);
        $this->newLine();

        // Step 3: Scrape detail + tạo PDF + gửi Telegram
        $telegramConfig = TelegramConfig::where('is_active', true)->first();

        foreach ($newPaths as $index => $item) {
            $num = $index + 1;
            $mst = $item['mst'];
            $path = $item['path'];

            $this->info("[DN {$num}] Đang scrape: {$mst}");
            $this->line("  URL: https://masothue.com/{$path}");

            // Fetch detail page
            sleep(2); // Delay tránh block

            $detailResponse = Http::timeout(30)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept-Language' => 'vi-VN,vi;q=0.9',
            ])->get("https://masothue.com/{$path}");

            if (!$detailResponse->successful()) {
                $this->error("  ✗ Detail page failed: HTTP {$detailResponse->status()}");
                continue;
            }

            $html = $detailResponse->body();

            // Parse basic info from table-taxinfo
            $companyData = $this->parseDetailPage($html, $mst);

            if (!$companyData) {
                $this->error("  ✗ Không parse được thông tin DN");
                continue;
            }

            $this->info("  ✓ {$companyData['name']}");
            $this->line("    Địa chỉ: {$companyData['address']}");
            $this->line("    SĐT: " . ($companyData['phone'] ?: 'Không có'));
            $this->line("    Đại diện: " . ($companyData['representative'] ?: 'N/A'));
            $this->line("    Ngành: " . count($companyData['industries']) . " ngành");

            // Điều kiện tiên quyết: DN phải có SĐT
            if (empty($companyData['phone'])) {
                $this->line("  → BỎ QUA (không có SĐT)");
                $this->newLine();
                // Vẫn lưu DB nhưng không gửi thông báo
                Company::updateOrCreate(['mst' => $mst], $companyData);
                continue;
            }

            // Save to DB
            $company = Company::updateOrCreate(
                ['mst' => $mst],
                $companyData
            );

            if ($skipPdf) {
                $this->line("  → Bỏ qua PDF");
                $this->newLine();
                continue;
            }

            // Tạo PDF
            $this->line("  → Tạo PDF...");
            try {
                $pdfPath = $pdfService->generateCompanyPdf($company);
                $this->info("  ✓ PDF: {$pdfPath}");
            } catch (\Throwable $e) {
                $this->error("  ✗ Lỗi PDF: " . $e->getMessage());
                continue;
            }

            if ($skipTelegram || !$telegramConfig) {
                $this->line("  → Bỏ qua Telegram");
                $pdfService->cleanup($pdfPath);
                $this->newLine();
                continue;
            }

            // Gửi Telegram
            $this->line("  → Gửi Telegram...");
            try {
                $sent = $telegramService->sendDocument($pdfPath, $telegramConfig, $company);
                if ($sent) {
                    $this->info("  ✓ Đã gửi Telegram!");
                    $company->update(['notification_sent' => true]);
                } else {
                    $this->error("  ✗ Gửi thất bại");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ Lỗi: " . $e->getMessage());
            }

            $pdfService->cleanup($pdfPath);
            $this->newLine();
        }

        $this->info("=== HOÀN TẤT ===");
        return self::SUCCESS;
    }

    /**
     * Parse detail page HTML into company data array.
     */
    private function parseDetailPage(string $html, string $mst): ?array
    {
        $data = [
            'mst' => $mst,
            'name' => null,
            'international_name' => null,
            'short_name' => null,
            'address' => null,
            'province' => null,
            'district' => null,
            'representative' => null,
            'phone' => null,
            'registration_date' => null,
            'operation_date' => null,
            'status' => 'Đang hoạt động',
            'industries' => [],
            'managing_tax_authority' => null,
            'scraped_at' => now(),
        ];

        // Name from title
        if (preg_match('/<title>\d+\s*-\s*(.+?)\s*-\s*MaSoThue<\/title>/', $html, $m)) {
            $data['name'] = trim($m[1]);
        }

        // Parse table-taxinfo
        if (preg_match('/<table[^>]*class="table-taxinfo"[^>]*>(.*?)<\/table>/s', $html, $table)) {
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $table[1], $rows);

            foreach ($rows[1] as $row) {
                preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cells);
                if (count($cells[1]) < 2) continue;

                $label = trim(strip_tags($cells[1][0]));
                $rawValue = $cells[1][1];
                $value = trim(strip_tags($rawValue));

                if ($value === '' || $value === '---') continue;

                if (str_contains($label, 'Địa chỉ') && !str_contains($label, 'Thuế')) {
                    $data['address'] = $value;
                } elseif (str_contains($label, 'Tình trạng')) {
                    $data['status'] = $value;
                } elseif (str_contains($label, 'Tên quốc tế')) {
                    $data['international_name'] = $value;
                } elseif (str_contains($label, 'Tên viết tắt')) {
                    $data['short_name'] = $value;
                } elseif (str_contains($label, 'Người đại diện')) {
                    // Clean: "NGUYỄN VĂN A Ngoài ra..." → "NGUYỄN VĂN A"
                    $parts = preg_split('/\s*(Ngoài ra|còn đại diện)/u', $value);
                    $data['representative'] = trim($parts[0] ?? $value);
                } elseif (str_contains($label, 'Điện thoại')) {
                    if (!str_contains($value, 'Bị ẩn')) {
                        $data['phone'] = preg_replace('/\s*(Ẩn số|Xem SĐT).*/u', '', $value);
                        $data['phone'] = trim($data['phone']) ?: null;
                    }
                } elseif (str_contains($label, 'Ngày hoạt động')) {
                    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $value, $dm)) {
                        $data['operation_date'] = $dm[1];
                        $data['registration_date'] = $dm[1];
                    }
                } elseif (str_contains($label, 'Quản lý')) {
                    $data['managing_tax_authority'] = $value;
                } elseif (str_contains($label, 'Loại hình')) {
                    $data['short_name'] = $data['short_name'] ?: $value;
                }
            }
        }

        // Province from address
        if ($data['address']) {
            $parts = array_map('trim', explode(',', $data['address']));
            $data['province'] = end($parts) !== 'Việt Nam'
                ? end($parts)
                : ($parts[count($parts) - 2] ?? null);
            // Remove "Việt Nam", "TP", "Thành phố" prefix
            if ($data['province']) {
                $data['province'] = preg_replace('/^(TP|Thành phố|Tỉnh)\s*/u', '', $data['province']);
            }
        }

        // Industries: mã ngành nằm trong <a> bên trong <td>
        // Pattern: <td><a ...>CODE</a></td><td><a ...>Description</a></td>
        preg_match_all(
            '/<tr[^>]*>\s*<td[^>]*>\s*<a[^>]*>(\d{4})<\/a>\s*<\/td>\s*<td[^>]*>\s*<a[^>]*>([^<]+)<\/a>\s*<\/td>\s*<\/tr>/s',
            $html,
            $indRows,
            PREG_SET_ORDER
        );
        foreach ($indRows as $row) {
            $code = trim($row[1]);
            $desc = trim($row[2]);
            if ($desc && $code) {
                $data['industries'][] = [
                    'code' => $code,
                    'description' => $desc,
                    'is_primary' => false,
                ];
            }
        }

        // Mark primary industry (from "Ngành nghề chính" field)
        if (!empty($data['industries'])) {
            // Tìm ngành chính từ thông tin đã parse trước đó
            $mainIndustryName = '';
            if (preg_match('/<table[^>]*class="table-taxinfo"[^>]*>.*?Ngành nghề chính.*?<td[^>]*>(.*?)<\/td>/s', $html, $mainMatch)) {
                $mainIndustryName = trim(strip_tags(preg_replace('/Chi tiết:.*/s', '', $mainMatch[1])));
            }
            if ($mainIndustryName) {
                foreach ($data['industries'] as &$ind) {
                    if (str_contains($mainIndustryName, $ind['description']) || str_contains($ind['description'], $mainIndustryName)) {
                        $ind['is_primary'] = true;
                        break;
                    }
                }
                unset($ind);
            }
        }

        if (!$data['name']) return null;

        return $data;
    }
}
