<?php

namespace App\Services\Website;

use App\Models\WebsiteMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class WebsiteMediaService
{
    private const MAX_WIDTH = 2400;
    private const WEBP_QUALITY = 80;
    private const JPEG_QUALITY = 85;
    private const DISK = 'public';
    private const DIRECTORY = 'website-media';

    private ImageManager $images;

    public function __construct()
    {
        $this->images = new ImageManager(new Driver());
    }

    public function store(UploadedFile $upload): WebsiteMedia
    {
        $basename = (string) Str::ulid();
        $disk = Storage::disk(self::DISK);

        $image = $this->images->decode($upload->getRealPath());

        if ($image->width() > self::MAX_WIDTH) {
            $image->scaleDown(width: self::MAX_WIDTH);
        }

        $webpPath = self::DIRECTORY.'/'.$basename.'.webp';
        $jpgPath = self::DIRECTORY.'/'.$basename.'.jpg';

        $disk->put($webpPath, (string) $image->encode(new WebpEncoder(quality: self::WEBP_QUALITY)));
        $disk->put($jpgPath, (string) $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY)));

        return WebsiteMedia::create([
            'disk' => self::DISK,
            'path' => $webpPath,
            'url' => '/storage/'.$webpPath,
            'fallback_path' => $jpgPath,
            'fallback_url' => '/storage/'.$jpgPath,
            'mime' => 'image/webp',
            'size_bytes' => $disk->size($webpPath),
            'width' => $image->width(),
            'height' => $image->height(),
            'original_filename' => $upload->getClientOriginalName(),
        ]);
    }

    public function delete(WebsiteMedia $media): void
    {
        $disk = Storage::disk($media->disk);
        $disk->delete(array_filter([$media->path, $media->fallback_path]));
        $media->delete();
    }
}
