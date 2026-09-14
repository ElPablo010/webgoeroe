<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

/**
 * De Google-inloggegevens verhuizen van `gsc_*` naar `google_*`.
 *
 * Search Console was de eerste dienst die op Google inlogde, dus droegen de
 * sleutels zijn naam. Nu Analytics op dezelfde koppeling meelift — één
 * toestemming, één refresh token, twee rechten — klopt die naam niet meer.
 *
 * Wat NIET meeverhuist: `gsc_site_url` blijft van Search Console (welke
 * property je daar volgt), net zoals `ga4_property_id` van Analytics is.
 *
 * De oude rijen worden verwijderd zodat er geen tweede, stille kopie van een
 * refresh token blijft rondslingeren. down() zet ze terug.
 */
return new class extends Migration
{
    /** @var array<int,string> */
    protected array $suffixes = [
        'oauth_client_id',
        'oauth_client_secret',
        'refresh_token',
        'service_account_json',
    ];

    public function up(): void
    {
        foreach ($this->suffixes as $suffix) {
            $this->move('gsc_'.$suffix, 'google_'.$suffix);
        }
    }

    public function down(): void
    {
        foreach ($this->suffixes as $suffix) {
            $this->move('google_'.$suffix, 'gsc_'.$suffix);
        }

        Setting::query()->where('key', 'google_oauth_scopes')->delete();
    }

    /** Verplaats één sleutel, zonder een bestaande waarde op de bestemming te overschrijven. */
    protected function move(string $from, string $to): void
    {
        $source = Setting::query()->where('key', $from)->first();

        if (! $source) {
            return;
        }

        if (! Setting::query()->where('key', $to)->exists()) {
            Setting::set($to, $source->value);
        }

        $source->delete();
        Cache::forget("setting.{$from}");
    }
};
