<?php

namespace App\Support;

use Webgoeroe\Core\Support\Seo;

/**
 * De pagina /ai-coaching (1-op-1 AI-coaching, in de ik-vorm: de enige pagina die
 * een persoon verkoopt). De FAQPage- en BreadcrumbList-schema komen uit de core;
 * hier komt de Service met de twee prijzen als Offer bij.
 *
 * De prijzen staan ook als tekst in de prijzenkaarten van de pagina
 * (migratie create_ai_coaching_page): wijzig je ze, wijzig ze dan op beide plekken.
 */
class AiCoaching
{
    public const SLUG = 'ai-coaching';

    /** Startsessie: 2 uur, € 250 excl. btw. */
    public const START_PRICE = 250;

    /** Vervolgcoaching: € 75 per uur excl. btw. */
    public const FOLLOW_UP_PRICE = 75;

    /**
     * @param  array<int, array<string, mixed>>  $nodes  de schema-nodes van de pagina
     * @return array<int, array<string, mixed>>
     */
    public static function graph(array $nodes, mixed $page): array
    {
        if (($page->slug ?? null) !== self::SLUG) {
            return $nodes;
        }

        $url = Seo::absoluteUrl(Seo::localizedPath($page));

        $offer = fn (string $name, int $price, string $description, ?string $unit = null): array => [
            '@type' => 'Offer',
            'name' => $name,
            'description' => $description,
            'price' => (string) $price,
            'priceCurrency' => 'EUR',
            'priceSpecification' => array_filter([
                '@type' => 'UnitPriceSpecification',
                'price' => (string) $price,
                'priceCurrency' => 'EUR',
                'valueAddedTaxIncluded' => false,
                'unitCode' => $unit,
            ], fn ($v) => $v !== null),
            'url' => $url.'#agenda',
        ];

        $service = [
            '@type' => 'Service',
            '@id' => $url.'#service',
            'name' => "1-op-1 AI-coaching voor zelfstandigen en kmo's",
            'serviceType' => 'AI-coaching',
            'description' => 'Persoonlijke coaching online: we zetten Claude samen op voor jouw bedrijf en automatiseren je eerste taak.',
            'url' => $url,
            'provider' => ['@id' => Seo::baseUrl().'/#business'],
            'areaServed' => ['@type' => 'Country', 'name' => 'België'],
            'offers' => [
                $offer('Startsessie', self::START_PRICE, 'Twee uur online, voorbereid. Claude opgezet voor jouw bedrijf, je eerste taak geautomatiseerd en een lijst voor daarna. Prijs excl. btw.'),
                $offer('Vervolgcoaching', self::FOLLOW_UP_PRICE, 'Eén uur online wanneer je het nodig hebt, zonder minimum of contract. Prijs per uur, excl. btw.', 'HUR'),
            ],
        ];

        // Na de WebPage-node, vóór de FAQ en de kruimels.
        array_splice($nodes, 1, 0, [$service]);

        return $nodes;
    }
}
