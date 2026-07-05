<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'mst',
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
}
