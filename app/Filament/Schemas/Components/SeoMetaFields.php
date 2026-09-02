<?php

namespace App\Filament\Schemas\Components;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;

/**
 * Meta-titel en meta-omschrijving, gedeeld door pagina's, posts en cases.
 *
 * De SEO-richtlijn (±60 / ±160 tekens) is een advies, geen blokkade: Google
 * kapt een te lange titel gewoon af. Daarom tonen we een live tekenteller
 * die oranje kleurt boven het ideaal, en valideren we enkel op wat de
 * databasekolom aankan.
 */
class SeoMetaFields
{
    public const TITLE_IDEAL = 60;

    public const DESCRIPTION_IDEAL = 160;

    public static function title(): TextInput
    {
        return TextInput::make('meta_title')
            ->label('Meta-titel')
            ->maxLength(255)
            ->live(debounce: '400ms')
            ->helperText(fn (?string $state) => self::counter($state, self::TITLE_IDEAL));
    }

    public static function description(): Textarea
    {
        return Textarea::make('meta_description')
            ->label('Meta-omschrijving')
            ->rows(3)
            ->maxLength(1000)
            ->live(debounce: '400ms')
            ->helperText(fn (?string $state) => self::counter($state, self::DESCRIPTION_IDEAL));
    }

    private static function counter(?string $state, int $ideal): HtmlString
    {
        $length = mb_strlen(trim((string) $state));

        if ($length <= $ideal) {
            return new HtmlString(sprintf('%d/%d tekens · ideaal ~%d.', $length, $ideal, $ideal));
        }

        return new HtmlString(sprintf(
            '<span style="color:#f59e0b;">%d/%d tekens · %d te lang. Google kapt het einde af, opslaan kan gewoon.</span>',
            $length, $ideal, $length - $ideal,
        ));
    }
}
