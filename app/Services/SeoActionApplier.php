<?php

namespace App\Services;

use App\Models\Page;
use App\Models\SeoActionItem;
use App\Models\SeoKeyword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Past een goedgekeurde SEO-verbeteractie effectief toe op de ingebouwde
 * page-builder. Alles gebeurt in één transactie en is idempotent: een reeds
 * gepubliceerd item wordt overgeslagen.
 *
 * Enkel content-acties (create_page / add_section / optimize_meta). Technische
 * SEO die code raakt (JSON-LD-templates, nieuwe sectietypes, …) valt buiten
 * scope en handel je ad-hoc af.
 *
 * De sectie-content volgt het contract van de new-website page-builder:
 *   - `hero`-sectie:      { heading, subtitle, ctas: [{ label, href, variant }] }
 *   - `rich_text`-sectie: { heading, body }  (body = rich-text HTML)
 *   - `faq`-sectie:       { heading, items: [{ question, answer }] }
 *   - `cta`-sectie:       { heading, intro, ctas: [{ label, href, variant }] }
 * De secties worden opgebouwd in SeoAdvisorService::buildLandingSections();
 * hier worden ze enkel weggeschreven. `create_page` levert een volledige
 * landingspagina, en `add_section` (FAQ) merget in een bestaande FAQ-sectie
 * i.p.v. een duplicaat aan te maken. Wijkt jouw project van dit contract af,
 * pas dan die builder + de merge/positie-helpers hieronder aan.
 */
class SeoActionApplier
{
    public function __construct(protected DataForSeoService $api)
    {
    }

    public function apply(SeoActionItem $item, ?array $editedProposed = null): SeoActionItem
    {
        if ($item->status === 'published') {
            return $item;
        }

        $proposed = $editedProposed ?: ($item->proposed ?? []);

        DB::transaction(function () use ($item, $proposed) {
            match ($item->action_type) {
                'create_page' => $this->applyCreatePage($item, $proposed),
                'add_section' => $this->applyAddSection($item, $proposed),
                'optimize_meta' => $this->applyOptimizeMeta($item, $proposed),
                default => throw new \InvalidArgumentException("Onbekend action_type: {$item->action_type}"),
            };
        });

        return $item->refresh();
    }

    /* ---------------------------------------------------------------- */

    protected function applyCreatePage(SeoActionItem $item, array $proposed): void
    {
        $title = $proposed['meta_title'] ?? $item->title;
        $slug = $this->uniqueSlug($proposed['slug'] ?? Str::slug($title));

        $page = Page::create([
            'title' => $title,
            'slug' => $slug,
            'locale' => 'nl',
            'published' => true,
            'meta_title' => $proposed['meta_title'] ?? null,
            'meta_description' => $proposed['meta_description'] ?? null,
            'meta_robots' => 'index,follow',
        ]);

        foreach (array_values($proposed['sections'] ?? []) as $position => $section) {
            if (empty($section['section_type'])) {
                continue;
            }
            $page->sections()->create([
                'section_type' => $section['section_type'],
                'position' => $position,
                'content' => $section['content'] ?? [],
                'locale' => 'nl',
            ]);
        }

        $this->trackSourceKeyword($item);

        $item->update([
            'status' => 'published',
            'applied_at' => now(),
            'created_page_id' => $page->id,
            'page_id' => $page->id,
            'result_url' => $page->publicUrl(),
        ]);
    }

    protected function applyAddSection(SeoActionItem $item, array $proposed): void
    {
        $page = $item->page;
        if (! $page) {
            throw new \RuntimeException('Geen doelpagina gekoppeld aan deze actie.');
        }

        $type = $proposed['section_type'] ?? 'faq';
        $content = $proposed['content'] ?? [];

        // Heeft de pagina al een FAQ-sectie? Voeg de nieuwe vragen daaraan toe
        // i.p.v. een duplicaat aan te maken (met dedup op de vraag).
        if ($type === 'faq' && $this->mergeIntoExistingFaq($page, $content)) {
            $this->trackSourceKeyword($item);
            $item->update([
                'status' => 'published',
                'applied_at' => now(),
                'result_url' => $page->publicUrl(),
            ]);

            return;
        }

        $page->sections()->create([
            'section_type' => $type,
            'position' => $this->positionBeforeTrailingCta($page),
            'content' => $content,
            'locale' => $page->locale ?: 'nl',
        ]);

        $this->trackSourceKeyword($item);

        $item->update([
            'status' => 'published',
            'applied_at' => now(),
            'result_url' => $page->publicUrl(),
        ]);
    }

    /**
     * Voegt de FAQ-items uit $content toe aan de eerste bestaande FAQ-sectie van
     * de pagina (dedup op de genormaliseerde vraag). Geeft false als er nog geen
     * FAQ-sectie is — dan wordt er een nieuwe aangemaakt.
     */
    protected function mergeIntoExistingFaq(Page $page, array $content): bool
    {
        $existing = $page->sections()
            ->where('section_type', 'faq')
            ->orderBy('position')
            ->first();

        if (! $existing) {
            return false;
        }

        $current = is_array($existing->content) ? $existing->content : (array) json_decode((string) $existing->content, true);
        $items = array_values($current['items'] ?? []);

        $seen = [];
        foreach ($items as $it) {
            $seen[$this->faqKey($it['question'] ?? '')] = true;
        }

        foreach ($content['items'] ?? [] as $new) {
            $question = trim((string) ($new['question'] ?? ''));
            $answer = trim((string) ($new['answer'] ?? ''));
            $key = $this->faqKey($question);
            if ($key === '' || $answer === '' || isset($seen[$key])) {
                continue;
            }
            $items[] = ['question' => $question, 'answer' => $answer];
            $seen[$key] = true;
        }

        $current['items'] = $items;
        $existing->update(['content' => $current]);

        return true;
    }

    /** Genormaliseerde sleutel voor dedup van FAQ-vragen. */
    protected function faqKey(string $question): string
    {
        return Str::of($question)->lower()->squish()->trim(" ?.!")->value();
    }

    /**
     * Positie voor een nieuwe sectie: net vóór een eventuele afsluitende
     * CTA-sectie (die hoort onderaan te blijven), anders helemaal achteraan.
     */
    protected function positionBeforeTrailingCta(Page $page): int
    {
        $last = $page->sections()->orderByDesc('position')->first();

        if ($last && $last->section_type === 'cta') {
            $position = (int) $last->position;
            $last->update(['position' => $position + 1]);

            return $position;
        }

        return ((int) $page->sections()->max('position')) + 1;
    }

    protected function applyOptimizeMeta(SeoActionItem $item, array $proposed): void
    {
        $page = $item->page;
        if (! $page) {
            throw new \RuntimeException('Geen doelpagina gekoppeld aan deze actie.');
        }

        $updates = array_filter([
            'meta_title' => $proposed['meta_title'] ?? null,
            'meta_description' => $proposed['meta_description'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($updates) {
            $page->update($updates);
        }

        $item->update([
            'status' => 'published',
            'applied_at' => now(),
            'result_url' => $page->publicUrl(),
        ]);
    }

    /* ---------------------------------------------------------------- */

    /** Voeg het bron-keyword toe aan de opgevolgde lijst. */
    protected function trackSourceKeyword(SeoActionItem $item): void
    {
        $keyword = trim((string) $item->source_keyword);
        if ($keyword === '') {
            return;
        }

        SeoKeyword::firstOrCreate(
            [
                'keyword' => $keyword,
                'location_code' => $this->api->locationCode,
                'language_code' => $this->api->languageCode,
            ],
            ['tag' => 'SEO-actie', 'is_active' => true],
        );
    }

    /** Garandeer een unieke slug binnen `pages`. */
    protected function uniqueSlug(string $slug): string
    {
        $slug = Str::slug($slug) ?: 'pagina';
        $base = $slug;
        $i = 2;
        while (Page::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
