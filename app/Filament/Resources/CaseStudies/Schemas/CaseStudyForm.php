<?php

namespace App\Filament\Resources\CaseStudies\Schemas;

use App\Filament\Schemas\Components\MediaPickerField;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CaseStudyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Case study')
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        Tab::make('Basis')
                            ->id('basis')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Titel')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (?string $state, callable $get, callable $set): void {
                                                if (filled($state) && blank($get('slug'))) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),
                                        TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Wordt automatisch afgeleid van de titel.'),
                                    ]),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        TextInput::make('client')
                                            ->label('Klant')
                                            ->maxLength(255),
                                        TextInput::make('industry')
                                            ->label('Sector')
                                            ->maxLength(120)
                                            ->placeholder('bv. E-commerce, Horeca, B2B'),
                                    ]),
                                Textarea::make('excerpt')
                                    ->label('Korte beschrijving (teaser)')
                                    ->rows(3)
                                    ->maxLength(300)
                                    ->helperText('Wordt getoond op de overzichtspagina en als SEO-fallback.'),
                                MediaPickerField::make('cover_url', 'Coverafbeelding', required: false, helperText: 'Hoofdafbeelding die op de overzichtspagina getoond wordt.'),
                                TextInput::make('cover_alt')
                                    ->label('Cover — alt-tekst')
                                    ->maxLength(255),
                                TagsInput::make('tags')
                                    ->label('Tags')
                                    ->placeholder('Voeg tag toe'),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        Toggle::make('published')
                                            ->label('Gepubliceerd'),
                                        Toggle::make('featured')
                                            ->label('Uitgelicht')
                                            ->helperText('Uitgelichte cases verschijnen als eerste in het overzicht.'),
                                    ]),
                            ]),
                        Tab::make('SEO')
                            ->id('seo')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta-titel')
                                    ->maxLength(60)
                                    ->helperText('Ideaal ~60 tekens.'),
                                Textarea::make('meta_description')
                                    ->label('Meta-omschrijving')
                                    ->rows(3)
                                    ->maxLength(160)
                                    ->helperText('Ideaal ~160 tekens.'),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        Select::make('meta_robots')
                                            ->label('Meta robots')
                                            ->options([
                                                'index, follow' => 'Indexeren + volgen (standaard)',
                                                'noindex, follow' => 'Niet indexeren, wel volgen',
                                                'noindex, nofollow' => 'Niet indexeren, niet volgen',
                                                'index, nofollow' => 'Indexeren, niet volgen',
                                            ])
                                            ->placeholder('Standaard (index, follow)'),
                                        TextInput::make('canonical_url')
                                            ->label('Canonical URL')
                                            ->url()
                                            ->maxLength(255)
                                            ->helperText('Optioneel. Laat leeg om de standaard URL te gebruiken.'),
                                    ]),
                                MediaPickerField::make('seo_image_url', 'Uitgelichte afbeelding (SEO)', required: false, helperText: 'Wordt gebruikt voor sociale media previews (og:image). Fallback: coverafbeelding.'),
                                TextInput::make('seo_image_alt')
                                    ->label('SEO-afbeelding — alt-tekst')
                                    ->maxLength(255),
                                Toggle::make('is_cornerstone')
                                    ->label('Cornerstone content')
                                    ->helperText('Markeer deze case als één van de belangrijkste pagina\'s van de website.'),
                            ]),
                        Tab::make('Inhoud')
                            ->id('sections')
                            ->schema([
                                Section::make('De uitdaging')
                                    ->description('Beschrijf de situatie en het probleem van de klant vóór de samenwerking.')
                                    ->schema([
                                        Textarea::make('content.challenge.body')
                                            ->label('Tekst')
                                            ->rows(6)
                                            ->required(),
                                    ]),

                                Section::make('Projectdoelen')
                                    ->description('Wat wilden jullie samen bereiken? Elke regel is één doel.')
                                    ->schema([
                                        Repeater::make('content.goals')
                                            ->label('Doelen')
                                            ->schema([
                                                TextInput::make('text')
                                                    ->label('Doel')
                                                    ->required()
                                                    ->maxLength(200),
                                            ])
                                            ->addActionLabel('Doel toevoegen')
                                            ->reorderable()
                                            ->defaultItems(0)
                                            ->columns(1),
                                    ]),

                                Section::make('Onze aanpak')
                                    ->description('De stappen die jullie gezet hebben, in volgorde.')
                                    ->schema([
                                        Repeater::make('content.approach.steps')
                                            ->label('Stappen')
                                            ->schema([
                                                TextInput::make('title')
                                                    ->label('Titel')
                                                    ->required()
                                                    ->maxLength(100),
                                                Textarea::make('body')
                                                    ->label('Omschrijving')
                                                    ->rows(3)
                                                    ->required(),
                                            ])
                                            ->addActionLabel('Stap toevoegen')
                                            ->reorderable()
                                            ->defaultItems(0)
                                            ->columns(1),
                                    ]),

                                Section::make('De oplossing')
                                    ->description('Wat hebben jullie gebouwd of opgeleverd?')
                                    ->schema([
                                        Textarea::make('content.solution.body')
                                            ->label('Tekst')
                                            ->rows(6)
                                            ->required(),
                                        MediaPickerField::make('content.solution.image_url', 'Schermafbeelding / afbeelding', required: false, helperText: 'Optioneel: screenshot of mockup van het eindresultaat.'),
                                        TextInput::make('content.solution.image_alt')
                                            ->label('Schermafbeelding — alt-tekst')
                                            ->maxLength(255),
                                    ]),

                                Section::make('Het resultaat')
                                    ->description('Concrete cijfers en meetbare impact.')
                                    ->schema([
                                        Textarea::make('content.results.intro')
                                            ->label('Inleidende tekst')
                                            ->rows(3),
                                        Repeater::make('content.results.metrics')
                                            ->label('Meetresultaten')
                                            ->schema([
                                                Grid::make(['default' => 1, 'md' => 2])
                                                    ->schema([
                                                        TextInput::make('label')
                                                            ->label('Label')
                                                            ->required()
                                                            ->maxLength(80)
                                                            ->placeholder('bv. Online boekingen'),
                                                        TextInput::make('value')
                                                            ->label('Waarde')
                                                            ->required()
                                                            ->maxLength(40)
                                                            ->placeholder('bv. +65%'),
                                                    ]),
                                            ])
                                            ->addActionLabel('Resultaat toevoegen')
                                            ->reorderable()
                                            ->defaultItems(0),
                                    ]),

                                Section::make('Getuigenis')
                                    ->description('Quote van de klant. Laat leeg als er nog geen getuigenis is.')
                                    ->schema([
                                        Textarea::make('content.testimonial.quote')
                                            ->label('Quote')
                                            ->rows(4),
                                        Grid::make(['default' => 1, 'md' => 2])
                                            ->schema([
                                                TextInput::make('content.testimonial.name')
                                                    ->label('Naam')
                                                    ->maxLength(100),
                                                TextInput::make('content.testimonial.role')
                                                    ->label('Functie / titel')
                                                    ->maxLength(100),
                                            ]),
                                        MediaPickerField::make('content.testimonial.avatar_url', 'Profielfoto', required: false),
                                    ]),

                                Section::make('Reflectie')
                                    ->description('Waarom werkte deze aanpak? Kort en krachtig, 2-3 zinnen.')
                                    ->schema([
                                        Textarea::make('content.reflection.body')
                                            ->label('Tekst')
                                            ->rows(4),
                                        TextInput::make('content.reflection.website_url')
                                            ->label('Link naar het live project')
                                            ->url()
                                            ->maxLength(255)
                                            ->placeholder('https://www.voorbeeld.be')
                                            ->helperText('Optioneel. Wordt getoond als "Bekijk het project" onderaan de reflectie.'),
                                    ]),

                                Section::make('Call to action')
                                    ->description('De afsluiter van de case. Laat de bezoeker de volgende stap zetten.')
                                    ->schema([
                                        TextInput::make('content.cta.title')
                                            ->label('Titel')
                                            ->maxLength(120)
                                            ->default('Past deze aanpak bij jouw situatie?'),
                                        Textarea::make('content.cta.body')
                                            ->label('Tekst')
                                            ->rows(3)
                                            ->default('Laten we in een kort gesprek verkennen wat er mogelijk is voor jouw bedrijf. Geen verplichtingen, wel concrete inzichten.'),
                                        Grid::make(['default' => 1, 'md' => 2])
                                            ->schema([
                                                TextInput::make('content.cta.button_label')
                                                    ->label('Knoptekst')
                                                    ->maxLength(80)
                                                    ->default('Plan strategisch gesprek'),
                                                TextInput::make('content.cta.button_url')
                                                    ->label('Knop-URL')
                                                    ->maxLength(255)
                                                    ->default('/contact'),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
