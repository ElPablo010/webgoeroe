<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Haal een instellingen-groep op (gecached). Geeft $default terug wanneer
     * de groep nog niet bestaat — zo blijft de site werken vóór de eerste save.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            self::cacheKey($key),
            fn () => static::query()->where('key', $key)->first()?->value,
        );

        return $value ?? $default;
    }

    /**
     * Bewaar (of overschrijf) een instellingen-groep en wis de cache.
     */
    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::cacheKey($key));
    }

    private static function cacheKey(string $key): string
    {
        return "setting.{$key}";
    }
}
