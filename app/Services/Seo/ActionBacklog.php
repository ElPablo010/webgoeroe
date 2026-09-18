<?php

namespace App\Services\Seo;

use App\Models\SeoActionItem;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Bewaakt hoeveel verbeteracties er tegelijk mogen openstaan.
 *
 * Het echte probleem was niet dat voorstellen op elkaar leken, maar dat ze
 * bleven aangroeien: elke week kwam er een verse lading bij terwijl de vorige
 * nog onbeoordeeld stond. Na een paar weken kijk je naar twintig kaarten, en
 * dán pas valt op dat er twee keer hetzelfde in staat — want niets hield ooit
 * rekening met wat er al lag.
 *
 * Vandaar twee simpele regels: staat er nog één voorstel open, dan gebeurt er
 * niets; is de lijst leeg, dan komen er hoogstens LIMIT_KEY nieuwe bij. Dat
 * lost de overlap meteen mee op: een tweede voorstel voor dezelfde pagina kan
 * alleen ontstaan zolang het eerste er nog staat, en dat gebeurt nu niet meer.
 *
 * De grens zit vóór de AI-call, niet erna. Een overgeslagen week kost dus
 * niets — waar hij vroeger een volledige generatie betaalde (het model
 * schrijft landingspagina's uit) om de uitkomst daarna als duplicaat weg te
 * gooien.
 *
 * Tegengif tegen de keerzijde: een lijst die vol blijft staan zou alles
 * blokkeren. Daarom vervalt een voorstel na EXPIRE_DAYS vanzelf. Dat is geen
 * opruimkosmetiek — een actie van twee maanden oud is gebouwd op posities en
 * een site die intussen allebei verschoven zijn, dus die wil je sowieso niet
 * meer uitvoeren.
 */
class ActionBacklog
{
    /** Hoeveel acties er maximaal tegelijk mogen openstaan. */
    public const LIMIT_KEY = 'seo_actions_max_open';

    public const DEFAULT_LIMIT = 5;

    /** Na hoeveel dagen een onbeoordeeld voorstel vanzelf vervalt. */
    public const EXPIRE_KEY = 'seo_actions_expire_days';

    public const DEFAULT_EXPIRE_DAYS = 60;

    /** Status van een voorstel dat is blijven liggen tot het achterhaald was. */
    public const STATUS_EXPIRED = 'expired';

    /**
     * Vanaf welk moment de vervalklok loopt. Voor een teruggezet voorstel is
     * dat het moment van terugzetten — anders zou het bij de eerstvolgende run
     * meteen opnieuw vervallen en was "Terugzetten" een knop die niets doet.
     */
    protected const CLOCK = 'COALESCE(reopened_at, created_at)';

    public function limit(): int
    {
        $value = (int) Setting::get(self::LIMIT_KEY, self::DEFAULT_LIMIT);

        // Nul zou elke generatie voorgoed blokkeren; dat is nooit de bedoeling
        // van deze instelling en kost je stilzwijgend de hele module.
        return max(1, $value);
    }

    public function expireDays(): int
    {
        return max(1, (int) Setting::get(self::EXPIRE_KEY, self::DEFAULT_EXPIRE_DAYS));
    }

    public function openCount(): int
    {
        return SeoActionItem::pending()->count();
    }

    /** Hoeveel nieuwe acties er deze run mogen bijkomen. */
    public function room(): int
    {
        return $this->isBlocked() ? 0 : $this->limit();
    }

    /**
     * Ligt er nog werk? Dan slaat de generatie volledig over.
     *
     * Bewust één openstaand voorstel als drempel, niet "tot de grens vol is":
     * aanvullen tot vijf betekent dat de lijst nooit op nul komt, en dan ben je
     * dus ook nooit klaar. Nu wél: werk je hem af, dan is hij leeg tot de
     * eerstvolgende maandag.
     */
    public function isBlocked(): bool
    {
        return $this->openCount() > 0;
    }

    /**
     * De openstaande voorstellen, oudste eerst — voor de weekmail en voor de
     * uitleg op het scherm.
     *
     * @return Collection<int,SeoActionItem>
     */
    public function openItems(): Collection
    {
        return SeoActionItem::pending()->oldest()->get();
    }

    /**
     * Zet onbeoordeelde voorstellen die ouder zijn dan de vervaltermijn op
     * `expired`. Geeft terug hoeveel er vervallen zijn.
     */
    public function expireStale(): int
    {
        return SeoActionItem::pending()
            ->whereRaw(self::CLOCK.' < ?', [Carbon::now()->subDays($this->expireDays())])
            ->update([
                'status' => self::STATUS_EXPIRED,
                'dismissed_at' => Carbon::now(),
            ]);
    }

    /**
     * Wanneer het oudste openstaande voorstel vervalt — de mail gebruikt dit
     * om te zeggen tot wanneer je nog kunt beslissen.
     */
    public function nextExpiryAt(): ?Carbon
    {
        $oldest = SeoActionItem::pending()
            ->selectRaw('MIN('.self::CLOCK.') as clock')
            ->value('clock');

        return $oldest ? Carbon::parse($oldest)->addDays($this->expireDays()) : null;
    }
}
