<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Post;
use App\Support\Seo;
use App\Support\SiteFooter;
use Illuminate\Http\Response;

/**
 * Dynamische SEO/GEO-assets die de live database weerspiegelen:
 * - /sitemap.xml  — alle publieke pagina's
 * - /robots.txt   — verwijst naar de sitemap met de juiste host per omgeving
 * - /llms.txt     — gestructureerd overzicht voor AI-zoekmachines (GEO)
 *
 * Breid sitemap()/llms() per project uit met domein-content (events, producten…).
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [];

        // Pagina's: homepage altijd mee (ongeacht published-vlag, net als de
        // publieke router), plus alle gepubliceerde NL-pagina's.
        $pages = Page::query()
            ->where('locale', 'nl')
            ->where(fn ($q) => $q->where('published', true)->orWhere('is_homepage', true))
            ->get();

        foreach ($pages as $page) {
            $urls[] = [
                'loc' => Seo::absoluteUrl($page->is_homepage ? '/' : '/'.$page->slug),
                'lastmod' => $page->updated_at,
                'priority' => $page->is_homepage ? '1.0' : ($page->is_cornerstone ? '0.8' : '0.6'),
            ];
        }

        // Cases overzichtspagina
        $urls[] = [
            'loc' => Seo::absoluteUrl('/cases'),
            'lastmod' => now(),
            'priority' => '0.7',
        ];

        // Individuele cases
        $cases = CaseStudy::query()->where('published', true)->get();

        foreach ($cases as $case) {
            $urls[] = [
                'loc'      => Seo::absoluteUrl('/cases/'.$case->slug),
                'lastmod'  => $case->updated_at,
                'priority' => $case->is_cornerstone ? '0.8' : '0.7',
            ];
        }

        // Blog overzichtspagina
        $urls[] = [
            'loc'      => Seo::absoluteUrl('/blog'),
            'lastmod'  => now(),
            'priority' => '0.7',
        ];

        // Individuele blogberichten
        $posts = Post::query()->where('published', true)->get();

        foreach ($posts as $post) {
            $urls[] = [
                'loc'      => Seo::absoluteUrl('/blog/'.$post->slug),
                'lastmod'  => $post->updated_at,
                'priority' => $post->is_cornerstone ? '0.8' : '0.7',
            ];
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /livewire',
            '',
            'Sitemap: '.Seo::baseUrl().'/sitemap.xml',
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function llms(): Response
    {
        $footer = SiteFooter::current();
        $contact = $footer['contact'] ?? [];
        $base = Seo::baseUrl();

        $lines = [];
        $lines[] = '# '.Seo::siteName();
        $lines[] = '';

        $lines[] = '## Contact';
        if (filled($contact['address'] ?? null)) {
            $lines[] = '- Adres: '.str_replace(["\r\n", "\r", "\n"], ', ', trim($contact['address']));
        }
        if (filled($contact['phone'] ?? null)) {
            $lines[] = '- Telefoon: '.$contact['phone'];
        }
        if (filled($contact['email'] ?? null)) {
            $lines[] = '- E-mail: '.$contact['email'];
        }
        $lines[] = '- Website: '.$base.'/';
        $lines[] = '';

        $pages = Page::query()
            ->where('locale', 'nl')
            ->where(fn ($q) => $q->where('published', true)->orWhere('is_homepage', true))
            ->orderByDesc('is_homepage')
            ->orderByDesc('is_cornerstone')
            ->get();

        if ($pages->isNotEmpty()) {
            $lines[] = '## Pagina\'s';
            foreach ($pages as $page) {
                $url = Seo::absoluteUrl($page->is_homepage ? '/' : '/'.$page->slug);
                $desc = filled($page->meta_description) ? ': '.$page->meta_description : '';
                $lines[] = '- ['.($page->meta_title ?: $page->title).']('.$url.')'.$desc;
            }
            $lines[] = '';
        }

        $cases = CaseStudy::query()
            ->where('published', true)
            ->orderByDesc('featured')
            ->orderByDesc('updated_at')
            ->get();

        if ($cases->isNotEmpty()) {
            $lines[] = '## Cases';
            foreach ($cases as $case) {
                $url     = Seo::absoluteUrl('/cases/'.$case->slug);
                $desc    = filled($case->excerpt) ? ': '.$case->excerpt : '';
                $lines[] = '- ['.($case->meta_title ?: $case->title).']('.$url.')'.$desc;
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
                $url     = Seo::absoluteUrl('/blog/'.$post->slug);
                $desc    = filled($post->excerpt) ? ': '.$post->excerpt : '';
                $lines[] = '- ['.($post->meta_title ?: $post->title).']('.$url.')'.$desc;
            }
            $lines[] = '';
        }

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
