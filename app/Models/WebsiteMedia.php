<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'disk',
    'path',
    'url',
    'fallback_path',
    'fallback_url',
    'mime',
    'size_bytes',
    'width',
    'height',
    'original_filename',
])]
class WebsiteMedia extends Model
{
    protected $table = 'website_media';

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * Zoek de breedte/hoogte horend bij een afbeeldings-URL. Sectie-content
     * bewaart enkel de URL-string (geen FK), dus voor `width`/`height`-attributen
     * — die layout-shift (CLS) tegengaan — leiden we de dimensies af uit de
     * media-tabel. Resultaat wordt gecached zodat herhaalde lookups (bv. meerdere
     * afbeeldingen op één pagina) de DB niet telkens raken.
     *
     * @return array{width: int, height: int}|null
     */
    public static function dimensionsForUrl(?string $url): ?array
    {
        if (blank($url)) {
            return null;
        }

        return Cache::remember(
            'media-dimensions.'.md5($url),
            now()->addDay(),
            function () use ($url): ?array {
                $media = static::query()
                    ->where('url', $url)
                    ->orWhere('fallback_url', $url)
                    ->first();

                return $media && $media->width && $media->height
                    ? ['width' => $media->width, 'height' => $media->height]
                    : null;
            },
        );
    }
}
