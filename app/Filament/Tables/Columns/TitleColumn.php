<?php

namespace App\Filament\Tables\Columns;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * De titelkolom van een overzichtstabel: klikbaar naar het bewerkscherm, met
 * een maximumbreedte zodat één lange titel de rest van de kolommen niet
 * wegduwt (lange titels wrappen dan over twee regels).
 *
 * De breedte staat als inline style: de app-Tailwind wordt niet in de
 * Filament-admin geladen, dus utility-classes zijn hier geen betrouwbare weg.
 *
 * Gedeeld door Pagina's, Blog en Cases — bel er gerust extra's achteraan
 * (bv. het homepage-icoontje in PagesTable).
 */
class TitleColumn
{
    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource  De resource waarvan het bewerkscherm geopend wordt.
     */
    public static function make(string $resource, string $attribute = 'title', string $label = 'Titel'): TextColumn
    {
        return TextColumn::make($attribute)
            ->label($label)
            ->searchable()
            ->sortable()
            ->wrap()
            ->extraCellAttributes(['style' => 'max-width: 28rem;'])
            ->url(fn (Model $record): string => $resource::getUrl('edit', ['record' => $record]));
    }
}
