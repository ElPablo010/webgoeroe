<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * De Bottleneck Scan boekt voortaan in de GoHighLevel-agenda "Bottleneck Scan"
 * in plaats van in Calendly: een boeking landt dan onder hetzelfde contact in
 * GHL, waardoor de workflows de opvolgmails kunnen stoppen (tag scan-geboekt).
 *
 * Content-migratie zodat dit met een gewone deploy live komt, zonder db-push.
 * Enkel de boeking-sectie van /bottleneck-scan die nog naar Calendly wijst; een
 * pagina die al in de admin is aangepast, blijft zoals ze is.
 */
return new class extends Migration
{
    private const SLUG = 'bottleneck-scan';

    private const CALENDLY_URL = 'https://calendly.com/dewebgoeroe/bottleneck-scan';

    /** GHL-agenda "Bottleneck Scan" (iframe-URL). */
    private const GHL_URL = 'https://api.leadconnectorhq.com/widget/booking/Gpi00N2idJSnzaRZsA9a';

    public function up(): void
    {
        $this->switch('calendly', self::CALENDLY_URL, 'ghl', self::GHL_URL);
    }

    public function down(): void
    {
        $this->switch('ghl', self::GHL_URL, 'calendly', self::CALENDLY_URL);
    }

    private function switch(string $fromProvider, string $fromUrl, string $toProvider, string $toUrl): void
    {
        $page = Page::query()->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        foreach ($page->sections()->where('section_type', 'booking')->get() as $section) {
            $content = $section->content;

            if (($content['provider'] ?? null) !== $fromProvider || ($content['url'] ?? null) !== $fromUrl) {
                continue;
            }

            $section->update(['content' => [...$content, 'provider' => $toProvider, 'url' => $toUrl]]);
        }
    }
};
