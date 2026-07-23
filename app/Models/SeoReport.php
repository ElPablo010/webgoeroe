<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'captured_at',
        'period',
        'metrics',
        'advice',
        'emailed',
    ];

    protected $casts = [
        'captured_at' => 'date',
        'metrics' => 'array',
        'emailed' => 'boolean',
    ];
}
