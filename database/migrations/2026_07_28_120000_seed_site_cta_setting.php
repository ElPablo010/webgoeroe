<?php

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Setting;
use App\Support\SiteCta;
use Illuminate\Database\Migrations\Migration;

/**
 * Zet de site-brede CTA op de adviesgesprek-pagina en laat de bestaande cases
 * die knop erven.
 *
 * De CTA onderaan blog- en case-pagina's wees eerder naar /contact (hardcoded
 * in de blade-view, en als default in het case-formulier). Vanaf nu staat de
 * bestemming één keer in Instellingen → Algemeen; per-case velden zijn enkel
 * nog een override. Deze migratie wist daarom de oude standaardwaarden op de
 * bestaande cases, zodat ze de nieuwe instelling volgen.
 */
return new class extends Migration
{
    /** De waarden die het case-formulier vroeger automatisch invulde. */
    private const OLD_BUTTON_URL = '/contact';

    private const OLD_BUTTON_LABEL = 'Plan strategisch gesprek';

    public function up(): void
    {
        $this->seedSetting();
        $this->inheritCaseButtons();
    }

    public function down(): void
    {
        // Content-migratie: niet terug te draaien zonder de oude, per-case
        // waarden te raden. Bewust een no-op.
    }

    private function seedSetting(): void
    {
        if (Setting::query()->where('key', SiteCta::KEY)->exists()) {
            return;
        }

        // Koppel aan de pagina zelf (niet aan een pad), zodat de knop blijft
        // kloppen wanneer de slug later wijzigt.
        $page = Page::query()
            ->whereIn('slug', ['gratis-adviesgesprek', 'adviesgesprek'])
            ->orderByRaw("slug = 'gratis-adviesgesprek' desc")
            ->first();

        Setting::set(SiteCta::KEY, [
            ...SiteCta::defaults(),
            ...($page !== null
                ? ['link_type' => 'page', 'page_id' => $page->id, 'href' => '/'.$page->slug]
                : []),
        ]);
    }

    private function inheritCaseButtons(): void
    {
        foreach (CaseStudy::query()->cursor() as $case) {
            $content = $case->content;

            if (! is_array($content) || ! isset($content['cta'])) {
                continue;
            }

            $changed = false;

            if (($content['cta']['button_url'] ?? null) === self::OLD_BUTTON_URL) {
                $content['cta']['button_url'] = null;
                $changed = true;
            }

            if (($content['cta']['button_label'] ?? null) === self::OLD_BUTTON_LABEL) {
                $content['cta']['button_label'] = null;
                $changed = true;
            }

            if ($changed) {
                $case->content = $content;
                $case->save();
            }
        }
    }
};
