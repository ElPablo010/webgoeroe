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
 *   3. een '<Label>' => Block::make('<type_snake_case>') hieronder registreren
 *      (gekeyed op label — de lijst wordt alfabetisch gesorteerd, zie blocks())
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

    /**
     * De blocks worden gekeyed op hun label en daarna alfabetisch gesorteerd
     * (project-conventie: dropdowns alfabetisch), zodat de "Sectie toevoegen"-
     * lijst voorspelbaar blijft ongeacht de volgorde hieronder.
     */
    protected static function blocks(): array
    {
        $blocks = [
            'Hero' => Block::make('hero')
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
            'Tekst en media' => Block::make('text_media')
                ->label(self::numberedLabel('Tekst en media'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...TextMediaFields::make(),
                ]),
            'Cards' => Block::make('cards')
                ->label(self::numberedLabel('Cards'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CardsFields::make(),
                ]),
            'FAQ' => Block::make('faq')
                ->label(self::numberedLabel('FAQ'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...FaqFields::make(),
                ]),
            'Gallerij' => Block::make('gallery')
                ->label(self::numberedLabel('Gallerij'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...GalleryFields::make(),
                ]),
            'Formulier' => Block::make('form')
                ->label(self::numberedLabel('Formulier'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...FormFields::make(),
                ]),
            'Call-to-action' => Block::make('cta')
                ->label(self::numberedLabel('Call-to-action'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CtaFields::make(),
                ]),
            'Testimonials' => Block::make('testimonials')
                ->label(self::numberedLabel('Testimonials'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...TestimonialsFields::make(),
                ]),
            'Case-resultaten' => Block::make('case_results')
                ->label(self::numberedLabel('Case-resultaten'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CaseResultsFields::make(),
                ]),
            'Calendly' => Block::make('calendly')
                ->label(self::numberedLabel('Calendly'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CalendlyFields::make(),
                ]),
            'Case studies grid' => Block::make('cases_grid')
                ->label(self::numberedLabel('Case studies grid'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...CasesGridFields::make(),
                ]),
            'Tekst (lange inhoud)' => Block::make('rich_text')
                ->label(self::numberedLabel('Tekst (lange inhoud)'))
                ->schema([
                    ...SectionCommonFields::make(),
                    ...RichTextFields::make(),
                ]),
        ];

        uksort($blocks, fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return array_values($blocks);
    }

    protected static function numberedLabel(string $label): Closure
    {
        return fn (?int $index = null): string => $index === null
            ? $label
            : ($index + 1).' - '.$label;
    }
}
