<?php

use App\Support\FaqQuestionMatcher;

/**
 * Het vangnet tegen SEO-voorstellen die een bestaande FAQ-vraag herhalen.
 * Woord-overlap, geen exacte tekstmatch: een vraag die enkel een detail
 * toevoegt of licht herformuleert is dezelfde vraag; een andere intentie
 * (waar vs. wat kost, of een ander vraagwoord) is een écht andere vraag.
 */
beforeEach(function () {
    $this->matcher = new FaqQuestionMatcher();
});

it('marks a question with only an extra detail as the same question', function () {
    expect($this->matcher->overlaps(
        'Wat kost een website?',
        'Wat kost een website laten maken in Antwerpen?'
    ))->toBeTrue();
});

it('ignores casing, accents and punctuation', function () {
    expect($this->matcher->overlaps(
        'Wat kost een privé-traject?',
        'wat kost een prive traject'
    ))->toBeTrue();
});

it('marks a light rephrasing as overlap', function () {
    expect($this->matcher->overlaps(
        'Moet ik zelf teksten aanleveren voor mijn website?',
        'Moet ik zelf teksten aanleveren?'
    ))->toBeTrue();
});

it('keeps questions with a different subject apart', function () {
    expect($this->matcher->overlaps(
        'Wat kost een website?',
        'Wat kost een webshop?'
    ))->toBeFalse();
});

it('keeps questions with a different intent apart', function () {
    expect($this->matcher->overlaps(
        'Wat kost een website?',
        'Hoelang duurt het bouwen van een website?'
    ))->toBeFalse();
});

it('treats interrogatives as meaningful words', function () {
    // "waar" en "wanneer" dragen de intentie: locatie vs. moment.
    expect($this->matcher->overlaps(
        'Waar kan ik terecht voor onderhoud?',
        'Wanneer kan ik terecht voor onderhoud?'
    ))->toBeFalse();
});

it('returns the clashing existing question via firstOverlapping', function () {
    $existing = [
        'Werken jullie ook voor kleine zelfstandigen?',
        'Wat kost een website?',
    ];

    expect($this->matcher->firstOverlapping('Wat kost een website laten maken?', $existing))
        ->toBe('Wat kost een website?');

    expect($this->matcher->firstOverlapping('Doen jullie ook e-mailmarketing?', $existing))->toBeNull();
});

it('covers a keyword when every meaningful word appears in the page text', function () {
    expect($this->matcher->keywordCoveredBy('webdesign antwerpen', 'Webdesigner in Antwerpen webdesigner-antwerpen'))->toBeTrue();
    expect($this->matcher->keywordCoveredBy('seo optimalisatie', 'SEO-optimalisatie voor kmo\'s seo-optimalisatie'))->toBeTrue();
});

it('does not cover a keyword with an unmatched meaningful word', function () {
    expect($this->matcher->keywordCoveredBy('webdesign mechelen', 'Webdesigner in Antwerpen webdesigner-antwerpen'))->toBeFalse();
    expect($this->matcher->keywordCoveredBy('webshop antwerpen', 'Webdesigner in Antwerpen webdesigner-antwerpen'))->toBeFalse();
});
