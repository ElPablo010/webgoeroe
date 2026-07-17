<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Laat zowel een volledige http(s)-URL toe als een relatief pad uit de eigen
 * medialibrary ("/storage/..."). MediaPickerField en WebsiteMediaService slaan
 * media relatief op, zodat content portabel blijft bij een domeinwissel.
 */
class MediaUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (Str::startsWith($value, '/storage/')) {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) !== false
            && Str::startsWith(strtolower($value), ['http://', 'https://'])) {
            return;
        }

        $fail('Geef een volledige http(s)-URL op, of een media-pad uit de library (/storage/...).');
    }
}
