<?php

namespace App\Filament\Schemas\Sections;

use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;

/**
 * De pagina-secties builder: één Block per sectietype. Een nieuw sectietype
 * toevoegen = drie plekken (zie SKILL / CLAUDE.md):
 *   1. Blade-partial in resources/views/components/site/sections/<type-met-streepjes>.blade.php
 *   2. <Type>Fields::make() met de admin-velden
 *   3. een Block::make('<type_snake_case>') hieronder registreren
 *
 * De render-dispatch (pages/show.blade.php) mapt section_type → partial via
 * str_replace('_', '-', $type), dus geen route-aanpassingen nodig.
 */
class PageSectionsBuilder
{
    public static function make(): Builder
    {
        return Builder::make('sections')
            ->label('Pagina-secties')
            ->blockNumbers(false)
            ->reorderable()
            ->collapsible()
            ->collapsed()
            ->cloneable()
            ->addActionLabel('Sectie toevoegen')
            ->blocks(self::blocks())
            ->columnSpanFull()
            ->collapseAllAction(fn ($action) => $action->hidden())
            ->expandAllAction(fn ($action) => $action->hidden());
    }

    protected static function blocks(): array
    {
        return [
            Block::make('hero')
                ->label(self::numberedLabel('Hero'))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            ...SectionCommonFields::make(withBackground: false),
                            Select::make('size')
                                ->label('Grootte')
                                ->options([
                                    'compact' => 'Compact',
                                    'default' => 'Standaard',
                                ])
                                ->default('default'),
                        ]),
                    ...HeroFields::make(),
                ]),
            Block::make('text_media')
                ->label(self::numberedLabel('Tekst en media'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...TextMediaFields::make(),
                ]),
            Block::make('cards')
                ->label(self::numberedLabel('Cards'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CardsFields::make(),
                ]),
            Block::make('faq')
                ->label(self::numberedLabel('FAQ'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...FaqFields::make(),
                ]),
            Block::make('gallery')
                ->label(self::numberedLabel('Gallerij'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...GalleryFields::make(),
                ]),
            Block::make('form')
                ->label(self::numberedLabel('Formulier'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...FormFields::make(),
                ]),
            Block::make('cta')
                ->label(self::numberedLabel('Call-to-action'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CtaFields::make(),
                ]),
            Block::make('testimonials')
                ->label(self::numberedLabel('Testimonials'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...TestimonialsFields::make(),
                ]),
            Block::make('case_results')
                ->label(self::numberedLabel('Case-resultaten'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CaseResultsFields::make(),
                ]),
            Block::make('calendly')
                ->label(self::numberedLabel('Calendly'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CalendlyFields::make(),
                ]),
            Block::make('cases_grid')
                ->label(self::numberedLabel('Case studies grid'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CasesGridFields::make(),
                ]),
            Block::make('rich_text')
                ->label(self::numberedLabel('Tekst (lange inhoud)'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...RichTextFields::make(),
                ]),
        ];
    }

    protected static function numberedLabel(string $label): Closure
    {
        return fn (?int $index = null): string => $index === null
            ? $label
            : ($index + 1).' - '.$label;
    }
}
