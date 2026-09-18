<?php

use App\Filament\Pages\SeoActions;
use App\Jobs\GenerateSeoActionsJob;
use App\Mail\SeoWeeklyReport;
use App\Models\SeoActionItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\Seo\ActionBacklog;
use App\Services\SeoAdvisorService;
use App\Support\JobStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/**
 * De actielijst groeide elke week aan terwijl de vorige lading nog
 * onbeoordeeld stond. Na een paar weken keek je naar twintig kaarten, met
 * twee keer hetzelfde voorstel voor dezelfde pagina ertussen — want niets
 * hield ooit rekening met wat er al lag.
 *
 * Wat hier vastligt:
 *  - staat er nog één voorstel open, dan genereert de run niets;
 *  - is de lijst leeg, dan komen er hoogstens `seo_actions_max_open` nieuwe bij,
 *    en sneuvelt het minst belangrijke voorstel — niet het laatste in de rij;
 *  - die blokkade zit vóór de AI-call, dus een stille week kost niets;
 *  - onbeoordeelde voorstellen vervallen na `seo_actions_expire_days`, zodat
 *    één blijver de module niet voorgoed stillegt;
 *  - een teruggezet voorstel krijgt de volle termijn opnieuw.
 */
function backlogAction(string $status = 'pending', int $daysOld = 0, ?string $fingerprint = null): SeoActionItem
{
    $item = SeoActionItem::create([
        'action_type' => 'optimize_meta',
        'status' => $status,
        'priority' => 'medium',
        'title' => 'Testvoorstel',
        'problem' => 'Iets aan te scherpen.',
        'proposed' => ['meta_title' => 'Nieuw'],
        'fingerprint' => $fingerprint ?? bin2hex(random_bytes(8)),
    ]);

    if ($daysOld > 0) {
        // Via de query builder: een Eloquent-save duwt updated_at weer op "nu".
        DB::table('seo_action_items')->where('id', $item->id)->update([
            'created_at' => Carbon::now()->subDays($daysOld),
        ]);
    }

    return $item->refresh();
}

it('hands out a full batch when the list is empty', function () {
    Setting::set(ActionBacklog::LIMIT_KEY, 5);

    $backlog = app(ActionBacklog::class);

    expect($backlog->openCount())->toBe(0)
        ->and($backlog->isBlocked())->toBeFalse()
        ->and($backlog->room())->toBe(5);
});

it('does nothing at all while even one proposal is still open', function () {
    Setting::set(ActionBacklog::LIMIT_KEY, 5);
    backlogAction();

    $backlog = app(ActionBacklog::class);

    expect($backlog->isBlocked())->toBeTrue()
        ->and($backlog->room())->toBe(0);
});

it('never blocks itself forever on a limit of zero', function () {
    // Nul zou elke generatie voorgoed tegenhouden — dat mag een instelling
    // nooit stilzwijgend doen.
    Setting::set(ActionBacklog::LIMIT_KEY, 0);

    expect(app(ActionBacklog::class)->limit())->toBe(1);
});

it('only counts pending items as open', function () {
    Setting::set(ActionBacklog::LIMIT_KEY, 5);
    backlogAction('published');
    backlogAction('dismissed');
    backlogAction(ActionBacklog::STATUS_EXPIRED);
    backlogAction('pending');

    expect(app(ActionBacklog::class)->openCount())->toBe(1);
});

it('expires stale proposals so one straggler cannot block forever', function () {
    Setting::set(ActionBacklog::EXPIRE_KEY, 60);
    backlogAction(daysOld: 61);

    $backlog = app(ActionBacklog::class);
    expect($backlog->isBlocked())->toBeTrue();

    expect($backlog->expireStale())->toBe(1)
        ->and($backlog->openCount())->toBe(0)
        ->and($backlog->isBlocked())->toBeFalse();
});

it('restarts the expiry clock for a restored proposal', function () {
    Setting::set(ActionBacklog::EXPIRE_KEY, 60);
    $item = backlogAction(ActionBacklog::STATUS_EXPIRED, daysOld: 90);

    // Terugzetten zoals het scherm dat doet.
    $item->update(['status' => 'pending', 'dismissed_at' => null, 'reopened_at' => now()]);

    expect(app(ActionBacklog::class)->expireStale())->toBe(0)
        ->and(SeoActionItem::pending()->count())->toBe(1);
});

it('keeps only the highest-priority proposals when room runs short', function () {
    $advisor = app(SeoAdvisorService::class);

    $make = fn (string $priority, string $title) => [
        'action_type' => 'optimize_meta',
        'priority' => $priority,
        'title' => $title,
        'problem' => 'x',
        'proposed' => ['meta_title' => $title],
        'page_id' => null,
        'source_keyword' => null,
        'metric' => null,
        'fingerprint' => sha1($title),
    ];

    $summary = $advisor->storeActions([
        $make('low', 'Lage'),
        $make('high', 'Hoge'),
        $make('medium', 'Middelste'),
    ], null, 2);

    expect($summary['created'])->toBe(2)
        ->and($summary['skipped'])->toBe(1)
        ->and(SeoActionItem::pluck('title')->all())->toBe(['Hoge', 'Middelste']);
});

it('skips generation without calling the AI while work is still open', function () {
    Setting::set(ActionBacklog::LIMIT_KEY, 1);
    backlogAction();

    // Geen Http::fake: elke uitgaande call zou de test laten ontploffen. Dat
    // ís de assertie — een geblokkeerde week mag niets kosten.
    app(GenerateSeoActionsJob::class, ['force' => false])
        ->handle(app(SeoAdvisorService::class), app(ActionBacklog::class));

    expect(SeoActionItem::count())->toBe(1)
        ->and(JobStatus::for(GenerateSeoActionsJob::STATUS_KEY)->state())->toBe('failed');
});

it('explains on the screen why no new proposals arrive', function () {
    actingAs(User::factory()->create());
    Setting::set(ActionBacklog::LIMIT_KEY, 5);
    Setting::set(ActionBacklog::EXPIRE_KEY, 60);
    backlogAction(daysOld: 40);
    backlogAction();

    Livewire::test(SeoActions::class)
        ->assertSee('Er komen geen nieuwe voorstellen bij')
        ->assertSee('staan er bij de volgende wekelijkse analyse 5 nieuwe klaar')
        ->assertSee('vervalt automatisch op '.Carbon::now()->subDays(40)->addDays(60)->format('d/m/Y'));
});

it('offers a bulk dismiss for proposals that are past their usefulness', function () {
    actingAs(User::factory()->create());
    backlogAction(daysOld: SeoActions::STALE_DAYS + 1);
    backlogAction(daysOld: 2);

    Livewire::test(SeoActions::class)
        ->callAction('dismissOld')
        ->assertNotified();

    expect(SeoActionItem::pending()->count())->toBe(1);
});

it('still lets you force a run past open work, but keeps it capped', function () {
    Queue::fake();
    actingAs(User::factory()->create());
    Setting::set(ActionBacklog::LIMIT_KEY, 1);
    Setting::set('dataforseo_login', 'x');
    Setting::set('dataforseo_password', 'y');
    backlogAction();

    // De bevestiging is een modal, geen blokkade: de knop blijft bruikbaar.
    Livewire::test(SeoActions::class)
        ->callAction('generate')
        ->assertNotified();

    Queue::assertPushed(fn (GenerateSeoActionsJob $job) => $job->force === true);
});

it('tells the weekly mail why it has nothing new, and by when it self-clears', function () {
    $mail = new SeoWeeklyReport(
        context: ['target' => 'dewebgoeroe.be', 'latest' => null, 'stats' => []],
        advice: null,
        dashboardUrl: 'https://dewebgoeroe.be/admin/seo-actions',
        backlog: [
            'blocked' => true,
            'created' => 0,
            'expired' => 2,
            'open' => 2,
            'limit' => 5,
            'next_expiry' => Carbon::parse('2026-11-10'),
            'items' => [
                ['title' => 'FAQ uitbreiden op over-ons', 'days' => 21],
                ['title' => 'Meta-title homepage', 'days' => 1],
            ],
        ],
    );

    $html = $mail->render();

    expect($html)
        ->toContain('Deze week geen nieuwe voorstellen')
        ->toContain('staan er nog 2 open')
        ->toContain('5 nieuwe klaar')
        ->toContain('FAQ uitbreiden op over-ons')
        ->toContain('21 dagen oud')
        ->toContain('1 dag oud')
        // Datums dag-eerst, ook in de mail.
        ->toContain('10/11/2026')
        ->toContain('2 oudere voorstellen zijn vervallen');
});
