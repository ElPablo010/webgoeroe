<?php

use App\Support\Phone;

it('builds a valid E.164 tel link', function (?string $input, ?string $expected) {
    expect(Phone::telHref($input))->toBe($expected);
})->with([
    'country code with trunk zero' => ['+32 0456 95 25 54', 'tel:+32456952554'],
    'country code with (0)' => ['+32 (0)456 95 25 54', 'tel:+32456952554'],
    'already correct' => ['+32 456 95 25 54', 'tel:+32456952554'],
    'national Belgian number' => ['0456 95 25 54', 'tel:+32456952554'],
    'dots, slashes and dashes' => ['0456/95.25-54', 'tel:+32456952554'],
    '00 international prefix' => ['0032 456 95 25 54', 'tel:+32456952554'],
    'landline' => ['03 123 45 67', 'tel:+3231234567'],
    'other country untouched' => ['+31 6 12345678', 'tel:+31612345678'],
    'empty' => ['', null],
    'null' => [null, null],
]);
