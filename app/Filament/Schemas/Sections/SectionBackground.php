<?php

namespace App\Filament\Schemas\Sections;

/**
 * Achtergrond-keuzes per sectie. Eén bron voor zowel de admin-dropdown
 * (options()) als de publieke styling (classes()).
 *
 * De site gebruikt een dark-mode design. 'dark' is de standaard achtergrond.
 * Houd options(), classes() en isDark() in sync.
 */
class SectionBackground
{
    public const DEFAULT = 'dark';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'dark'        => 'Donker (standaard)',
            'light'       => 'Donker licht (kaarten)',
            'primary'     => 'Primair (merkkleur)',
            'white'       => 'Wit (uitzonderlijk)',
            'transparent' => 'Transparant',
        ];
    }

    public static function classes(?string $key): string
    {
        return match ($key) {
            'light'       => 'bg-[#0c0c10] text-white',
            'primary'     => 'bg-[#050507] text-white',   // donker, consistentie over de pagina
            'white'       => 'bg-white text-slate-900',
            'transparent' => 'bg-transparent text-white',
            default       => 'bg-[#050507] text-white',
        };
    }

    public static function isDark(?string $key): bool
    {
        return $key !== 'white';
    }
}
