<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Twee kerntests (sinds core 0.28.1 / 0.29.0) gaan uit van de standaardconfig; deze site
    // zet de beeldvorm van tekst-en-media uit en registreert eigen vaste formuliertypes.
    ->beforeEach(function () {
        $skip = [
            'it vult een lege beeldvorm' => 'Deze site heeft geen beeldvorm op tekst-en-media (config/core.php).',
            'it zet het contactformulier om' => 'Deze site registreert eigen vaste formuliertypes (groep "Vaste formulieren").',
            'it toont het focuspunt in de hero en een galerij met links' => 'Deze site heeft een eigen hero-view zonder het focuspunt-attribuut van de core (core 0.34).',
            'it toont de menu items van het hoofdmenu op de 404 pagina' => 'Deze site is eentalig: er is geen kolom label_en op menu_items (config/core.php: enkel nl).',
        ];
        foreach ($skip as $needle => $reason) {
            if (str_contains(str_replace('_', ' ', $this->name()), $needle)) {
                $this->markTestSkipped($reason);
            }
        }
    })
    // De standaardtests van de site-basis en de Groei-module draaien vanuit
    // de packages mee.
    ->in('Feature', '../vendor/webgoeroe/core/tests/Feature', '../vendor/webgoeroe/seo-growth/tests/Feature', '../vendor/webgoeroe/reviews/tests/Feature', '../vendor/webgoeroe/ai-builder/tests/Feature', '../vendor/webgoeroe/blog/tests/Feature', '../vendor/webgoeroe/cases/tests/Feature', '../vendor/webgoeroe/chat/tests/Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
