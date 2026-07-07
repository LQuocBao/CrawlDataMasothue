<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;

/**
 * Service responsible for scraping newly registered company data
 * from masothue.com with rotating proxy support.
 */
class ScraperService
{
    /** Cache prefix for deduplication set */
    private const CACHE_PREFIX = 'scraped_mst:';

    /** Cache TTL for processed MSTs (30 days) */
    private const CACHE_TTL_SECONDS = 2592000;

    private array $proxies;
    private int $currentProxyIndex = 0;

    public function __construct()
    {
        $this->proxies = $this->loadProxies();
    }

    /**
     * Scrape the latest registered companies from masothue.com.
     *
     * @return array<Company> Newly discovered companies (not seen before)
     */
    public function scrapeLatest(): array
    {
        $newCompanies = [];

        try {
            // Fetch the listing page for newly registered companies
            $response = $this->buildHttpClient()
                ->get($this->getTargetUrl());

            if (!$response->successful()) {
                Log::warning('ScraperService: Failed to fetch listing page', [
                    'status' => $response->status(),
                ]);
                return [];
            }

            $html = $response->body();
            $companyPaths = $this->parseListingPage($html);

            Log::info('ScraperService: Found company links', ['count' => count($companyPaths)]);

            $limit = config('scraper.max_per_run', 50);
            $processed = 0;

            foreach ($companyPaths as $path) {
                if ($processed >= $limit) break;

                $mst = $this->extractMstFromPath($path);

                if (!$mst) {
                    continue;
                }

                if ($this->isDuplicate($mst)) {
                    Log::debug("ScraperService: Skipping duplicate MST {$mst}");
                    continue;
                }

                // Fetch individual company detail page using full path
                $companyData = $this->scrapeCompanyDetail($mst, $path);

                if ($companyData) {
                    $company = $this->storeCompany($companyData);
                    if ($company) {
                        $newCompanies[] = $company;
                        $this->markAsProcessed($mst);
                        $processed++;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('ScraperService: Scraping failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $newCompanies;
    }

    /**
     * Build HTTP client with proxy injection and browser-like headers.
     */
    private function buildHttpClient(): PendingRequest
    {
        $client = Http::timeout(30)
            ->connectTimeout(15)
            ->withHeaders([
                'User-Agent' => $this->getRandomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Cache-Control' => 'no-cache',
            ]);

        // Inject rotating proxy if available
        $proxy = $this->getNextProxy();
        if ($proxy) {
            $client = $client->withOptions([
                'proxy' => $proxy,
                'verify' => config('scraper.verify_ssl', true),
            ]);
        }

        return $client;
    }

    /**
     * Parse the listing page HTML to extract company MST codes and slugs.
     *
     * @return array<string> List of MST-slug paths found (e.g. "0319620664-cong-ty-tnhh-...")
     */
    private function parseListingPage(string $html): array
    {
        $paths = [];

        // Pattern: href='/0319620664-cong-ty-tnhh-...'
        preg_match_all(
            '/href=["\']\/(\d{10,14}-[^"\']+)["\']/',
            $html,
            $matches
        );

        if (!empty($matches[1])) {
            // Deduplicate by MST (first 10-14 digits)
            $seen = [];
            foreach ($matches[1] as $path) {
                $mst = $this->extractMstFromPath($path);
                if ($mst && !isset($seen[$mst])) {
                    $seen[$mst] = true;
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * Extract MST from a path like "0319620664-cong-ty-tnhh-..."
     */
    private function extractMstFromPath(string $path): ?string
    {
        if (preg_match('/^(\d{10}(?:-\d{3})?)/', $path, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Scrape detailed company information from the detail page.
     *
     * @param string $mst The tax ID
     * @param string $path Full URL path (e.g. "0319620664-cong-ty-tnhh-...")
     * @return array|null Parsed company data or null on failure
     */
    private function scrapeCompanyDetail(string $mst, string $path): ?array
    {
        try {
            $url = config('scraper.base_url', 'https://masothue.com') . "/{$path}";

            $response = $this->buildHttpClient()->get($url);

            if (!$response->successful()) {
                Log::warning("ScraperService: Failed to fetch detail for MST {$mst}", [
                    'status' => $response->status(),
                    'url' => $url,
                ]);
                return null;
            }

            return $this->parseCompanyDetail($response->body(), $mst);
        } catch (\Throwable $e) {
            Log::warning("ScraperService: Error scraping MST {$mst}", [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse company detail HTML into structured data.
     * Dựa trên HTML thực tế từ masothue.com (table-taxinfo structure).
     */
    private function parseCompanyDetail(string $html, string $mst): ?array
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
            'representative_title' => null,
            'phone' => null,
            'registration_date' => null,
            'operation_date' => null,
            'status' => 'active',
            'industries' => [],
            'managing_tax_authority' => null,
        ];

        // Parse company name from <title>: "0319620664 - COMPANY NAME - MaSoThue"
        if (preg_match('/<title>\d+\s*-\s*(.+?)\s*-\s*MaSoThue<\/title>/', $html, $m)) {
            $data['name'] = trim($m[1]);
        }

        // Parse table-taxinfo (main info table)
        if (preg_match('/<table[^>]*class="table-taxinfo"[^>]*>(.*?)<\/table>/s', $html, $table)) {
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $table[1], $rows);

            foreach ($rows[1] as $row) {
                preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cells);
                if (count($cells[1]) < 2) continue;

                $label = trim(strip_tags($cells[1][0]));
                $value = trim(strip_tags($cells[1][1]));

                if ($value === '' || $value === '---') continue;

                match (true) {
                    str_contains($label, 'Địa chỉ') && !str_contains($label, 'Thuế')
                        => $data['address'] = $value,
                    str_contains($label, 'Tình trạng')
                        => $data['status'] = $value,
                    str_contains($label, 'Tên quốc tế')
                        => $data['international_name'] = $value,
                    str_contains($label, 'Tên viết tắt')
                        => $data['short_name'] = $value,
                    str_contains($label, 'Người đại diện')
                        => $data['representative'] = $this->cleanRepresentative($value),
                    str_contains($label, 'Điện thoại')
                        => $data['phone'] = $this->cleanPhone($value),
                    str_contains($label, 'Ngày hoạt động')
                        => $data['operation_date'] = $this->parseDate($value),
                    str_contains($label, 'Quản lý bởi')
                        => $data['managing_tax_authority'] = $value,
                    str_contains($label, 'Loại hình')
                        => $data['short_name'] = $data['short_name'] ?: $value,
                    str_contains($label, 'Ngành nghề chính')
                        => $data['industries'] = $this->parseMainIndustry($value),
                    default => null,
                };
            }
        }

        // Extract province from address
        if ($data['address']) {
            $data['province'] = $this->extractProvince($data['address']);
            $data['district'] = $this->extractDistrict($data['address']);
        }

        // Parse full industry table if exists (separate from main table)
        $fullIndustries = $this->parseIndustries($html);
        if (!empty($fullIndustries)) {
            $data['industries'] = $fullIndustries;
        }

        // Use operation_date as registration_date if not set
        if (!$data['registration_date'] && $data['operation_date']) {
            $data['registration_date'] = $data['operation_date'];
        }

        // Validate minimum fields
        if (!$data['name'] || !$data['mst']) {
            return null;
        }

        return $data;
    }

    /**
     * Clean representative name (remove extra text like other companies they represent).
     */
    private function cleanRepresentative(string $value): string
    {
        // "NGUYỄN TẤN DANH Ngoài ra, NGUYỄN TẤN DANH còn đại diện..."
        $parts = preg_split('/\s*(Ngoài ra|còn đại diện|Xem thêm)/u', $value);
        return trim($parts[0] ?? $value);
    }

    /**
     * Clean phone number (remove "Ẩn số điện thoại" text).
     */
    private function cleanPhone(string $value): ?string
    {
        // "035 5156836 Ẩn số điện thoại" -> "035 5156836"
        if (str_contains($value, 'Bị ẩn')) {
            return null; // Phone is hidden
        }
        $clean = preg_replace('/\s*(Ẩn số điện thoại|Xem SĐT).*/u', '', $value);
        $clean = trim($clean);
        return $clean !== '' ? $clean : null;
    }

    /**
     * Parse date from various formats.
     */
    private function parseDate(string $value): ?string
    {
        // Format "2026-06-26" (already ISO)
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return $m[1];
        }
        // Format "26/06/2026" (Vietnamese)
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }

    /**
     * Parse the main industry text from the table cell.
     * Returns at least the primary industry.
     */
    private function parseMainIndustry(string $value): array
    {
        // "Bán buôn thực phẩmChi tiết: - Bán buôn thịt..."
        $mainName = preg_replace('/Chi tiết:.*/s', '', $value);
        $mainName = trim($mainName);

        if (empty($mainName)) {
            return [];
        }

        return [
            ['code' => '', 'description' => $mainName, 'is_primary' => true],
        ];
    }

    /**
     * Parse the industries/business activities from HTML.
     * Structure: <td><a>CODE</a></td><td><a>Description</a></td>
     *
     * @return array<array{code: string, description: string, is_primary: bool}>
     */
    private function parseIndustries(string $html): array
    {
        $industries = [];

        // Pattern: <td><a href="...">0321</a></td><td><a href="...">Nuôi trồng thuỷ sản biển</a></td>
        preg_match_all(
            '/<tr[^>]*>\s*<td[^>]*>\s*<a[^>]*>(\d{4})<\/a>\s*<\/td>\s*<td[^>]*>\s*<a[^>]*>([^<]+)<\/a>\s*<\/td>\s*<\/tr>/s',
            $html,
            $rows,
            PREG_SET_ORDER
        );

        foreach ($rows as $row) {
            $code = trim($row[1]);
            $desc = trim($row[2]);
            if ($desc && $code) {
                $industries[] = [
                    'code' => $code,
                    'description' => $desc,
                    'is_primary' => false,
                ];
            }
        }

        return $industries;
    }

    /**
     * Store a new company record in the database.
     */
    private function storeCompany(array $data): ?Company
    {
        try {
            return Company::create([
                ...$data,
                'scraped_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint violation = duplicate (race condition)
            if ($e->getCode() === '23000') {
                Log::debug("ScraperService: Duplicate MST caught by DB constraint", [
                    'mst' => $data['mst'],
                ]);
                return null;
            }
            throw $e;
        }
    }

    /**
     * Check if this MST has already been processed (Redis + DB fallback).
     */
    private function isDuplicate(string $mst): bool
    {
        // Level 1: Redis cache check (fast path)
        if (Cache::has(self::CACHE_PREFIX . $mst)) {
            return true;
        }

        // Level 2: Database check (handles cache eviction)
        $exists = Company::where('mst', $mst)->exists();

        if ($exists) {
            // Re-populate cache
            $this->markAsProcessed($mst);
        }

        return $exists;
    }

    /**
     * Mark an MST as processed in the cache layer.
     */
    private function markAsProcessed(string $mst): void
    {
        Cache::put(self::CACHE_PREFIX . $mst, true, self::CACHE_TTL_SECONDS);
    }

    /**
     * Get the next proxy from the rotation pool or TMProxy API.
     */
    private function getNextProxy(): ?string
    {
        $tmproxyKey = config('scraper.tmproxy_key');
        if ($tmproxyKey) {
            return $this->getTmproxy($tmproxyKey);
        }

        if (empty($this->proxies)) {
            return null;
        }

        $proxy = $this->proxies[$this->currentProxyIndex % count($this->proxies)];
        $this->currentProxyIndex++;

        return $proxy;
    }

    /**
     * Lấy IP từ TMProxy API
     */
    private function getTmproxy(string $apiKey): ?string
    {
        try {
            // Lấy proxy hiện tại
            $response = Http::post('https://tmproxy.com/api/proxy/get-current-proxy', [
                'api_key' => $apiKey
            ]);
            
            $data = $response->json();
            
            // Nếu lỗi hết hạn IP hoặc không có IP hiện tại, thì xin IP mới
            if (!isset($data['code']) || $data['code'] !== 0) {
                $response = Http::post('https://tmproxy.com/api/proxy/get-new-proxy', [
                    'api_key' => $apiKey
                ]);
                $data = $response->json();
            }

            if (isset($data['code']) && $data['code'] === 0 && !empty($data['data']['https'])) {
                return 'http://' . $data['data']['https'];
            }
            
            Log::warning('TMProxy API Error', ['response' => $data]);
        } catch (\Exception $e) {
            Log::error('TMProxy Fetch Error', ['error' => $e->getMessage()]);
        }
        
        return null;
    }

    /**
     * Load proxy list from configuration.
     *
     * @return array<string> Proxy URLs in format http://user:pass@host:port
     */
    private function loadProxies(): array
    {
        $proxyEndpoint = config('scraper.proxy_endpoint');

        // Single rotating proxy endpoint (most common setup)
        if ($proxyEndpoint) {
            return [$proxyEndpoint];
        }

        // Multiple static proxies from config
        $proxyList = config('scraper.proxy_list', []);

        return is_array($proxyList) ? $proxyList : [];
    }

    /**
     * Get the target URL for scraping new registrations.
     */
    private function getTargetUrl(): string
    {
        $baseUrl = config('scraper.base_url', 'https://masothue.com');
        return $baseUrl . '/';
    }

    /**
     * Extract province name from a full address string.
     */
    private function extractProvince(string $address): ?string
    {
        // Vietnamese addresses typically end with city/province
        $parts = array_map('trim', explode(',', $address));
        $lastPart = end($parts);

        // Common province prefixes
        $prefixes = ['Thành phố', 'Tỉnh', 'TP', 'TP.'];
        foreach ($prefixes as $prefix) {
            $lastPart = preg_replace("/^{$prefix}\s*/u", '', $lastPart);
        }

        return $lastPart ?: null;
    }

    /**
     * Extract district from address.
     */
    private function extractDistrict(string $address): ?string
    {
        $parts = array_map('trim', explode(',', $address));

        if (count($parts) >= 2) {
            $districtPart = $parts[count($parts) - 2];
            return trim($districtPart);
        }

        return null;
    }

    /**
     * Rotate through realistic browser User-Agent strings.
     */
    private function getRandomUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        ];

        return $agents[array_rand($agents)];
    }
}
