<?php

use App\Digest\DigestBuilder;
use App\Enums\Priority;
use App\Livewire\Settings\Digest;
use App\Mail\DailyDigest;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use App\Services\TimeTracker;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-11-04 06:30'); // Wednesday
    Mail::fake();
    $this->user = User::factory()->create(['name' => 'Peter']);
});

function task(User $user, string $title, Priority $priority = Priority::P3, ?string $due = null, int $position = 0): Task
{
    return Task::factory()->for($user)->priority($priority, $position)->create(['title' => $title, 'due_date' => $due]);
}

it('puts everything overdue or due today first, then tops up to five', function () {
    foreach (range(1, 6) as $i) {
        task($this->user, "Dnes {$i}", due: '2026-11-04', position: $i);
    }
    task($this->user, 'P1 úloha', Priority::P1);
    task($this->user, 'Budúci týždeň', due: '2026-11-09');
    task($this->user, 'O mesiac', due: '2026-12-04');
    task($this->user, 'Hotová', due: '2026-11-03')->forceFill(['completed_at' => now()])->save();

    $digest = app(DigestBuilder::class)->build($this->user);

    expect($digest->focus->pluck('title')->all())->toHaveCount(6)->each->toStartWith('Dnes')
        ->and($digest->upcoming->pluck('title')->all())->toBe(['Budúci týždeň'])
        ->and($digest->dueTodayCount)->toBe(6)
        ->and($digest->overdueCount)->toBe(0)
        ->and($digest->p1Count)->toBe(1);
});

it('summarises yesterday and meetings waiting for review', function () {
    $t = task($this->user, 'Kódovanie');
    app(TimeTracker::class)->log($t, 90, now()->subDay());
    app(TimeTracker::class)->log($t, 30, now()->subDays(2));
    $meeting = Meeting::factory()->ready()->for($this->user)->create(['title' => 'Sync']);
    $meeting->actionItems()->createMany([['title' => 'A'], ['title' => 'B', 'status' => 'dismissed']]);

    $digest = app(DigestBuilder::class)->build($this->user);

    expect($digest->trackedYesterdayTotal())->toBe(5400)
        ->and($digest->trackedYesterday->all())->toBe(['Bez projektu' => 5400])
        ->and($digest->meetingsToReview->pluck('pending_items_count', 'title')->all())->toBe(['Sync' => 1]);
});

it('renders the e-mail with a useful subject', function () {
    task($this->user, 'Zaplatiť faktúru', due: '2026-11-02');
    task($this->user, 'Opraviť *bug*', Priority::P1, '2026-11-04');

    $mail = new DailyDigest(app(DigestBuilder::class)->build($this->user));

    expect($mail->envelope()->subject)->toBe('Streda 4. 11.: 1 po termíne, 1 na dnes, 1× P1');
    $mail->assertSeeInHtml('Dobré ráno, Peter')
        ->assertSeeInHtml('Zaplatiť faktúru')
        ->assertSeeInHtml('po termíne (2. 11.)')
        ->assertSeeInHtml('Opraviť *bug*')
        ->assertSeeInHtml(route('digest.edit'));
});

it('sends once a day after the chosen time, skipping weekends by default', function () {
    task($this->user, 'Niečo');
    $this->user->update(['digest_time' => '07:00']);

    $this->artisan('digest:send');
    Mail::assertNothingSent();

    $this->travelTo('2026-11-04 07:00');
    $this->artisan('digest:send');
    $this->artisan('digest:send');
    Mail::assertSentCount(1);
    Mail::assertSent(DailyDigest::class, fn ($mail) => $mail->hasTo($this->user->email));

    $this->travelTo('2026-11-07 09:00'); // Saturday
    $this->artisan('digest:send');
    Mail::assertSentCount(1);

    $this->user->update(['digest_weekends' => true]);
    $this->artisan('digest:send');
    Mail::assertSentCount(2);
});

it('skips users who turned it off or have nothing to do', function () {
    $this->travelTo('2026-11-04 08:00');
    $off = User::factory()->create(['digest_enabled' => false]);
    task($off, 'Niečo');

    $this->artisan('digest:send');

    Mail::assertNothingSent();
    expect($this->user->fresh()->digest_last_sent_on->toDateString())->toBe('2026-11-04');
});

it('saves settings and sends a test e-mail from the settings page', function () {
    $this->actingAs($this->user);

    Livewire::test(Digest::class)
        ->set('time', '06:45')
        ->set('weekends', true)
        ->call('save')
        ->assertHasNoErrors()
        ->call('sendTest');

    expect($this->user->fresh())
        ->digest_time->toBe('06:45')
        ->digest_weekends->toBeTrue();
    Mail::assertSent(DailyDigest::class);

    Livewire::test(Digest::class)->set('time', '25:00')->call('save')->assertHasErrors('time');
    $this->get(route('digest.edit'))->assertOk();
});
