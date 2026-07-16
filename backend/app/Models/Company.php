<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    /** Các giá trị hợp lệ cho cột source */
    public const SOURCE_MASOTHUE = 'masothue';
    public const SOURCE_TRAMASOTHUE = 'tramasothue';
    public const SOURCE_BOTH = 'both';

    protected $fillable = [
        'mst',
        'source',
        'name',
        'international_name',
        'short_name',
        'address',
        'province',
        'district',
        'representative',
        'representative_title',
        'phone',
        'registration_date',
        'operation_date',
        'status',
        'industries',
        'managing_tax_authority',
        'notification_sent',
        'scraped_at',
    ];

    protected $casts = [
        'industries' => 'array',
        'registration_date' => 'date',
        'operation_date' => 'date',
        'notification_sent' => 'boolean',
        'scraped_at' => 'datetime',
    ];

    /**
     * Lấy tên hiển thị nguồn dữ liệu (cho Telegram/PDF/Sheet).
     */
    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            self::SOURCE_MASOTHUE => 'masothue.com',
            self::SOURCE_TRAMASOTHUE => 'tramasothue.com.vn',
            self::SOURCE_BOTH => 'masothue.com + tramasothue.com.vn',
            default => $this->source ?? 'masothue.com',
        };
    }

    /**
     * Merge source khi DN trùng MST xuất hiện ở nguồn khác.
     * Trả về true nếu source thực sự thay đổi.
     */
    public function mergeSource(string $newSource): bool
    {
        // Đã là 'both' → không cần update
        if ($this->source === self::SOURCE_BOTH) {
            return false;
        }

        // Nguồn mới khác nguồn cũ → đánh dấu 'both'
        if ($this->source !== $newSource) {
            $this->source = self::SOURCE_BOTH;
            $this->save();
            return true;
        }

        return false;
    }
}
