<?php

namespace App\Providers;

use App\Filament\Schemas\Sections\CalculatorFields;
use App\Filament\Schemas\Sections\CaseResultsFields;
use App\Filament\Schemas\Sections\ConversionCalculatorFields;
use App\Filament\Schemas\Sections\LeadCheckFields;
use App\Livewire\LeadCheckForm;
use App\Mcp\Tools\CreateCase;
use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\ListCases;
use App\Mcp\Tools\ListPayments;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\PreviewImage;
use App\Mcp\Tools\PublishCase;
use App\Mcp\Tools\PublishPost;
use App\Mcp\Tools\UnpublishCase;
use App\Mcp\Tools\UnpublishPost;
use App\Mcp\Tools\UpdateCase;
use App\Mcp\Tools\UpdatePost;
use App\Mcp\Tools\UploadMediaFile;
use App\Models\Page;
use App\Support\AdAttribution;
use App\Support\AiCoaching;
use App\Support\LandingPages;
use App\Support\LeadCheck;
use App\Support\LeadCheckExtensions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Webgoeroe\Chat\Support\WidgetVisibility;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Filament\Schemas\Components\MediaPickerField;
use Webgoeroe\Core\Filament\Schemas\Components\PageLinkField;
use Webgoeroe\Core\Filament\Schemas\Components\RichText;
use Webgoeroe\Core\Filament\Schemas\Sections\CtaLinkSchema;
use Webgoeroe\Core\Integrations\Meta\ConversionsApi;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    /**
     * Blog-, cases- en betalingstools voor de MCP-koppeling van de core (POST /mcp).
     * upload_media_from_url komt uit de core zelf.
     */
    protected function registerMcpTools(): void
    {
        Core::mcpTools()->add([
            // Blog
            ListPosts::class, CreatePost::class, UpdatePost::class, PublishPost::class, UnpublishPost::class,
            // Cases
            ListCases::class, CreateCase::class, UpdateCase::class, PublishCase::class, UnpublishCase::class,
            // Gedeeld
            UploadMediaFile::class, PreviewImage::class,
            // Betalingen (alleen-lezen, voor de facturatie)
            ListPayments::class,
        ]);
    }

    public function boot(): void
    {
        // Eigen (merk-)consentscherm voor de OAuth-flow, o.a. de claude.ai-connector.
        Passport::authorizationView('oauth.authorize');

        $this->registerMcpTools();
        $this->registerBlocks();
        $this->registerBookings();
        $this->hideChatWidgetOnLandingPages();
        $this->registerLeadChecks();
        // Schema.org-Service met de twee prijzen op /ai-coaching (FAQPage en BreadcrumbList levert de core).
        Core::seo()->pageGraph(fn (array $nodes, $page): array => AiCoaching::graph($nodes, $page));
        // Startformulier van de landingspagina "Speed to lead elektriciens".
        Core::formTypes()->register('speed_to_lead_start', 'Speed to lead: starten', 'forms.speed-to-lead-start-form', [], 'Speed to lead: starten');
        $this->registerGeneralSettings();
        $this->registerPageTable();
    }

    /**
     * Eigen kalender (core): herkomst van de advertentie meebewaren (`herkomst` staat
     * op Integraties aan het GHL-veld "Herkomst advertentie" gekoppeld). Het Meta-event
     * Schedule stuurt de core zelf (Meta Conversions API, standaard uit).
     */
    protected function registerBookings(): void
    {
        Core::bookings()
            ->rules(AdAttribution::RULES)
            ->data(fn (Request $request): array => AdAttribution::data($request));
    }

    /**
     * Lekkencheck (blok `lead_check`; tot core 0.27 in de core): blok, aanvraagtype,
     * GHL per vraag, veldlabels en formulier. Plus de herkomst van de advertentie
     * meebewaren (zoals bij de boeking) en het Meta-serverevent "Lead" met hetzelfde event_id als de pixel.
     */
    protected function registerLeadChecks(): void
    {
        $this->app->singleton(LeadCheckExtensions::class);

        Core::blocks()
            ->register(LeadCheckForm::TYPE, 'Lekkencheck (vragen + resultaat)', [LeadCheckFields::class, 'make'])
            // Per vraag een eigen GHL-veld, de resultaatvelden en een tag per oranje/rood lek.
            ->ghl(LeadCheckForm::TYPE, LeadCheckForm::TYPE, sync: function (array $content, array $data): array {
                $fields = [];

                foreach (LeadCheck::questions($content) as $question) {
                    if (filled($question['ghl_field'])) {
                        $fields[] = ['form' => $question['key'], 'ghl' => $question['ghl_field']];
                    }
                }

                foreach ((array) ($content['ghl_result'] ?? []) as $key => $ghl) {
                    if (filled($ghl)) {
                        $fields[] = ['form' => (string) $key, 'ghl' => (string) $ghl];
                    }
                }

                return ['fields' => $fields, 'tags' => (array) ($data['leak_tags'] ?? [])];
            });

        Core::formTypes()
            ->category(LeadCheckForm::TYPE, 'Lekkencheck', fn (): bool => Core::model('page_section')::query()->where('section_type', LeadCheckForm::TYPE)->exists())
            ->fieldLabels([
                'result_year' => 'Bedrag per jaar',
                'result_month' => 'Bedrag per maand',
                'lost_clients' => 'Gemiste klanten per maand',
                'top_leak' => 'Grootste lek',
                'top_leak_advice' => 'Tip bij het grootste lek',
                'leaks' => 'Lekken',
                'score' => 'Score',
                'leak_tags' => 'Lek-tags',
            ]);

        Livewire::component(LeadCheck::COMPONENT, LeadCheckForm::class);

        LeadCheck::hooks()
            ->rules(AdAttribution::RULES)
            ->data(fn (Request $request): array => AdAttribution::data($request))
            ->submitted(fn (Request $request, Model $submission, array $contact) => ConversionsApi::recordSubmission($submission, $request->input('tracking'), 'Lead', $request));
    }

    /**
     * Blokken die enkel deze site heeft, en de extra velden op core-blokken.
     * De publieke views staan in resources/views/components/site/sections.
     */
    protected function registerBlocks(): void
    {
        Core::blocks()
            // Agenda (boeking): GoHighLevel (iframe, pop-up of eigen kalender, met de
            // formuliervelden) komt uit de core zodra "Koppeling GoHighLevel" aanstaat.
            ->extend('booking', function (array $fields): array {
                // Opmaak in dezelfde woorden en op dezelfde plek als bij het formulierblok:
                // na de tekstvelden, net vóór de agenda-instellingen. Badge en vinkjes
                // gelden voor beide opmaken (de core toont ze enkel bij "hero").
                $layout = null;
                foreach ($fields as $i => $field) {
                    if ($field instanceof Select && $field->getName() === 'layout') {
                        $layout = $field->label('Layout (op brede schermen)')->options([
                            'hero' => 'Naast de tekst (kalender rechts)',
                            'section' => 'Onder de tekst (gecentreerd)',
                        ]);
                        unset($fields[$i]);
                    }
                    if (is_object($field) && method_exists($field, 'getName') && in_array($field->getName(), ['badge', 'benefits'], true)) {
                        $field->visible(true);
                    }
                }
                $fields = array_values($fields);
                // Kleine tekst onder de agenda (prijzen, betaling, een alternatief voor wie twijfelt).
                $fields[] = Textarea::make('after_text')
                    ->label('Tekst onder de agenda (optioneel)')
                    ->helperText('Kleine tekst onder de kalender. Een lege regel start een nieuwe alinea.')
                    ->rows(3)
                    ->maxLength(1000);
                if ($layout) {
                    // Opmaak als eerste veld in het raster van Aanbieder: Opmaak | Aanbieder.
                    $providerGrid = collect($fields)->search(fn ($f) => $f instanceof Grid
                        && collect($f->getDefaultChildComponents())->contains(fn ($c) => is_object($c) && method_exists($c, 'getName') && $c->getName() === 'provider'));
                    if ($providerGrid === false) {
                        $fields[] = $layout;
                    } else {
                        $fields[$providerGrid]->schema([$layout, ...$fields[$providerGrid]->getDefaultChildComponents()]);
                    }
                }

                return $fields;
            })
            ->register('calculator', 'Calculator (gemiste omzet)', CalculatorFields::class)
            ->register('conversion_calculator', 'Calculator (website-conversie)', ConversionCalculatorFields::class)
            // Levert aanvragen van type omzetcheck: tags, bron en deal per blok (core).
            ->ghl('conversion_calculator', 'omzetcheck')
            ->register('case_results', 'Case-resultaten', CaseResultsFields::class)
            // Cases-grid komt uit de module webgoeroe/cases (eigen ontwerp in
            // components/site/sections/cases-grid).
            // Contactgegevens naast het formulier (e-mail, telefoon, adres), net
            // onder de kop en vóór de formulierkeuze.
            ->extend('form', function (array $fields): array {
                array_splice($fields, max(0, count($fields) - 2), 0, [
                    Grid::make(['default' => 1, 'md' => 3])
                        ->schema([
                            TextInput::make('contact_email')
                                ->label('E-mailadres')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('contact_phone')
                                ->label('Telefoonnummer')
                                ->tel()
                                ->maxLength(64),
                            TextInput::make('contact_address')
                                ->label('Adres')
                                ->maxLength(255),
                        ]),
                ]);

                return self::formTypeAndLayoutSideBySide($fields);
            })
            // Video naast de tekst (YouTube/Vimeo of mp4), in de plaats van de
            // afbeelding. Enkel bij "Beeld naast de tekst"; leeg = de afbeelding.
            ->extend('hero', function (array $fields): array {
                $fields[] = TextInput::make('video_url')
                    ->label('Video naast de tekst (optioneel)')
                    ->helperText('YouTube-, Vimeo- of mp4-link. Toont de video in plaats van de afbeelding, enkel bij "Beeld naast de tekst". De afbeelding blijft de terugval en de SEO-afbeelding.')
                    ->url()
                    ->maxLength(255);
                $fields[] = TextInput::make('cta_note')
                    ->label('Tekst onder de knoppen (optioneel)')
                    ->helperText('Bv. de prijs, kort en op één regel.')
                    ->maxLength(160);

                return $fields;
            })
            // Knoppen en een slottekst onder de kaarten (bv. extra uitleg bij de prijzen en de knop naar de agenda).
            ->extend('cards', function (array $fields): array {
                $fields[] = RichText::make('footer')
                    ->label('Tekst onder de kaarten (optioneel)')
                    ->toolbarButtons([
                        ['bold', 'italic', 'link'],
                        ['bulletList'],
                        ['undo', 'redo'],
                    ]);
                $fields[] = CtaLinkSchema::repeater('ctas', 'Knoppen onder de kaarten');

                return $fields;
            })
            // Optionele afbeelding in de call-to-action: tekst links, beeld rechts.
            // Leeg = het blok blijft gecentreerd zoals voorheen.
            ->extend('cta', function (array $fields): array {
                $fields[] = MediaPickerField::make('image.src', 'Afbeelding (optioneel)', required: false);
                $fields[] = TextInput::make('image.alt')
                    ->label('Afbeelding: alt-tekst')
                    ->maxLength(255);
                // Korte regel onder de knoppen met een link naar een pagina. De regel verschijnt
                // pas zodra die pagina gepubliceerd is, dus een concept-pagina geeft geen dode link.
                $fields[] = Repeater::make('extra_lines')
                    ->label('Extra regel met link (optioneel)')
                    ->helperText('Bv. "Liever zelf leren? Bekijk onze AI-coaching." Verschijnt pas als de pagina gepubliceerd is.')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('text')->label('Tekst voor de link')->maxLength(120),
                            TextInput::make('label')->label('Linktekst')->required()->maxLength(120),
                        ]),
                        PageLinkField::make(),
                    ])
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->maxItems(1)
                    ->defaultItems(0)
                    ->collapsible()
                    ->collapsed();

                return $fields;
            })
            // Reviews klaarzetten zonder ze te tonen, bv. voorbeeldgetuigenissen
            // tot er echte zijn. pages/show slaat een verborgen blok over.
            ->extend('reviews', function (array $fields): array {
                $fields[] = Toggle::make('hidden')
                    ->label('Verbergen op de site')
                    ->helperText('Het blok blijft bewaard maar verschijnt niet op de pagina.');

                return $fields;
            });

        // Op elk bloktype: twee blokken die op dezelfde plek afwisselen, met twee
        // knoppen erboven (App\Support\SectionSwap). Bv. startformulier en agenda.
        foreach (Core::blocks()->types() as $type) {
            Core::blocks()->extend($type, fn (array $fields): array => [...$fields, self::sectionSwapFields()]);
        }
    }

    /**
     * Formulierblok: "Formulier" en "Layout (op brede schermen)" naast elkaar. In de core
     * staan ze elk in een eigen raster (naast "Telefoonnummer verplicht"); hier zetten we
     * de twee keuzes samen en de schakelaar eronder. Vindt het die velden niet (andere
     * core-versie), dan blijft alles zoals het was.
     *
     * @param  array<int, mixed>  $fields
     * @return array<int, mixed>
     */
    protected static function formTypeAndLayoutSideBySide(array $fields): array
    {
        $children = fn (mixed $c): array => $c instanceof Grid && is_array($c->getDefaultChildComponents()) ? $c->getDefaultChildComponents() : [];
        $named = fn (array $list, string $name): ?object => collect($list)->first(fn ($f) => is_object($f) && method_exists($f, 'getName') && $f->getName() === $name);

        $typeIndex = $layoutIndex = null;
        foreach ($fields as $i => $field) {
            if ($named($children($field), 'form_type')) {
                $typeIndex = $i;
            }
            if ($named($children($field), 'form_layout')) {
                $layoutIndex = $i;
            }
        }

        if ($typeIndex === null || $layoutIndex === null || $typeIndex === $layoutIndex) {
            return $fields;
        }

        $typeChildren = $children($fields[$typeIndex]);
        $type = $named($typeChildren, 'form_type');
        $layout = $named($children($fields[$layoutIndex]), 'form_layout');
        $rest = array_values(array_filter($typeChildren, fn ($f) => $f !== $type));

        $fields[$typeIndex] = Grid::make(['default' => 1, 'md' => 2])->schema([$type, $layout]);
        $fields[$layoutIndex] = $rest ? Grid::make(['default' => 1, 'md' => 2])->schema($rest) : null;

        return array_values(array_filter($fields));
    }

    /**
     * Velden "Wisselen met een ander blok". Het andere blok moet een anker hebben en
     * direct voor of na dit blok staan; anders gebeurt er niets (geen verborgen inhoud).
     */
    protected static function sectionSwapFields(): Section
    {
        return Section::make('Wisselen met een ander blok')
            ->description('Twee blokken op dezelfde plek, met twee knoppen erboven om te wisselen. Geef beide blokken een anker-ID en zet ze direct onder elkaar.')
            ->collapsible()
            ->collapsed()
            ->schema([
                Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('swap_with')
                            ->label('Wisselen met (anker-ID van het andere blok)')
                            ->placeholder('bv. afspraak')
                            ->maxLength(64)
                            ->regex('/^[a-z0-9\-]*$/i')
                            ->validationMessages(['regex' => 'Enkel letters, cijfers en streepjes.']),
                        TextInput::make('swap_label')
                            ->label('Knoptekst voor dit blok')
                            ->placeholder('bv. Ik wil starten')
                            ->helperText('Leeg = de titel van het blok.')
                            ->maxLength(60),
                    ]),
                Toggle::make('swap_hidden')
                    ->label('Eerst verborgen')
                    ->helperText('Aan = dit blok verschijnt pas na een klik op zijn knop.'),
            ]);
    }

    /**
     * Instellingen → Algemeen. De CTA-banner onder blog en cases staat sinds
     * november 2026 in Instellingen → Blog en → Cases (modules).
     */
    protected function registerGeneralSettings(): void
    {
        Core::generalSettings()->section(
            fn () => Section::make('Landingspagina\'s')
                ->description('Pagina\'s voor advertenties: zonder menu en footer, zodat de bezoeker op de pagina blijft. Onderaan blijven enkel de juridische links staan.')
                ->schema([
                    Select::make(LandingPages::KEY)
                        ->label('Pagina\'s zonder menu en footer')
                        ->multiple()
                        ->options(fn (): array => Page::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable(),
                ]),
            keys: [LandingPages::KEY],
            fill: fn (): array => [LandingPages::KEY => LandingPages::ids()],
        );
    }

    /**
     * Paginatabel: vinkje "Landingspagina" naast "Gepubliceerd", en een filter
     * op type (gewone pagina of landingspagina). Welke pagina's landingspagina's
     * zijn, staat in Instellingen → Algemeen (LandingPages).
     */
    protected function registerPageTable(): void
    {
        Core::pageTable()
            ->columns(fn (): array => [
                IconColumn::make('landing_page')
                    ->label('Landingspagina')
                    ->state(fn (Page $record): bool => LandingPages::contains($record))
                    ->boolean(),
            ])
            ->filters(fn (): array => [
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'page' => 'Gewone pagina',
                        'landing' => 'Landingspagina',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'page' => $query->whereKeyNot(LandingPages::ids()),
                        'landing' => $query->whereKey(LandingPages::ids()),
                        default => $query,
                    }),
            ]);
    }

    /**
     * Landingspagina's hebben één doel: de chatwidget (webgoeroe/chat) hoort er
     * niet op, de gewone pagina's houden hem.
     */
    protected function hideChatWidgetOnLandingPages(): void
    {
        if (! class_exists(WidgetVisibility::class)) {
            return;
        }

        WidgetVisibility::hideWhen(function (Request $request): bool {
            $ids = LandingPages::ids();

            return $ids !== [] && Page::query()
                ->whereIn('id', $ids)
                ->where('slug', trim($request->path(), '/'))
                ->exists();
        });
    }
}
