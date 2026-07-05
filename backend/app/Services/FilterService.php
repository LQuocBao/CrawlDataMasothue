<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Filter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service that evaluates scraped companies against user-defined filter rules.
 * Returns matched filter-company pairs for notification dispatch.
 */
class FilterService
{
    /**
     * Evaluate a company against all active filters.
     *
     * @return Collection<Filter> Filters that match this company
     */
    public function getMatchingFilters(Company $company): Collection
    {
        $activeFilters = Filter::where('is_active', true)
            ->whereNotNull('telegram_config_id')
            ->with('telegramConfig')
            ->get();

        return $activeFilters->filter(function (Filter $filter) use ($company) {
            return $this->matchesFilter($company, $filter);
        });
    }

    /**
     * Check if a company matches a specific filter's criteria.
     * All defined criteria must match (AND logic between categories).
     * Within a category (e.g., provinces), any match suffices (OR logic).
     */
    public function matchesFilter(Company $company, Filter $filter): bool
    {
        // Yêu cầu có số điện thoại: nếu bật, DN không có SĐT sẽ bị bỏ qua
        if ($filter->require_phone) {
            if (!$this->hasPhoneNumber($company)) {
                return false;
            }
        }

        // Lọc theo ngày đăng ký: chỉ lấy DN đăng ký trong N ngày gần nhất
        if ($filter->registration_days_back) {
            if (!$this->matchesRegistrationDate($company, $filter->registration_days_back)) {
                return false;
            }
        }

        // Province filter: company province must be in filter's province list
        if (!empty($filter->provinces)) {
            if (!$this->matchesProvince($company, $filter->provinces)) {
                return false;
            }
        }

        // Industry keyword filter: at least one keyword must appear in industries
        if (!empty($filter->industry_keywords)) {
            if (!$this->matchesIndustryKeywords($company, $filter->industry_keywords)) {
                return false;
            }
        }

        // Industry code filter: at least one code must match
        if (!empty($filter->industry_codes)) {
            if (!$this->matchesIndustryCodes($company, $filter->industry_codes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kiểm tra DN có số điện thoại hay không.
     */
    private function hasPhoneNumber(Company $company): bool
    {
        return !empty($company->phone) && trim($company->phone) !== '';
    }

    /**
     * Kiểm tra ngày đăng ký của DN có nằm trong khoảng N ngày gần nhất không.
     * VD: registration_days_back = 3, hôm nay 29/06 → chấp nhận DN đăng ký 27, 28, 29/06.
     */
    private function matchesRegistrationDate(Company $company, int $daysBack): bool
    {
        if (!$company->registration_date) {
            // Không có ngày đăng ký → dùng ngày scraped_at làm fallback
            $checkDate = $company->scraped_at ?? $company->created_at;
        } else {
            $checkDate = $company->registration_date;
        }

        if (!$checkDate) {
            return false;
        }

        $cutoffDate = now()->subDays($daysBack)->startOfDay();

        return $checkDate->gte($cutoffDate);
    }

    /**
     * Check province match (case-insensitive, accent-tolerant).
     */
    private function matchesProvince(Company $company, array $provinces): bool
    {
        if (!$company->province) {
            return false;
        }

        $companyProvince = Str::lower(trim($company->province));

        foreach ($provinces as $province) {
            $filterProvince = Str::lower(trim($province));

            if ($companyProvince === $filterProvince) {
                return true;
            }

            // Partial match for variations (e.g., "Hà Nội" vs "Ha Noi")
            if (Str::contains($companyProvince, $filterProvince) ||
                Str::contains($filterProvince, $companyProvince)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if any filter keyword appears in the company's industry descriptions.
     */
    private function matchesIndustryKeywords(Company $company, array $keywords): bool
    {
        $industries = $company->industries ?? [];

        if (empty($industries)) {
            return false;
        }

        foreach ($keywords as $keyword) {
            $keyword = Str::lower(trim($keyword));

            foreach ($industries as $industry) {
                $description = Str::lower($industry['description'] ?? '');

                if (Str::contains($description, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if any filter industry code matches the company's registered codes.
     */
    private function matchesIndustryCodes(Company $company, array $codes): bool
    {
        $industries = $company->industries ?? [];

        if (empty($industries)) {
            return false;
        }

        $companyCodes = array_column($industries, 'code');

        foreach ($codes as $code) {
            if (in_array((string) $code, $companyCodes, true)) {
                return true;
            }
        }

        return false;
    }
}
