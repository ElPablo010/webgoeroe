<?php

namespace App\Models;

use App\Support\Attribution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;

/**
 * Grootboek van conversies op de website, met first-party herkomst
 * ("Groei"-meetlaag). Eén rij per conversie; het basistype is de
 * contactformulier-inzending, andere types (bestelling, boeking, …) voeg je
 * per project toe. Bewust minimaal: geen berichtinhoud of losse
 * persoonsgegevens — die staan al op het bronrecord.
 */
class Lead extends Model
{
    public const TYPE_CONTACT = 'contact';

    /** Nederlandse labels voor de UI — vul aan per project. */
    public const TYPE_LABELS = [
        self::TYPE_CONTACT => 'Contactvraag',
    ];

    protected $fillable = [
        'lead_type',
        'source_type',
        'source_id',
        'value',
        'channel',
        'referrer_host',
        'landing_path',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'locale',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Label voor de UI. Formulierinzendingen komen automatisch binnen met het
     * formuliertype als lead_type (zie FormSubmission::booted()), dus val terug
     * op de labels die new-website daar al bijhoudt.
     */
    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->lead_type]
            ?? (class_exists(FormSubmission::class) ? (FormSubmission::TYPE_LABELS[$this->lead_type] ?? null) : null)
            ?? ucfirst($this->lead_type);
    }

    /**
     * Registreer een conversie. Faalt nooit hard: attributie is rapportage,
     * een fout hier mag geen inzending of betaling blokkeren.
     *
     * @param  array|null  $attribution  First-touch-snapshot; null = uit de
     *                                   huidige sessie halen.
     */
    public static function record(
        string $type,
        ?Model $source = null,
        ?float $value = null,
        ?array $attribution = null,
        ?string $locale = null,
    ): ?self {
        try {
            $attribution ??= Attribution::current();

            return static::create([
                'lead_type' => $type,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'value' => $value,
                'channel' => $attribution['channel'] ?? null,
                'referrer_host' => $attribution['referrer_host'] ?? null,
                'landing_path' => $attribution['landing_path'] ?? null,
                'utm_source' => $attribution['utm_source'] ?? null,
                'utm_medium' => $attribution['utm_medium'] ?? null,
                'utm_campaign' => $attribution['utm_campaign'] ?? null,
                'locale' => $locale ?? app()->getLocale(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Lead: registratie mislukt', [
                'lead_type' => $type,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
