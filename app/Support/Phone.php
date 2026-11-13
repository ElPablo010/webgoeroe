<?php

namespace App\Support;

/**
 * Turns a phone number as typed in the admin into a valid tel: link.
 *
 * The visible number stays as entered; only the href is normalised to E.164.
 * Belgian conventions: "0456 95 25 54", "+32 0456 95 25 54" and
 * "+32 (0)456 95 25 54" all become "+32456952554".
 */
class Phone
{
    public const DEFAULT_COUNTRY_CODE = '32';

    /** E.164 form (e.g. "+32456952554"), or null when there are no digits. */
    public static function e164(?string $number, string $countryCode = self::DEFAULT_COUNTRY_CODE): ?string
    {
        $number = trim((string) $number);

        // "(0)" is the trunk prefix notation: never dialled after a country code.
        $number = preg_replace('/\(\s*0\s*\)/', '', $number);

        $international = str_starts_with($number, '+');
        $digits = preg_replace('/\D/', '', $number);

        if ($digits === '') {
            return null;
        }

        if (! $international && str_starts_with($digits, '00')) {
            $international = true;
            $digits = substr($digits, 2);
        }

        if (! $international) {
            // National number: drop the leading trunk 0 and add the country code.
            return '+' . $countryCode . ltrim($digits, '0');
        }

        // "+32 0456 …": a Belgian number never has a 0 after the country code.
        if (str_starts_with($digits, $countryCode . '0')) {
            $digits = $countryCode . ltrim(substr($digits, strlen($countryCode)), '0');
        }

        return '+' . $digits;
    }

    /** "tel:+32456952554", or null when the number is empty. */
    public static function telHref(?string $number): ?string
    {
        $e164 = self::e164($number);

        return $e164 === null ? null : 'tel:' . $e164;
    }
}
