<?php

namespace App\Filament\Schemas\Components;

use App\Models\Page;
use App\Support\Url;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class PageLinkField
{
    /**
     * @param  bool  $required  Whether a destination must be filled in. Keep
     *                          true for dedicated button rows (CtaLinkSchema);
     *                          pass false where the link hangs off an optional
     *                          CTA (cards, tiles, tarieven) so a section without
     *                          a button can be saved without choosing a page.
     */
    public static function make(bool $required = true): Grid
    {
        return Grid::make(['default' => 1, 'md' => 3])
            ->schema([
                Select::make('link_type')
                    ->label('Type')
                    ->options([
                        'page' => 'Pagina',
                        'url' => 'Aangepaste URL',
                    ])
                    ->default('page')
                    ->live(),

                Select::make('page_id')
                    ->label('Kies een pagina')
                    ->options(fn () => Page::query()
                        ->where(fn ($q) => $q->where('published', true)->orWhere('is_homepage', true))
                        ->orderBy('title')
                        ->pluck('title', 'id'))
                    ->searchable()
                    ->placeholder('Kies een pagina...')
                    ->visible(fn (callable $get) => ($get('link_type') ?? 'page') === 'page')
                    ->required(fn (callable $get) => $required && ($get('link_type') ?? 'page') === 'page')
                    ->live()
                    ->columnSpan(['default' => 1, 'md' => 2])
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $page = Page::find($state);
                        if ($page !== null) {
                            $set('href', $page->is_homepage ? '/' : '/'.$page->slug);
                        }
                    }),

                TextInput::make('href')
                    ->label('URL')
                    ->visible(fn (callable $get) => ($get('link_type') ?? 'page') === 'url')
                    ->required(fn (callable $get) => $required && $get('link_type') === 'url')
                    ->maxLength(255)
                    ->columnSpan(['default' => 1, 'md' => 2])
                    ->placeholder('/over-ons of https://...')
                    ->helperText('Interne pagina? Start met "/". Externe site? Plak de volledige URL — zonder https:// wordt die automatisch aangevuld.')
                    // Een kale domeinnaam (www.example.be) → externe https-link, zodat
                    // de browser hem niet als relatief pad achter de huidige URL plakt.
                    ->dehydrateStateUsing(fn (?string $state): ?string => Url::normalize($state)),
            ]);
    }
}
