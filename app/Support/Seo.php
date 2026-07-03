<?php

namespace App\Support;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Post;
use App\Models\WebsiteMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Centrale SEO/GEO-helper. Levert per pagina een genormaliseerde bundel
 * meta-velden (titel, beschrijving, canonical, robots, og-afbeelding, og-type)
 * plus de bijhorende JSON-LD-nodes, én de site-brede structured data
 * (LocalBusiness + WebSite, gevoed door de footer-instellingen).
 *
 * Eén waarheidsbron zodat de <head> (components/site/meta.blade.php) enkel nog
 * hoeft te renderen wat hier berekend is.
 *
 * TODO (per project): zet DEFAULT_IMAGE op een echte standaard-deelafbeelding en
 * defaultDescription() op een wervende sitebrede beschrijving (of lees ze uit
 * een Setting).
 */
class Seo
{
    public const LOCALE = 'nl_BE';

    public const TIMEZONE = 'Europe/Brussels';

    /** Standaard deel-afbeelding wanneer een pagina er zelf geen heeft. */
    public const DEFAULT_IMAGE = null;

    public static function siteName(): string
    {
        return (string) config('app.name');
    }

    public static function defaultDescription(): string
    {
        return '';
    }

    /**
     * Maak een relatieve URL absoluut (vereist voor og:image, canonical, JSON-LD).
     * Volledige URLs blijven ongemoeid.
     */
    public static function absoluteUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    /** De basis-URL van de site, zonder trailing slash. */
    public static function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Meta-bundel + JSON-LD voor een CMS-pagina.
     *
     * @return array<string, mixed>
     */
    public static function fromPage(Page $page): array
    {
        $canonical = filled($page->canonical_url)
            ? $page->canonical_url
            : self::absoluteUrl($page->is_homepage ? '/' : '/'.$page->slug);

        $title = filled($page->meta_title) ? $page->meta_title : $page->title;
        $description = filled($page->meta_description) ? $page->meta_description : self::defaultDescription();

        [$image, $imageAlt, $width, $height] = self::resolvePageImage($page);

        $faqNode = self::faqNodeFromSections($page->sections, $canonical);

        $node = array_filter([
            '@type' => 'WebPage',
            '@id' => $canonical.'#webpage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => self::baseUrl().'/#website'],
            'inLanguage' => 'nl-BE',
            'primaryImageOfPage' => $image
                ? ['@type' => 'ImageObject', 'url' => self::absoluteUrl($image)]
                : null,
        ], fn ($v) => filled($v));

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => filled($page->meta_robots) ? $page->meta_robots : 'index, follow',
            'image' => $image,
            'imageAlt' => $imageAlt,
            'imageWidth' => $width,
            'imageHeight' => $height,
            'type' => 'website',
            'schema' => array_values(array_filter([$node, $faqNode])),
        ];
    }

    /**
     * Meta-bundel + JSON-LD voor een case study.
     *
     * @return array<string, mixed>
     */
    public static function fromCaseStudy(CaseStudy $case): array
    {
        $canonical = filled($case->canonical_url)
            ? $case->canonical_url
            : self::absoluteUrl('/cases/'.$case->slug);

        $title = filled($case->meta_title) ? $case->meta_title : $case->title;
        $description = filled($case->meta_description)
            ? $case->meta_description
            : (filled($case->excerpt) ? $case->excerpt : self::defaultDescription());

        // SEO-afbeelding → cover-afbeelding → site-default
        if (filled($case->seo_image_url)) {
            $dimensions = WebsiteMedia::dimensionsForUrl($case->seo_image_url);
            $image = $case->seo_image_url;
            $imageAlt = filled($case->seo_image_alt) ? $case->seo_image_alt : $title;
            $width = $dimensions['width'] ?? null;
            $height = $dimensions['height'] ?? null;
        } elseif (filled($case->cover_url)) {
            $image = $case->cover_url;
            $imageAlt = filled($case->cover_alt) ? $case->cover_alt : $title;
            $width = null;
            $height = null;
        } else {
            $image = self::DEFAULT_IMAGE;
            $imageAlt = $title;
            $width = null;
            $height = null;
        }

        $faqNode = null; // case studies use fixed content structure, no FAQ sections

        $node = array_filter([
            '@type' => 'WebPage',
            '@id' => $canonical.'#webpage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => self::baseUrl().'/#website'],
            'inLanguage' => 'nl-BE',
            'primaryImageOfPage' => $image
                ? ['@type' => 'ImageObject', 'url' => self::absoluteUrl($image)]
                : null,
            'about' => filled($case->client)
                ? ['@type' => 'Organization', 'name' => $case->client]
                : null,
        ], fn ($v) => filled($v));

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => filled($case->meta_robots) ? $case->meta_robots : 'index, follow',
            'image' => $image,
            'imageAlt' => $imageAlt,
            'imageWidth' => $width,
            'imageHeight' => $height,
            'type' => 'website',
            'schema' => array_values(array_filter([$node, $faqNode])),
        ];
    }

    /**
     * Meta-bundel + JSON-LD voor een blogbericht. Gebruikt Article-schema voor
     * betere rich results in Google (publicatiedatum, auteur, afbeelding).
     *
     * @return array<string, mixed>
     */
    public static function fromPost(Post $post): array
    {
        $canonical = filled($post->canonical_url)
            ? $post->canonical_url
            : self::absoluteUrl('/blog/'.$post->slug);

        $title = filled($post->meta_title) ? $post->meta_title : $post->title;
        $description = filled($post->meta_description)
            ? $post->meta_description
            : (filled($post->excerpt) ? $post->excerpt : self::defaultDescription());

        if (filled($post->seo_image_url)) {
            $dimensions = WebsiteMedia::dimensionsForUrl($post->seo_image_url);
            $image    = $post->seo_image_url;
            $imageAlt = filled($post->seo_image_alt) ? $post->seo_image_alt : $title;
            $width    = $dimensions['width'] ?? null;
            $height   = $dimensions['height'] ?? null;
        } elseif (filled($post->cover_url)) {
            $image    = $post->cover_url;
            $imageAlt = filled($post->cover_alt) ? $post->cover_alt : $title;
            $width    = null;
            $height   = null;
        } else {
            $image    = self::DEFAULT_IMAGE;
            $imageAlt = $title;
            $width    = null;
            $height   = null;
        }

        $articleNode = array_filter([
            '@type'            => 'Article',
            '@id'              => $canonical.'#article',
            'url'              => $canonical,
            'name'             => $title,
            'headline'         => $title,
            'description'      => $description,
            'isPartOf'         => ['@id' => self::baseUrl().'/#website'],
            'inLanguage'       => 'nl-BE',
            'datePublished'    => $post->published_at?->toAtomString(),
            'dateModified'     => $post->updated_at?->toAtomString(),
            'author'           => [
                '@type' => 'Person',
                'name'  => $post->author_name,
            ],
            'publisher'        => ['@id' => self::baseUrl().'/#business'],
            'image'            => $image
                ? ['@type' => 'ImageObject', 'url' => self::absoluteUrl($image)]
                : null,
        ], fn ($v) => filled($v));

        return [
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'robots'      => filled($post->meta_robots) ? $post->meta_robots : 'index, follow',
            'image'       => $image,
            'imageAlt'    => $imageAlt,
            'imageWidth'  => $width,
            'imageHeight' => $height,
            'type'        => 'article',
            'schema'      => [$articleNode],
        ];
    }

    /**
     * Statische meta-bundel voor de blog-overzichtspagina.
     *
     * @return array<string, mixed>
     */
    public static function fromBlogIndex(): array
    {
        $canonical = self::absoluteUrl('/blog');

        return [
            'title'       => 'Artikels — '.self::siteName(),
            'description' => 'Praktische inzichten over websites, AI-tools en digitale groei — voor ondernemers die slim willen werken.',
            'canonical'   => $canonical,
            'robots'      => 'index, follow',
            'image'       => self::DEFAULT_IMAGE,
            'imageAlt'    => null,
            'imageWidth'  => null,
            'imageHeight' => null,
            'type'        => 'website',
            'schema'      => [[
                '@type'      => 'Blog',
                '@id'        => $canonical.'#webpage',
                'url'        => $canonical,
                'name'       => 'Artikels',
                'isPartOf'   => ['@id' => self::baseUrl().'/#website'],
                'inLanguage' => 'nl-BE',
            ]],
        ];
    }

    /**
     * Statische meta-bundel voor de case-studies overzichtspagina.
     *
     * @return array<string, mixed>
     */
    public static function fromCaseStudiesIndex(): array
    {
        $canonical = self::absoluteUrl('/cases');

        return [
            'title' => 'Cases — '.self::siteName(),
            'description' => 'Ontdek hoe De Webgoeroe bedrijven helpt groeien online.',
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'image' => self::DEFAULT_IMAGE,
            'imageAlt' => null,
            'imageWidth' => null,
            'imageHeight' => null,
            'type' => 'website',
            'schema' => [[
                '@type' => 'CollectionPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => 'Case studies',
                'isPartOf' => ['@id' => self::baseUrl().'/#website'],
                'inLanguage' => 'nl-BE',
            ]],
        ];
    }

    /**
     * Site-brede JSON-LD die op élke pagina meegaat: het bedrijf (LocalBusiness)
     * en de website zelf. Gevoed door de footer-instellingen zodat NAP-gegevens
     * (naam, adres, telefoon, e-mail, social) één bron hebben.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function globalGraph(): array
    {
        $footer = SiteFooter::current();
        $contact = $footer['contact'] ?? [];
        $brand = $footer['brand'] ?? [];
        $social = $footer['social'] ?? [];
        $base = self::baseUrl();

        $sameAs = array_values(array_filter([
            $social['facebook'] ?? null,
            $social['instagram'] ?? null,
            $social['youtube'] ?? null,
        ], fn ($v) => filled($v)));

        $business = array_filter([
            '@type' => 'LocalBusiness',
            '@id' => $base.'/#business',
            'name' => $brand['name'] ?? self::siteName(),
            'url' => $base.'/',
            'logo' => self::absoluteUrl($brand['logo'] ?? null),
            'telephone' => $contact['phone'] ?? null,
            'email' => $contact['email'] ?? null,
            'address' => self::parseAddress($contact['address'] ?? ''),
            'sameAs' => $sameAs !== [] ? $sameAs : null,
        ], fn ($v) => filled($v));

        $website = [
            '@type' => 'WebSite',
            '@id' => $base.'/#website',
            'url' => $base.'/',
            'name' => self::siteName(),
            'inLanguage' => 'nl-BE',
            'publisher' => ['@id' => $base.'/#business'],
        ];

        return [$business, $website];
    }

    /**
     * Encodeer een lijst JSON-LD-nodes tot een schema.org-document. Bewust hier
     * (niet inline in Blade): de letterlijke string "@context" zou anders door de
     * Blade-compiler als de @context-directive verwerkt worden.
     *
     * @param  array<int, array<string, mixed>>  $graph
     */
    public static function jsonLd(array $graph): string
    {
        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values($graph)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Bouw een FAQPage-node uit alle `faq`-secties van een collectie. Sterk voor
     * zowel rich results als GEO. Antwoorden worden tot platte tekst herleid.
     *
     * @return array<string, mixed>|null
     */
    private static function faqNodeFromSections(Collection $sections, string $canonical): ?array
    {
        $questions = [];

        foreach ($sections->where('section_type', 'faq') as $section) {
            foreach ($section->content['items'] ?? [] as $item) {
                $question = trim((string) ($item['question'] ?? ''));
                $answer = Str::of($item['answer'] ?? '')->stripTags()->squish()->value();

                if ($question !== '' && $answer !== '') {
                    $questions[] = [
                        '@type' => 'Question',
                        'name' => $question,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                    ];
                }
            }
        }

        if ($questions === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => $canonical.'#faq',
            'mainEntity' => $questions,
        ];
    }

    /**
     * Resolve de deel-afbeelding van een pagina: expliciete SEO-afbeelding →
     * eerste hero-afbeelding → site-default. De SEO-afbeelding is een URL-string
     * (zoals alle media-velden); dimensies leiden we af uit de media-tabel.
     *
     * @return array{0: ?string, 1: ?string, 2: ?int, 3: ?int}
     */
    private static function resolvePageImage(Page $page): array
    {
        if (filled($page->seo_image_url)) {
            $dimensions = WebsiteMedia::dimensionsForUrl($page->seo_image_url);

            return [
                $page->seo_image_url,
                filled($page->seo_image_alt) ? $page->seo_image_alt : $page->title,
                $dimensions['width'] ?? null,
                $dimensions['height'] ?? null,
            ];
        }

        $hero = $page->sections->firstWhere('section_type', 'hero');
        $heroSrc = $hero?->content['image']['src'] ?? null;

        if (filled($heroSrc)) {
            return [$heroSrc, $hero->content['image']['alt'] ?? $page->title, null, null];
        }

        return [self::DEFAULT_IMAGE, $page->title, null, null];
    }

    /**
     * Parse het meerregelige footer-adres naar een schema.org PostalAddress.
     * Verwacht "straat + nr" op regel 1 en "postcode gemeente" op regel 2.
     *
     * @return array<string, string>|null
     */
    private static function parseAddress(string $raw): ?array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));

        if ($lines === []) {
            return null;
        }

        $address = [
            '@type' => 'PostalAddress',
            'streetAddress' => $lines[0],
            'addressCountry' => 'BE',
        ];

        if (isset($lines[1])) {
            if (preg_match('/^(\d{4})\s+(.+)$/', $lines[1], $m)) {
                $address['postalCode'] = $m[1];
                $address['addressLocality'] = $m[2];
            } else {
                $address['addressLocality'] = $lines[1];
            }
        }

        return $address;
    }
}
