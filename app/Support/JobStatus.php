<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * De stand van een achtergrondtaak die een knop in de admin start.
 *
 * Bewust in `settings` en niet in de sessie of een Livewire-property: een
 * knop die "dit duurt enkele minuten" zegt moet dat óók nog tonen wanneer je
 * het scherm verlaat, een kwartier iets anders doet en terugkomt. Een
 * Livewire-property overleeft dat niet, en zonder die persistentie zie je bij
 * terugkomst een leeg scherm dat niet te onderscheiden is van "er is niets
 * gestart".
 *
 * Even belangrijk is de staleness-detectie: op shared hosting hangt de queue
 * aan de cron (`schedule:run` → `queue:work --stop-when-empty`). Draait die
 * niet, dan blijft de job gewoon in de tabel staan zonder ooit te falen. Een
 * indicator die dan eeuwig "bezig" blijft zeggen liegt; daarom kantelt
 * `queued` na STALE_QUEUED_MINUTES naar een expliciete waarschuwing dat er
 * niemand aan het werk is.
 */
class JobStatus
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    /**
     * Zolang mag een taak in de wachtrij staan voor we ze als vastgelopen
     * beschouwen. De worker start elke minuut, dus drie minuten wachten is
     * ruim genoeg om een trage cron niet vals te beschuldigen.
     */
    public const STALE_QUEUED_MINUTES = 3;

    /**
     * Zolang mag ze draaien. Ruimer dan de job-timeout (300s) zodat een job
     * die netjes in z'n timeout loopt zelf nog `failed()` kan wegschrijven.
     */
    public const STALE_RUNNING_MINUTES = 15;

    public function __construct(public readonly string $key) {}

    public static function for(string $key): self
    {
        return new self($key);
    }

    public function queued(): void
    {
        $this->write(self::QUEUED);
    }

    public function running(): void
    {
        $this->write(self::RUNNING);
    }

    public function done(?string $message = null): void
    {
        $this->write(self::DONE, $message);
    }

    public function failed(string $message): void
    {
        $this->write(self::FAILED, $message);
    }

    public function clear(): void
    {
        Setting::set($this->key, null);
    }

    public function state(): ?string
    {
        return $this->stored()['state'];
    }

    public function message(): ?string
    {
        return $this->stored()['message'];
    }

    /** Sinds wanneer de taak in deze stand staat. */
    public function since(): ?Carbon
    {
        $at = $this->stored()['at'];

        return $at ? Carbon::parse($at) : null;
    }

    /** Loopt de taak nog — en is dat nog geloofwaardig? */
    public function isBusy(): bool
    {
        return in_array($this->state(), [self::QUEUED, self::RUNNING], true) && ! $this->isStale();
    }

    /**
     * De taak staat als bezig genoteerd maar is te lang blijven hangen.
     * Vrijwel altijd een queue-worker die niet draait.
     */
    public function isStale(): bool
    {
        $since = $this->since();
        if (! $since) {
            return false;
        }

        return match ($this->state()) {
            self::QUEUED => $since->diffInMinutes(now()) >= self::STALE_QUEUED_MINUTES,
            self::RUNNING => $since->diffInMinutes(now()) >= self::STALE_RUNNING_MINUTES,
            default => false,
        };
    }

    protected function write(string $state, ?string $message = null): void
    {
        Setting::set($this->key, [
            'state' => $state,
            'message' => $message,
            'at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @return array{state: ?string, message: ?string, at: ?string}
     */
    protected function stored(): array
    {
        $data = Setting::get($this->key);

        if (! is_array($data)) {
            return ['state' => null, 'message' => null, 'at' => null];
        }

        return [
            'state' => is_string($data['state'] ?? null) ? $data['state'] : null,
            'message' => is_string($data['message'] ?? null) ? $data['message'] : null,
            'at' => is_string($data['at'] ?? null) ? $data['at'] : null,
        ];
    }
}
