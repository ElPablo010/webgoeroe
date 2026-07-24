<?php

namespace App\Filament\Pages;

use App\Models\SeoActionItem;
use App\Models\SeoKeyword;
use App\Services\DataForSeoService;
use App\Services\SeoActionApplier;
use App\Services\SeoAdvisorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

/**
 * Het goedkeuringsdashboard: wekelijkse SEO-adviezen als uitvoerbare voorstellen.
 * Elk item toont het probleem + een uitgewerkte oplossing; "Goedkeuren"
 * publiceert die meteen in de page-builder (via SeoActionApplier). Niets gebeurt
 * zonder de klik van de beheerder.
 *
 * De pagina ís de Livewire-component — de kaart-acties (approve/dismiss/edit)
 * zijn publieke methodes die de blade via wire:click aanroept.
 */
class SeoActions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?string $navigationLabel = 'Acties';

    protected static ?string $title = 'Verbeteracties';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.seo-actions';

    public string $filter = 'all';

    public ?int $editingId = null;

    /** @var array<string,mixed> */
    public array $editForm = [];

    public static function getNavigationBadge(): ?string
    {
        $count = SeoActionItem::pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getSubheading(): ?string
    {
        $last = SeoActionItem::max('created_at');

        return $last
            ? 'Laatst gegenereerd ' . Carbon::parse($last)->diffForHumans()
            : 'Nog geen acties — genereer ze uit de laatste SEO-data.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Genereer acties nu')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->disabled(fn () => ! app(DataForSeoService::class)->isConfigured())
                ->action('generateNow'),
        ];
    }

    /* ---------------------------------------------------------------- */

    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(): array
    {
        $order = ['pending' => 0, 'published' => 1, 'dismissed' => 2];

        return SeoActionItem::with('page:id,slug,title,is_homepage')
            ->latest()
            ->get()
            ->when($this->filter !== 'all', fn ($c) => $c->where('status', $this->filter))
            ->sortBy(fn ($i) => $order[$i->status] ?? 9)
            ->values()
            ->map(fn (SeoActionItem $i) => [
                'id' => $i->id,
                'action_type' => $i->action_type,
                'status' => $i->status,
                'priority' => $i->priority,
                'title' => $i->title,
                'problem' => $i->problem,
                'proposed' => $i->proposed,
                'source_keyword' => $i->source_keyword,
                'page' => $i->page ? ['slug' => $i->page->is_homepage ? '/' : $i->page->slug] : null,
                'result_url' => $i->result_url,
                'feedback' => $this->actionFeedback($i),
            ])
            ->all();
    }

    /**
     * @return array<string,int>
     */
    public function counts(): array
    {
        $all = SeoActionItem::all();

        return [
            'all' => $all->count(),
            'pending' => $all->where('status', 'pending')->count(),
            'published' => $all->where('status', 'published')->count(),
            'dismissed' => $all->where('status', 'dismissed')->count(),
        ];
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    /* ---- kaart-acties ---- */

    public function approve(int $id): void
    {
        $item = SeoActionItem::findOrFail($id);
        if ($item->status === 'published') {
            return;
        }

        try {
            app(SeoActionApplier::class)->apply($item);
        } catch (\Throwable $e) {
            Notification::make()->title('Publiceren mislukt')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Gepubliceerd')->success()->send();
    }

    public function startEdit(int $id): void
    {
        $item = SeoActionItem::findOrFail($id);
        $proposed = $item->proposed ?? [];

        $text = collect($proposed['sections'] ?? [])->firstWhere('section_type', 'rich_text');
        $faqSection = collect($proposed['sections'] ?? [])->firstWhere('section_type', 'faq');
        $faq = data_get($faqSection, 'content.items', data_get($proposed, 'content.items', []));

        $this->editForm = [
            'meta_title' => $proposed['meta_title'] ?? '',
            'meta_description' => $proposed['meta_description'] ?? '',
            'heading' => data_get($text, 'content.heading', ''),
            'body' => data_get($text, 'content.body', ''),
            'faq' => array_values(array_map(fn ($f) => [
                'question' => $f['question'] ?? '',
                'answer' => $f['answer'] ?? '',
            ], $faq)),
        ];
        $this->editingId = $id;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editForm = [];
    }

    public function addFaqRow(): void
    {
        $this->editForm['faq'][] = ['question' => '', 'answer' => ''];
    }

    public function removeFaqRow(int $index): void
    {
        unset($this->editForm['faq'][$index]);
        $this->editForm['faq'] = array_values($this->editForm['faq']);
    }

    public function publish(int $id): void
    {
        $item = SeoActionItem::findOrFail($id);
        if ($item->status === 'published') {
            return;
        }

        try {
            app(SeoActionApplier::class)->apply($item, $this->buildProposed($item));
        } catch (\Throwable $e) {
            Notification::make()->title('Publiceren mislukt')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->cancelEdit();
        Notification::make()->title('Gepubliceerd')->success()->send();
    }

    public function dismiss(int $id): void
    {
        $item = SeoActionItem::findOrFail($id);
        if ($item->status !== 'published') {
            $item->update(['status' => 'dismissed', 'dismissed_at' => now()]);
        }
    }

    public function restore(int $id): void
    {
        $item = SeoActionItem::findOrFail($id);
        if ($item->status === 'dismissed') {
            $item->update(['status' => 'pending', 'dismissed_at' => null]);
        }
    }

    public function generateNow(): void
    {
        if (! app(DataForSeoService::class)->isConfigured()) {
            Notification::make()->title('DataForSEO is niet geconfigureerd')->danger()->send();

            return;
        }

        // Elke aanroep is een AI-call — bescherm tegen dubbelklikken.
        if (RateLimiter::tooManyAttempts('seo-generate-actions', 1)) {
            $seconds = RateLimiter::availableIn('seo-generate-actions');
            Notification::make()->title("Even geduld — probeer opnieuw over {$seconds}s.")->warning()->send();

            return;
        }
        RateLimiter::hit('seo-generate-actions', 120);

        $advisor = app(SeoAdvisorService::class);
        $actions = $advisor->generateActions($advisor->buildContext());

        if (! $actions) {
            Notification::make()->title('Geen nieuwe acties gevonden')->warning()->send();

            return;
        }

        $new = 0;
        foreach ($actions as $action) {
            if (SeoActionItem::where('fingerprint', $action['fingerprint'])->exists()) {
                continue;
            }
            SeoActionItem::create($action);
            $new++;
        }

        Notification::make()->title("{$new} nieuwe verbeteracties toegevoegd")->success()->send();
    }

    /* ---------------------------------------------------------------- */

    /**
     * Bouw een `proposed`-payload uit het inline-bewerkte formulier. Voor
     * `create_page` behouden we de volledige landingspagina-blueprint (hero, cta,
     * …) en patchen we enkel het "waarom"-tekstblok (`rich_text`) + de FAQ + meta.
     * Zo overschrijft "Aanpassen" de rijke secties niet.
     *
     * @return array<string,mixed>
     */
    protected function buildProposed(SeoActionItem $item): array
    {
        $f = $this->editForm;
        $faq = collect($f['faq'] ?? [])
            ->filter(fn ($r) => trim((string) ($r['question'] ?? '')) !== '' && trim((string) ($r['answer'] ?? '')) !== '')
            ->values()
            ->all();

        if ($item->action_type === 'create_page') {
            $sections = $item->proposed['sections'] ?? [];

            // Patch het "waarom"-tekstblok (eerste rich_text) als het bewerkt is.
            if (trim((string) ($f['heading'] ?? '')) !== '' || trim((string) ($f['body'] ?? '')) !== '') {
                $patched = array_filter([
                    'heading' => $f['heading'] ?? null,
                    'body' => $f['body'] ?? null,
                ], fn ($v) => $v !== null && $v !== '');
                $idx = collect($sections)->search(fn ($s) => ($s['section_type'] ?? '') === 'rich_text');
                if ($idx !== false) {
                    $sections[$idx]['content'] = array_merge($sections[$idx]['content'] ?? [], $patched);
                } elseif ($patched) {
                    $sections[] = ['section_type' => 'rich_text', 'content' => $patched];
                }
            }

            // Patch de FAQ; verwijderen als alle vragen zijn weggehaald.
            $faqIdx = collect($sections)->search(fn ($s) => ($s['section_type'] ?? '') === 'faq');
            if ($faqIdx !== false) {
                if ($faq) {
                    $sections[$faqIdx]['content'] = array_merge($sections[$faqIdx]['content'] ?? [], ['items' => $faq]);
                } else {
                    unset($sections[$faqIdx]);
                }
            } elseif ($faq) {
                $sections[] = ['section_type' => 'faq', 'content' => ['heading' => 'Veelgestelde vragen', 'items' => $faq]];
            }

            return array_filter([
                'slug' => $item->proposed['slug'] ?? null,
                'meta_title' => $f['meta_title'] ?: null,
                'meta_description' => $f['meta_description'] ?: null,
                'sections' => array_values($sections),
            ], fn ($v) => $v !== null && $v !== []);
        }

        if ($item->action_type === 'add_section') {
            return ['section_type' => 'faq', 'content' => ['heading' => 'Veelgestelde vragen', 'items' => $faq]];
        }

        // optimize_meta
        return array_filter([
            'meta_title' => $f['meta_title'] ?: null,
            'meta_description' => $f['meta_description'] ?: null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Terugkoppeling voor een gepubliceerd item: de huidige positie van het
     * bron-keyword (dat bij publicatie is toegevoegd aan de opgevolgde lijst).
     *
     * @return array<string,mixed>|null
     */
    protected function actionFeedback(SeoActionItem $item): ?array
    {
        if ($item->status !== 'published' || ! $item->source_keyword) {
            return null;
        }

        $api = app(DataForSeoService::class);
        $keyword = SeoKeyword::where('keyword', $item->source_keyword)
            ->where('location_code', $api->locationCode)
            ->where('language_code', $api->languageCode)
            ->first();

        if (! $keyword) {
            return ['tracked' => false];
        }

        $result = $keyword->latestResult;
        if (! $result) {
            return ['tracked' => true, 'measured' => false];
        }

        return [
            'tracked' => true,
            'measured' => true,
            'rank' => $result->rank_group,
            'delta' => $result->delta,
            'ai_cited' => (bool) $result->ai_overview_cited,
            'checked_at' => $result->checked_at?->format('Y-m-d'),
        ];
    }
}
