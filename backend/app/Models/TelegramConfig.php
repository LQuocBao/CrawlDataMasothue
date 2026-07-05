<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'bot_token',
        'chat_id',
        'is_active',
        'last_sent_at',
        'daily_send_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    /**
     * Hide sensitive data from JSON serialization by default.
     */
    protected $hidden = [
        'bot_token',
    ];

    public function filters(): HasMany
    {
        return $this->hasMany(Filter::class);
    }
}
