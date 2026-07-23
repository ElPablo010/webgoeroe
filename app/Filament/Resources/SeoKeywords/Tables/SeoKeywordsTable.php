<?php

namespace App\Filament\Resources\SeoKeywords\Tables;

use App\Models\SeoKeyword;
use App\Models\SeoKeywordResult;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeoKeywordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load de laatste meting: zonder dit doet elke rij zijn eigen
            // query voor rank, volume en AI-status.
            ->modifyQueryUsing(fn (Builder $query) => $query->with('latestResult'))
            ->defaultSort('keyword')
            ->columns([
                TextColumn::make('keyword')
                    ->label('Zoekwoord')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('tag')
                    ->label('Groep')
                    ->badge()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('latestResult.rank_group')
                    ->label('Positie')
                    ->placeholder('Niet in top 100')
                    ->sortable(query: fn (Builder $query, string $direction) => static::sortByRank($query, $direction))
                    ->formatStateUsing(fn (?int $state) => $state !== null ? '#'.$state : null)
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state <= 3 => 'success',
                        $state <= 10 => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('delta')
                    ->label('Beweging')
                    ->state(fn (SeoKeyword $record) => $record->latestResult?->delta)
                    ->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state === null || $state === 0
                        ? null
                        : ($state > 0 ? '+'.$state : (string) $state))
                    ->color(fn (?int $state) => match (true) {
                        $state === null || $state === 0 => 'gray',
                        $state > 0 => 'success',
                        default => 'danger',
                    })
                    ->icon(fn (?int $state) => match (true) {
                        $state === null || $state === 0 => null,
                        $state > 0 => Heroicon::ArrowTrendingUp,
                        default => Heroicon::ArrowTrendingDown,
                    }),

                TextColumn::make('latestResult.search_volume')
                    ->label('Zoekvolume')
                    ->placeholder('—')
                    ->numeric(thousandsSeparator: '.')
                    ->toggleable(),

                IconColumn::make('latestResult.ai_overview_cited')
                    ->label('In AI-antwoord')
                    ->boolean()
                    ->tooltip('Wordt je site geciteerd in Google\'s AI Overview?')
                    ->toggleable(),

                TextColumn::make('latestResult.url')
                    ->label('Rankende pagina')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn (?string $state) => $state)
                    ->url(fn (?string $state) => $state, shouldOpenInNewTab: true)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('latestResult.checked_at')
                    ->label('Gemeten')
                    ->date('d/m/Y')
                    ->placeholder('Nog niet gemeten')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('tag')
                    ->label('Groep')
                    ->options(fn () => SeoKeyword::query()
                        ->whereNotNull('tag')
                        ->distinct()
                        ->orderBy('tag')
                        ->pluck('tag', 'tag')
                        ->all()),

                TernaryFilter::make('is_active')
                    ->label('Actief opvolgen')
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make()->button()->hiddenLabel()->tooltip('Bewerken'),
                DeleteAction::make()->button()->hiddenLabel()->tooltip('Verwijderen'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nog geen keywords')
            ->emptyStateDescription('Voeg de zoektermen toe waarop je gevonden wil worden. Daarna verschijnen hier je posities.');
    }

    /**
     * Sorteer op de positie uit de meest recente meting.
     *
     * Een gewone relatie-sort volstaat niet: we hebben per keyword maar één
     * regel nodig (de laatste), dus we sorteren op een gecorreleerde subquery.
     * Keywords zonder meting hebben `null` en belanden zo achteraan bij
     * oplopend sorteren — precies waar je ze wil.
     */
    protected static function sortByRank(Builder $query, string $direction): Builder
    {
        $subquery = SeoKeywordResult::query()
            ->select('rank_group')
            ->whereColumn('seo_keyword_results.seo_keyword_id', 'seo_keywords.id')
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->limit(1);

        return $query->orderByRaw(
            '('.$subquery->toSql().') IS NULL, ('.$subquery->toSql().') '.($direction === 'desc' ? 'desc' : 'asc'),
            [...$subquery->getBindings(), ...$subquery->getBindings()],
        );
    }
}
