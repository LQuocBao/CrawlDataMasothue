<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Filter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provinces',
        'industry_keywords',
        'industry_codes',
        'registration_days_back',
        'require_phone',
        'is_active',
        'telegram_config_id',
    ];

    protected $casts = [
        'provinces' => 'array',
        'industry_keywords' => 'array',
        'industry_codes' => 'array',
        'registration_days_back' => 'integer',
        'require_phone' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function telegramConfig(): BelongsTo
    {
        return $this->belongsTo(TelegramConfig::class);
    }
}
