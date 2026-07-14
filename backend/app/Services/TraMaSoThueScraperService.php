<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Service bóc tách dữ liệu từ tramasothue.com.vn
 * Sử dụng Symfony DomCrawler để parse HTML.
 *
 * Structure:
 * - Listing page: các khối có text "Mới đăng ký" → lấy href
 * - Detail page: Alpine.js x-data attributes chứa dữ liệu DN
 */
class TraMaSoThueScraperService
{
    private const BASE_URL = 'https://tramasothue.com.vn';
    private const LISTING_PATH = '/doanh-nghiep-moi-dang-ky';

    /**
     * Fetch listing page, parse ra danh sách URL DN "Mới đăng ký".
     *
     * @return array<string> Full URLs of new company detail pages
     */
    public function fetchNewCompanyUrls(?string $proxy = null): array
    {
        $url = self::BASE_URL . self::LISTING_PATH;

        $response = $this->httpGet($url, $proxy);

        if (!$response || !$response->successful()) {
            Log::warning('TraMaSoThueScraperService: Listing page failed', [
                'url' => $url,
                'status' => $response?->status(),
            ]);
            return [];
        }

        return $this->parseListingPage($response->body());
    }

    /**
     * Parse listing page HTML → extract URLs DN mới đăng ký.
     *
     * Logic: Tìm các khối chứa text "Mới đăng ký", lấy href của thẻ <a>.
     */
    private function parseListingPage(string $html): array
    {
        $urls = [];

        try {
            $crawler = new Crawler($html);

            // Tìm các block chứa "Mới đăng ký"
            $crawler->filter('div.bg-white.rounded-xl')->each(function (Crawler $node) use (&$urls) {
                // Kiểm tra có badge "Mới đăng ký"
                $badge = $node->filter('span');
                $hasBadge = false;

                $badge->each(function (Crawler $span) use (&$hasBadge) {
                    if (str_contains($span->text(''), 'Mới đăng ký')) {
                        $hasBadge = true;
                    }
                });

                if (!$hasBadge) {
                    return;
                }

                // Lấy href từ thẻ <a>
                $link = $node->filter('a[href]');
                if ($link->count() > 0) {
                    $href = $link->first()->attr('href');
                    if ($href && str_starts_with($href, 'http')) {
                        $urls[] = $href;
                    } elseif ($href) {
                        $urls[] = self::BASE_URL . '/' . ltrim($href, '/');
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('TraMaSoThueScraperService: Parse listing failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return array_unique($urls);
    }

    /**
     * Scrape detail page → parse thông tin DN.
     *
     * @return array|null Company data hoặc null nếu parse thất bại
     */
    public function scrapeCompanyDetail(string $url, ?string $proxy = null): ?array
    {
        $response = $this->httpGet($url, $proxy);

        if (!$response || !$response->successful()) {
            Log::warning('TraMaSoThueScraperService: Detail page failed', [
                'url' => $url,
                'status' => $response?->status(),
            ]);
            return null;
        }

        return $this->parseDetailPage($response->body(), $url);
    }

    /**
     * Parse detail page HTML → structured company data.
     *
     * Trang dùng Alpine.js nên data nằm trong x-data attributes.
     */
    private function parseDetailPage(string $html, string $url): ?array
    {
        $data = [
            'mst' => null,
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
        ];

        try {
            $crawler = new Crawler($html);

            // MST: từ x-data="{ mst: '0402347844' }" hoặc x-text="mst"
            $data['mst'] = $this->extractXData($html, 'mst');

            // Tên DN: từ x-data="{ companyName: '...' }" hoặc h1
            $data['name'] = $this->extractXData($html, 'companyName');
            if (!$data['name']) {
                $h1 = $crawler->filter('h1');
                if ($h1->count() > 0) {
                    $data['name'] = trim($h1->first()->text(''));
                }
            }

            // SĐT: từ x-data="{ phone: '...' }"
            $data['phone'] = $this->extractXData($html, 'phone');

            // Địa chỉ: từ x-data="{ taxAddress: '...' }"
            $data['address'] = $this->extractXData($html, 'taxAddress');
            if (!$data['address']) {
                $data['address'] = $this->extractXData($html, 'address');
            }

            // Người đại diện: x-data="{ representative: '...' }"
            $data['representative'] = $this->extractXData($html, 'representative');
            if (!$data['representative']) {
                $data['representative'] = $this->extractXData($html, 'owner');
            }

            // Ngày hoạt động: x-data="{ activeDate: '...' }"
            $dateStr = $this->extractXData($html, 'activeDate');
            if (!$dateStr) {
                $dateStr = $this->extractXData($html, 'startDate');
            }
            if ($dateStr) {
                $data['operation_date'] = $this->parseDate($dateStr);
                $data['registration_date'] = $data['operation_date'];
            }

            // Cơ quan thuế: x-data="{ taxAuthority: '...' }"
            $data['managing_tax_authority'] = $this->extractXData($html, 'taxAuthority');

            // Loại hình DN
            $data['short_name'] = $this->extractXData($html, 'companyType');

            // Ngành nghề: ul.space-y-3 > li → span.w-12 (mã) + span.name-special (tên)
            $data['industries'] = $this->parseIndustries($crawler);

            // Extract province từ address
            if ($data['address']) {
                $data['province'] = $this->extractProvince($data['address']);
            }

            // Fallback MST từ URL nếu chưa có
            if (!$data['mst'] && preg_match('/\/(\d{10,14})-/', $url, $m)) {
                $data['mst'] = $m[1];
            }

            // Validate: phải có ít nhất MST
            if (!$data['mst']) {
                return null;
            }

            // Nếu không có tên, dùng tên từ URL
            if (!$data['name']) {
                $data['name'] = $this->extractNameFromUrl($url);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error('TraMaSoThueScraperService: Parse detail failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract value từ Alpine.js x-data attribute.
     * Pattern: x-data="{ key: 'value' }"
     */
    private function extractXData(string $html, string $key): ?string
    {
        // Pattern 1: x-data="{ key: 'value' }"
        $pattern = '/x-data="\{\s*' . preg_quote($key, '/') . ":\s*'([^']+)'/";
        if (preg_match($pattern, $html, $m)) {
            return trim($m[1]);
        }

        // Pattern 2: x-data='{ key: "value" }'
        $pattern2 = "/x-data='\{\s*" . preg_quote($key, '/') . ':\s*"([^"]+)"/';
        if (preg_match($pattern2, $html, $m)) {
            return trim($m[1]);
        }

        // Pattern 3: x-data="{ key: `value` }" (template literal)
        $pattern3 = '/x-data="\{\s*' . preg_quote($key, '/') . ':\s*`([^`]+)`/';
        if (preg_match($pattern3, $html, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Parse bảng ngành nghề từ DOM.
     * Structure: ul.space-y-3 > li > a > span.w-12 (code) + span.name-special (desc)
     */
    private function parseIndustries(Crawler $crawler): array
    {
        $industries = [];

        try {
            $crawler->filter('ul.space-y-3 > li')->each(function (Crawler $li) use (&$industries) {
                $codeNode = $li->filter('span.w-12');
                $nameNode = $li->filter('span.name-special');

                if ($codeNode->count() > 0 && $nameNode->count() > 0) {
                    $code = trim($codeNode->first()->text(''));
                    $description = trim($nameNode->first()->text(''));

                    if ($code && $description) {
                        $industries[] = [
                            'code' => $code,
                            'description' => $description,
                            'is_primary' => count($industries) === 0, // Ngành đầu tiên = chính
                        ];
                    }
                }
            });
        } catch (\Throwable $e) {
            // Silently fail — ngành nghề là optional
        }

        return $industries;
    }

    /**
     * Parse date string sang Y-m-d.
     */
    private function parseDate(string $dateStr): ?string
    {
        // Format: 14/07/2026
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $dateStr, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // Format: 2026-07-14 (already ISO)
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $dateStr, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract tỉnh/TP từ địa chỉ.
     */
    private function extractProvince(string $address): ?string
    {
        $parts = array_map('trim', explode(',', $address));
        $last = end($parts);

        if (str_contains($last, 'Việt Nam')) {
            $last = $parts[count($parts) - 2] ?? null;
        }

        if ($last) {
            return preg_replace('/^(TP\.?|Thành phố|Tỉnh)\s*/u', '', $last);
        }

        return null;
    }

    /**
     * Extract tên DN từ URL slug.
     */
    private function extractNameFromUrl(string $url): ?string
    {
        if (preg_match('/\/\d+-(.+)$/', parse_url($url, PHP_URL_PATH) ?? '', $m)) {
            $slug = str_replace('-', ' ', $m[1]);
            return mb_strtoupper($slug);
        }
        return null;
    }

    /**
     * HTTP GET với proxy và timeout.
     */
    private function httpGet(string $url, ?string $proxy = null): ?\Illuminate\Http\Client\Response
    {
        try {
            $client = Http::timeout(10)
                ->connectTimeout(10)
                ->withHeaders([
                    'User-Agent' => $this->getRandomUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'vi-VN,vi;q=0.9,en;q=0.7',
                ]);

            if ($proxy) {
                $client = $client->withOptions([
                    'proxy' => $proxy,
                    'verify' => true,
                ]);
            }

            return $client->get($url);
        } catch (\Throwable $e) {
            Log::warning('TraMaSoThueScraperService: HTTP failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Random User-Agent.
     */
    private function getRandomUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        ];

        return $agents[array_rand($agents)];
    }
}
