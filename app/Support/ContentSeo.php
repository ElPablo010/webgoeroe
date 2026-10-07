<?php

namespace App\Support;

use App\Models\CaseStudy;
use App\Models\Post;
use App\Models\WebsiteMedia;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Support\Seo;

/**
 * SEO van de eigen content van deze site (cases en blog), bovenop de
 * site-basis van webgoeroe/core:
 *
 *   - meta-bundels + JSON-LD als Seo-macro's: Seo::fromCaseStudy($case),
 *     Seo::fromPost($post), Seo::fromBlogIndex(), Seo::fromCaseStudiesIndex();
 *   - /sitemap.xml: het cases-overzicht, elke case, het blogoverzicht, elk artikel;
 *   - /llms.txt: de secties "Cases" en "Artikels".
 *
 * Geregistreerd in AppServiceProvider::boot().
 */
class ContentSeo
{
    public static function register(): void
    {
        // Callables i.p.v. closures met self::: Macroable bindt een closure aan
        // de Seo-klasse, waardoor self:: daar naar Seo zelf zou wijzen (lus).
        Seo::macro('fromCaseStudy', [self::class, 'fromCaseStudy']);
        Seo::macro('fromPost', [self::class, 'fromPost']);
        Seo::macro('fromBlogIndex', [self::class, 'fromBlogIndex']);
        Seo::macro('fromCaseStudiesIndex', [self::class, 'fromCaseStudiesIndex']);

        Core::seo()
            ->sitemapSource(fn (): array => ContentSeo::sitemapEntries())
            ->llmsSection(fn (): array => ContentSeo::llmsLines());
    }

    /**
     * Meta-bundel + JSON-LD voor een case.
     *
     * @return array<string, mixed>
     */
    public static function fromCaseStudy(CaseStudy $case): array
    {
        $canonical = filled($case->canonical_url)
            ? $case->canonical_url
            : Seo::absoluteUrl('/cases/'.$case->slug);

        $title = filled($case->meta_title) ? $case->meta_title : $case->title;
        $description = filled($case->meta_description)
            ? $case->meta_description
            : (filled($case->excerpt) ? $case->excerpt : Seo::defaultDescription());

        [$image, $imageAlt, $width, $height] = self::image($case, $title);

        $node = array_filter([
            '@type' => 'WebPage',
            '@id' => $canonical.'#webpage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => Seo::baseUrl().'/#website'],
            'inLanguage' => 'nl-BE',
            'primaryImageOfPage' => $image
                ? ['@type' => 'ImageObject', 'url' => Seo::absoluteUrl($image)]
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
            'schema' => [$node],
        ];
    }

    /**
     * Meta-bundel + JSON-LD voor een blogartikel (Article-schema: publicatiedatum,
     * auteur, afbeelding).
     *
     * @return array<string, mixed>
     */
    public static function fromPost(Post $post): array
    {
        $canonical = filled($post->canonical_url)
            ? $post->canonical_url
            : Seo::absoluteUrl('/blog/'.$post->slug);

        $title = filled($post->meta_title) ? $post->meta_title : $post->title;
        $description = filled($post->meta_description)
            ? $post->meta_description
            : (filled($post->excerpt) ? $post->excerpt : Seo::defaultDescription());

        [$image, $imageAlt, $width, $height] = self::image($post, $title);

        $articleNode = array_filter([
            '@type' => 'Article',
            '@id' => $canonical.'#article',
            'url' => $canonical,
            'name' => $title,
            'headline' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => Seo::baseUrl().'/#website'],
            'inLanguage' => 'nl-BE',
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->author_name,
            ],
            'publisher' => ['@id' => Seo::baseUrl().'/#business'],
            'image' => $image
                ? ['@type' => 'ImageObject', 'url' => Seo::absoluteUrl($image)]
                : null,
        ], fn ($v) => filled($v));

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => filled($post->meta_robots) ? $post->meta_robots : 'index, follow',
            'image' => $image,
            'imageAlt' => $imageAlt,
            'imageWidth' => $width,
            'imageHeight' => $height,
            'type' => 'article',
            'schema' => [$articleNode],
        ];
    }

    /**
     * Meta-bundel voor het blogoverzicht.
     *
     * @return array<string, mixed>
     */
    public static function fromBlogIndex(): array
    {
        $canonical = Seo::absoluteUrl('/blog');

        return [
            'title' => 'Artikels — '.Seo::siteName(),
            'description' => 'Praktische inzichten over websites, AI-tools en digitale groei — voor ondernemers die slim willen werken.',
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'image' => Seo::defaultImage(),
            'imageAlt' => null,
            'imageWidth' => null,
            'imageHeight' => null,
            'type' => 'website',
            'schema' => [[
                '@type' => 'Blog',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => 'Artikels',
                'isPartOf' => ['@id' => Seo::baseUrl().'/#website'],
                'inLanguage' => 'nl-BE',
            ]],
        ];
    }

    /**
     * Meta-bundel voor het cases-overzicht.
     *
     * @return array<string, mixed>
     */
    public static function fromCaseStudiesIndex(): array
    {
        $canonical = Seo::absoluteUrl('/cases');

        return [
            'title' => 'Cases — '.Seo::siteName(),
            'description' => 'Ontdek hoe De Webgoeroe bedrijven helpt groeien online.',
            'canonical' => $canonical,
            'robots' => 'index, follow',
            'image' => Seo::defaultImage(),
            'imageAlt' => null,
            'imageWidth' => null,
            'imageHeight' => null,
            'type' => 'website',
            'schema' => [[
                '@type' => 'CollectionPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => 'Case studies',
                'isPartOf' => ['@id' => Seo::baseUrl().'/#website'],
                'inLanguage' => 'nl-BE',
            ]],
        ];
    }

    /**
     * Sitemap-regels na de pagina's: cases-overzicht, cases, blogoverzicht, artikels.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sitemapEntries(): array
    {
        $urls = [[
            'loc' => Seo::absoluteUrl('/cases'),
            'lastmod' => now(),
            'priority' => '0.7',
        ]];

        foreach (CaseStudy::query()->where('published', true)->get() as $case) {
            $urls[] = [
                'loc' => Seo::absoluteUrl('/cases/'.$case->slug),
                'lastmod' => $case->updated_at,
                'priority' => $case->is_cornerstone ? '0.8' : '0.7',
            ];
        }

        $urls[] = [
            'loc' => Seo::absoluteUrl('/blog'),
            'lastmod' => now(),
            'priority' => '0.7',
        ];

        foreach (Post::query()->where('published', true)->get() as $post) {
            $urls[] = [
                'loc' => Seo::absoluteUrl('/blog/'.$post->slug),
                'lastmod' => $post->updated_at,
                'priority' => $post->is_cornerstone ? '0.8' : '0.7',
            ];
        }

        return $urls;
    }

    /**
     * llms.txt-secties "Cases" en "Artikels". De core zet zelf een lege regel
     * achter het geheel, dus de laatste sectie eindigt zonder.
     *
     * @return array<int, string>
     */
    public static function llmsLines(): array
    {
        $lines = [];

        $cases = CaseStudy::query()
            ->where('published', true)
            ->orderByDesc('featured')
            ->orderByDesc('updated_at')
            ->get();

        if ($cases->isNotEmpty()) {
            $lines[] = '## Cases';
            foreach ($cases as $case) {
                $desc = filled($case->excerpt) ? ': '.$case->excerpt : '';
                $lines[] = '- ['.($case->meta_title ?: $case->title).']('.Seo::absoluteUrl('/cases/'.$case->slug).')'.$desc;
            }
            $lines[] = '';
        }

        $posts = Post::query()
            ->where('published', true)
            ->orderByDesc('published_at')
            ->get();

        if ($posts->isNotEmpty()) {
            $lines[] = '## Artikels';
            foreach ($posts as $post) {
                $desc = filled($post->excerpt) ? ': '.$post->excerpt : '';
                $lines[] = '- ['.($post->meta_title ?: $post->title).']('.Seo::absoluteUrl('/blog/'.$post->slug).')'.$desc;
            }
            $lines[] = '';
        }

        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * Deel-afbeelding van een case of artikel: SEO-afbeelding (met afmetingen
     * uit de mediabibliotheek) → cover → site-standaard.
     *
     * @return array{0: ?string, 1: ?string, 2: ?int, 3: ?int}
     */
    private static function image(CaseStudy|Post $model, string $title): array
    {
        if (filled($model->seo_image_url)) {
            $dimensions = WebsiteMedia::dimensionsForUrl($model->seo_image_url);

            return [
                $model->seo_image_url,
                filled($model->seo_image_alt) ? $model->seo_image_alt : $title,
                $dimensions['width'] ?? null,
                $dimensions['height'] ?? null,
            ];
        }

        if (filled($model->cover_url)) {
            return [$model->cover_url, filled($model->cover_alt) ? $model->cover_alt : $title, null, null];
        }

        return [Seo::defaultImage(), $title, null, null];
    }
}
