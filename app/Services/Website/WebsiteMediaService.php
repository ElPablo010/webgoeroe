<?php

namespace App\Services\Website;

use App\Models\WebsiteMedia;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

class WebsiteMediaService
{
    private const MAX_WIDTH = 2400;

    private const WEBP_QUALITY = 80;

    private const JPEG_QUALITY = 85;

    private const DISK = 'public';

    private const DIRECTORY = 'website-media';

    /** Harde bovengrens op een remote download (bytes). */
    private const MAX_DOWNLOAD_BYTES = 15 * 1024 * 1024;

    /**
     * Bovengrens op het aantal pixels (~12 MP, bv. 4000x3000).
     *
     * Een byte-limiet volstaat niet: een klein JPEG-bestand kan enorme afmetingen
     * hebben ("decompression bomb"). GD houdt een afbeelding onverpakt in het
     * geheugen (breedte x hoogte x 4 bytes), dus 10000x8000 zou ~320 MB vragen en
     * het PHP-geheugen op de server opblazen. We lezen daarom eerst de header en
     * weigeren te grote afmetingen mét een nette foutmelding i.p.v. een crash.
     */
    private const MAX_PIXELS = 12_000_000;

    private const DOWNLOAD_TIMEOUT = 15;

    private ImageManager $images;

    public function __construct()
    {
        $this->images = new ImageManager(new Driver);
    }

    public function store(UploadedFile $upload): WebsiteMedia
    {
        return $this->storeFromPath($upload->getRealPath(), $upload->getClientOriginalName());
    }

    /**
     * Haal een afbeelding op via een publieke http(s)-URL en zet ze in de library.
     *
     * De URL komt van buitenaf (een MCP-client kiest 'm), dus behandelen we 'm als
     * onvertrouwd: enkel http(s), enkel publieke IP's (geen localhost/privé-ranges
     * of cloud-metadata), een harde size-cap en een timeout. Redirects worden per
     * hop opnieuw gecontroleerd, zodat een publieke URL niet alsnog naar een intern
     * adres kan doorsturen.
     *
     * @throws InvalidArgumentException bij een geweigerde/onveilige URL
     * @throws RuntimeException als het downloaden of decoderen mislukt
     */
    public function storeFromUrl(string $url, ?string $filename = null): WebsiteMedia
    {
        $temporaryPath = $this->downloadToTemporaryFile($url);

        try {
            return $this->storeFromPath($temporaryPath, $filename ?? $this->filenameFromUrl($url));
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function storeFromPath(string $sourcePath, string $originalFilename): WebsiteMedia
    {
        $basename = (string) Str::ulid();
        $disk = Storage::disk(self::DISK);

        $this->assertWithinPixelBudget($sourcePath);

        try {
            $image = $this->images->decode($sourcePath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Het bestand is geen geldige afbeelding.', previous: $e);
        }

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
            'original_filename' => $originalFilename,
        ]);
    }

    /**
     * Lees enkel de afbeeldingsheader (goedkoop) en weiger te grote afmetingen
     * vóór GD het bestand uitpakt. Beschermt zowel uploads via de admin als
     * downloads via de MCP-tool.
     *
     * @throws RuntimeException
     */
    private function assertWithinPixelBudget(string $sourcePath): void
    {
        $info = @getimagesize($sourcePath);

        if ($info === false) {
            throw new RuntimeException('Het bestand is geen geldige afbeelding.');
        }

        [$width, $height] = $info;
        $megapixels = round(($width * $height) / 1_000_000, 1);

        if ($width * $height > self::MAX_PIXELS) {
            throw new RuntimeException(
                "De afbeelding is te groot: {$width}x{$height} ({$megapixels} MP). "
                .'Maximum is '.(self::MAX_PIXELS / 1_000_000).' MP — kies een kleinere bronafbeelding.'
            );
        }
    }

    private function downloadToTemporaryFile(string $url): string
    {
        $this->assertPubliclyFetchable($url);

        $temporaryPath = (string) tempnam(sys_get_temp_dir(), 'website-media-');

        try {
            $response = Http::timeout(self::DOWNLOAD_TIMEOUT)
                ->withOptions([
                    'sink' => $temporaryPath,
                    'allow_redirects' => [
                        'max' => 3,
                        'protocols' => ['http', 'https'],
                        'strict' => true,
                        'referer' => false,
                        // Elke redirect-hop opnieuw valideren: anders stuurt een
                        // publieke URL je alsnog naar een intern adres.
                        'on_redirect' => function ($request, $response, $uri): void {
                            $this->assertPubliclyFetchable((string) $uri);
                        },
                    ],
                ])
                ->get($url);
        } catch (ConnectionException $e) {
            @unlink($temporaryPath);
            throw new RuntimeException("Kon de afbeelding niet ophalen: {$e->getMessage()}", previous: $e);
        } catch (\Throwable $e) {
            @unlink($temporaryPath);
            throw $e instanceof InvalidArgumentException
                ? $e
                : new RuntimeException("Kon de afbeelding niet ophalen: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            @unlink($temporaryPath);
            throw new RuntimeException("De URL gaf HTTP {$response->status()} terug.");
        }

        $contentType = (string) $response->header('Content-Type');
        if ($contentType !== '' && ! Str::startsWith(strtolower($contentType), 'image/')) {
            @unlink($temporaryPath);
            throw new RuntimeException("De URL levert geen afbeelding op (Content-Type: {$contentType}).");
        }

        if (filesize($temporaryPath) > self::MAX_DOWNLOAD_BYTES) {
            @unlink($temporaryPath);
            throw new RuntimeException('De afbeelding is groter dan 15 MB.');
        }

        return $temporaryPath;
    }

    /**
     * Weiger alles wat geen publieke http(s)-bron is — de kern van de SSRF-afscherming.
     *
     * @throws InvalidArgumentException
     */
    private function assertPubliclyFetchable(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Enkel http(s)-URL\'s zijn toegestaan.');
        }

        if ($host === '') {
            throw new InvalidArgumentException('De URL bevat geen geldige hostnaam.');
        }

        foreach ($this->resolveHost($host) as $ip) {
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if ($isPublic === false) {
                throw new InvalidArgumentException(
                    "De URL wijst naar een niet-publiek adres ({$ip}) en wordt geweigerd."
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        // Al een letterlijk IP? Dan enkel dat controleren.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );

        if ($ips === []) {
            throw new InvalidArgumentException("De hostnaam '{$host}' kon niet worden opgezocht.");
        }

        return array_values(array_filter($ips));
    }

    private function filenameFromUrl(string $url): string
    {
        $name = basename((string) parse_url($url, PHP_URL_PATH));

        return $name !== '' && $name !== '/' ? $name : 'remote-image';
    }

    public function delete(WebsiteMedia $media): void
    {
        $disk = Storage::disk($media->disk);
        $disk->delete(array_filter([$media->path, $media->fallback_path]));
        $media->delete();
    }
}
