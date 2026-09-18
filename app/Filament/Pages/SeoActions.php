<?php

namespace App\Filament\Pages;

use App\Jobs\GenerateSeoActionsJob;
use App\Models\SeoActionItem;
use App\Models\SeoKeyword;
use App\Models\Setting;
use App\Services\DataForSeoService;
use App\Services\Seo\ActionBacklog;
use App\Services\SeoActionApplier;
use App\Support\JobStatus;
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

    protected static string|UnitEnum|null $navigationGroup = 'Groei';

    protected static ?string $navigationLabel = 'Acties';

    protected static ?string $title = 'Verbeteracties';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.seo-actions';

    /** Vanaf wanneer een openstaand voorstel "oud" heet — voedt de bulkknop. */
    public const STALE_DAYS = 30;

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
            ? 'Laatst gegenereerd '.Carbon::parse($last)->diffForHumans()
            : 'Nog geen acties — genereer ze uit de laatste SEO-data.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Bewust géén harde blokkade zolang er werk ligt: dit is de
            // handmatige noodrem, en die dichttimmeren is irritant op het
            // moment dat je juist controle wilt. Een bevestiging haalt hetzelfde
            // doel — je ziet wat je aanricht — zonder je de weg te versperren.
            Action::make('generate')
                ->label('Genereer acties nu')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->disabled(fn () => ! app(DataForSeoService::class)->isConfigured())
                ->requiresConfirmation(fn () => $this->backlog()->isBlocked())
                ->modalHeading('Er ligt nog werk')
                ->modalDescription(fn () => 'Er staan nog '.$this->backlog()->openCount()
                    .' acties open. Nieuwe voorstellen maken het lijstje alleen langer — '
                    .'handel ze eerst af, of genereer toch.')
                ->modalSubmitActionLabel('Toch genereren')
                ->action(fn () => $this->generateNow()),

            Action::make('dismissOld')
                ->label('Negeer oude voorstellen')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->visible(fn () => $this->staleCount() > 0)
                ->requiresConfirmation()
                ->modalHeading('Oude voorstellen negeren')
                ->modalDescription(fn () => $this->staleCount().' voorstellen zijn ouder dan '
                    .self::STALE_DAYS.' dagen. Die zijn gebouwd op posities die intussen verschoven '
                    .'zijn — negeren maakt ruimte voor verse voorstellen.')
                ->modalSubmitActionLabel('Negeren')
                ->action(fn () => $this->dismissOld()),
        ];
    }

    /** De bewaker van de openstaande lijst. */
    public function backlog(): ActionBacklog
    {
        return app(ActionBacklog::class);
    }

    /** Hoeveel openstaande voorstellen al te oud zijn om nog te kloppen. */
    public function staleCount(): int
    {
        return SeoActionItem::pending()
            ->where('created_at', '<', Carbon::now()->subDays(self::STALE_DAYS))
            ->count();
    }

    /**
     * Legt uit waarom er géén nieuwe voorstellen bijkomen. Zonder deze regel
     * lijkt een lijst die niet aangroeit op een stilgevallen module, terwijl
     * het juist de bedoeling is: eerst afwerken, dan pas nieuwe.
     */
    public function backlogNotice(): ?string
    {
        $backlog = $this->backlog();

        if (! $backlog->isBlocked()) {
            return null;
        }

        $open = $backlog->openCount();
        $expiry = $backlog->nextExpiryAt();

        return 'Er komen geen nieuwe voorstellen bij zolang '.($open === 1
            ? 'dit voorstel openstaat'
            : 'deze '.$open.' voorstellen openstaan')
            .'. Keur ze goed of negeer ze, dan staan er bij de volgende wekelijkse analyse '
            .$backlog->limit().' nieuwe klaar.'
            .($expiry ? ' Het oudste vervalt automatisch op '.$expiry->format('d/m/Y').'.' : '');
    }

    public function dismissOld(): void
    {
        $count = SeoActionItem::pending()
            ->where('created_at', '<', Carbon::now()->subDays(self::STALE_DAYS))
            ->update(['status' => 'dismissed', 'dismissed_at' => now()]);

        Notification::make()
            ->title($count.' '.($count === 1 ? 'voorstel genegeerd' : 'voorstellen genegeerd'))
            ->success()
            ->send();
    }

    /* ---------------------------------------------------------------- */

    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(): array
    {
        $order = ['pending' => 0, 'published' => 1, 'dismissed' => 2, ActionBacklog::STATUS_EXPIRED => 3];

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
     * Legt uit waarom er niets te beoordelen staat. Zonder dit verdwijnt een
     * run waarvan alles als duplicaat wegviel — of die niets opleverde — in
     * stilte, en lijkt een lege lijst op een storing.
     */
    public function runNotice(): ?string
    {
        if ($this->counts()['pending'] > 0) {
            return null;
        }

        $data = json_decode((string) Setting::get('seo_actions_last_run'), true);
        if (! is_array($data)) {
            return null;
        }

        $when = ! empty($data['at']) ? ' ('.Carbon::parse($data['at'])->diffForHumans().')' : '';
        $proposed = (int) ($data['proposed'] ?? 0);

        if ($proposed === 0) {
            return "De laatste analyse{$when} leverde geen voorstellen op. Krijg je dit vaker, "
                .'kijk dan in de log naar "SEO-acties: wat het model teruggaf".';
        }

        if ((int) ($data['created'] ?? 0) === 0) {
            return "De laatste analyse{$when} stelde {$proposed} ".($proposed === 1 ? 'actie' : 'acties')
                .' voor, maar die stonden hier al eerder — er is dus niets nieuws om te beoordelen.';
        }

        return null;
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
            ActionBacklog::STATUS_EXPIRED => $all->where('status', ActionBacklog::STATUS_EXPIRED)->count(),
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
            'has_text' => $text !== null,
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
        if (! in_array($item->status, ['dismissed', ActionBacklog::STATUS_EXPIRED], true)) {
            return;
        }

        // `reopened_at` herstart de vervalklok: zonder dat zou een teruggezet
        // voorstel bij de eerstvolgende run meteen opnieuw vervallen, want z'n
        // created_at ligt al ver achter ons.
        $item->update([
            'status' => 'pending',
            'dismissed_at' => null,
            'reopened_at' => now(),
        ]);
    }

    /** De stand van de lopende (of laatste) analyse — voedt de banner bovenaan. */
    public function jobStatus(): JobStatus
    {
        return JobStatus::for(GenerateSeoActionsJob::STATUS_KEY);
    }

    /** Terwijl de analyse loopt ververst de pagina zichzelf. */
    public function pollInterval(): ?string
    {
        return $this->jobStatus()->isBusy() ? '15s' : null;
    }

    public function dismissJobStatus(): void
    {
        $this->jobStatus()->clear();
    }

    public function generateNow(): void
    {
        if (! app(DataForSeoService::class)->isConfigured()) {
            Notification::make()->title('DataForSEO is niet geconfigureerd')->danger()->send();

            return;
        }

        $status = $this->jobStatus();

        if ($status->isBusy()) {
            Notification::make()
                ->title('De analyse loopt al')
                ->body('De stand staat bovenaan dit scherm en ververst vanzelf.')
                ->warning()
                ->send();

            return;
        }

        // Elke aanroep is een AI-call — bescherm tegen dubbelklikken.
        if (RateLimiter::tooManyAttempts(GenerateSeoActionsJob::RATE_LIMIT_KEY, 1)) {
            $seconds = RateLimiter::availableIn(GenerateSeoActionsJob::RATE_LIMIT_KEY);
            Notification::make()->title("Even geduld — probeer opnieuw over {$seconds}s.")->warning()->send();

            return;
        }
        RateLimiter::hit(GenerateSeoActionsJob::RATE_LIMIT_KEY, 120);

        // Vol? Dan kwam de klik langs de bevestiging hierboven — dat is een
        // bewuste keuze, dus laten we hem door. Het aantal nieuwe acties blijft
        // wel begrensd; forceren mag de lijst verlengen, niet laten ontploffen.
        $force = $this->backlog()->isBlocked();

        $status->queued();

        // Naar de queue: het model schrijft volledige landingspagina's uit en
        // doet daar ruim een minuut over. Synchroon loopt dat op shared hosting
        // in een time-out, zonder dat er iets wordt opgeslagen. Vereist wel een
        // draaiende `queue:work` — draait die niet, dan zegt de banner dat na
        // een paar minuten zelf.
        GenerateSeoActionsJob::dispatch(force: $force);

        Notification::make()
            ->title('De analyse loopt')
            ->body('De stand verschijnt bovenaan dit scherm en ververst vanzelf — je mag gerust weggaan en later terugkomen.')
            ->success()
            ->send();
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
                    // Geen tekstblok in het voorstel: zet er één ná de hero.
                    // Achteraan zou het ná de afsluitende CTA belanden.
                    array_splice($sections, 1, 0, [['section_type' => 'rich_text', 'content' => $patched]]);
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
            'checked_at' => $result->checked_at?->format('d/m/Y'),
        ];
    }
}
