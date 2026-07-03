<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Schemas\Components\MediaPickerField;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
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

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Blogbericht')
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
                                Textarea::make('excerpt')
                                    ->label('Inleiding / teaser')
                                    ->rows(3)
                                    ->maxLength(400)
                                    ->helperText('Wordt getoond op de overzichtspagina en als SEO-fallback.'),
                                TagsInput::make('tags')
                                    ->label('Tags')
                                    ->placeholder('Voeg tag toe'),
                                MediaPickerField::make('cover_url', 'Coverafbeelding', required: false, helperText: 'Optioneel hoofdafbeelding bovenaan het artikel.'),
                                TextInput::make('cover_alt')
                                    ->label('Cover — alt-tekst')
                                    ->maxLength(255),
                                Grid::make(['default' => 1, 'md' => 2])
                                    ->schema([
                                        Toggle::make('published')
                                            ->label('Gepubliceerd'),
                                        Toggle::make('featured')
                                            ->label('Uitgelicht')
                                            ->helperText('Uitgelichte berichten verschijnen bovenaan het overzicht.'),
                                    ]),
                                DateTimePicker::make('published_at')
                                    ->label('Publicatiedatum')
                                    ->helperText('Laat leeg voor vandaag bij publiceren.'),
                            ]),

                        Tab::make('Auteur')
                            ->id('author')
                            ->schema([
                                Section::make('Auteur')
                                    ->description('De auteur die onderaan het artikel getoond wordt.')
                                    ->schema([
                                        TextInput::make('author_name')
                                            ->label('Naam')
                                            ->required()
                                            ->maxLength(100)
                                            ->default('De Webgoeroe'),
                                        Textarea::make('author_bio')
                                            ->label('Bio')
                                            ->rows(3)
                                            ->maxLength(300)
                                            ->helperText('Korte beschrijving van de auteur.'),
                                        MediaPickerField::make('author_avatar_url', 'Profielfoto', required: false),
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
                                                'index, follow'      => 'Indexeren + volgen (standaard)',
                                                'noindex, follow'    => 'Niet indexeren, wel volgen',
                                                'noindex, nofollow'  => 'Niet indexeren, niet volgen',
                                                'index, nofollow'    => 'Indexeren, niet volgen',
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
                                    ->helperText('Markeer dit artikel als één van de belangrijkste pagina\'s van de website.'),
                            ]),

                        Tab::make('Inhoud')
                            ->id('sections')
                            ->schema([
                                RichEditor::make('body')
                                    ->label('Artikeltekst')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'underline',
                                        'strike',
                                        'link',
                                        'h2',
                                        'h3',
                                        'bulletList',
                                        'orderedList',
                                        'blockquote',
                                        'codeBlock',
                                        'undo',
                                        'redo',
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
