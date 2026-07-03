<?php

namespace App\Filament\Schemas\Sections;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;

class RepeaterToggleStyle
{
    public static function make(): Closure
    {
        return fn (Action $action): Action => $action
            ->size(Size::ExtraSmall)
            ->extraAttributes(['class' => 'opacity-60 hover:opacity-100']);
    }
}
