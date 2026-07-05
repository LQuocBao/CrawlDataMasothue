<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Debug command: xem masothue.com trả về gì.
 */
class DebugScrape extends Command
{
    protected $signature = 'debug:scrape {--url= : URL cụ thể} {--search= : Tìm text trong HTML}';
    protected $description = 'Debug: xem response từ masothue.com';

    public function handle(): int
    {
        $url = $this->option('url') ?: 'https://masothue.com/tra-cuu-ma-so-thue-doanh-nghiep-moi-thanh-lap/';

        $this->info("Fetching: {$url}");

        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
            ])
            ->get($url);

        $this->info("Status: {$response->status()}");
        $body = $response->body();
        $this->info("Body: " . strlen($body) . " bytes");
        $this->newLine();

        // Tìm tất cả href chứa số (MST patterns)
        // masothue.com dùng format: /0123456789 hoặc /0123456789-ten-cong-ty
        preg_match_all('/href=["\']\/(\d{10,14})(?:-[^"\']*)?["\']/', $body, $matches);
        $mstFromHref = array_unique($matches[1] ?? []);

        $this->info("Pattern 1 (href with MST): " . count($mstFromHref));
        foreach (array_slice($mstFromHref, 0, 5) as $m) {
            $this->line("  {$m}");
        }

        // Pattern 2: tìm trong text/class chứa MST
        preg_match_all('/(\d{10,14})/', $body, $allNumbers);
        $potentialMSTs = [];
        foreach (array_unique($allNumbers[1] ?? []) as $num) {
            // MST thường bắt đầu bằng 0 và có 10-14 chữ số
            if (preg_match('/^0\d{9,13}$/', $num)) {
                $potentialMSTs[] = $num;
            }
        }
        $this->info("Pattern 2 (10-14 digit numbers starting with 0): " . count($potentialMSTs));
        foreach (array_slice($potentialMSTs, 0, 10) as $m) {
            $this->line("  {$m}");
        }

        // Pattern 3: tìm link dạng /0123456789-slug
        preg_match_all('/href=["\']\/(\d{10})-([^"\']+)["\']/', $body, $slugMatches);
        $this->newLine();
        $this->info("Pattern 3 (MST-slug links): " . count($slugMatches[1] ?? []));
        foreach (array_slice($slugMatches[0] ?? [], 0, 5) as $m) {
            $this->line("  {$m}");
        }

        // Tìm khu vực table hoặc list có class liên quan
        if (preg_match_all('/<table[^>]*class="([^"]*)"/', $body, $tables)) {
            $this->newLine();
            $this->info("Tables found:");
            foreach ($tables[1] as $t) {
                $this->line("  class=\"{$t}\"");
            }
        }

        // Tìm section "tax-listing" hoặc "company"
        $search = $this->option('search') ?: 'tax-listing|company-list|mst-list|doanh-nghiep';
        if (preg_match_all("/({$search})/i", $body, $found)) {
            $this->newLine();
            $this->info("Keyword matches:");
            foreach (array_unique($found[1]) as $f) {
                $this->line("  {$f}");
            }
        }

        // In ra 1 đoạn HTML chứa MST nếu tìm thấy
        if (!empty($potentialMSTs)) {
            $firstMST = $potentialMSTs[0];
            $pos = strpos($body, $firstMST);
            if ($pos !== false) {
                $this->newLine();
                $this->info("Context around first MST ({$firstMST}):");
                $start = max(0, $pos - 200);
                $this->line(substr($body, $start, 600));
            }
        }

        return self::SUCCESS;
    }
}
