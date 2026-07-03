<?php

namespace App\Filament\Resources\WebsiteMedia\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class WebsiteMediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('upload')
                    ->label('Afbeelding')
                    ->image()
                    ->required()
                    ->maxSize(20 * 1024)
                    ->disk('local')
                    ->directory('tmp-uploads')
                    ->helperText('Wordt automatisch geconverteerd naar WebP + JPG fallback (max 2400 px breed).')
                    ->columnSpanFull(),
            ]);
    }
}
