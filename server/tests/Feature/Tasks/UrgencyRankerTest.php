<?php

use App\Enums\Priority;
use App\Models\Task;
use App\Models\User;
use App\Services\UrgencyRanker;

it('ranks overdue and today first, then P1, then due soon, then the rest', function () {
    $this->travelTo('2026-11-02 09:00');
    $user = User::factory()->create();
    $make = fn (string $title, Priority $priority, int $position = 0, ?string $due = null) => Task::factory()
        ->for($user)->priority($priority, $position)->create(['title' => $title, 'due_date' => $due]);

    $make('backlog', Priority::Backlog);
    $make('p2', Priority::P2);
    $make('p3 due in 2 days', Priority::P3, due: '2026-11-04');
    $make('p1 second', Priority::P1, 1);
    $make('p1 first', Priority::P1, 0);
    $make('p3 due today', Priority::P3, due: '2026-11-02');
    $make('p2 overdue', Priority::P2, due: '2026-10-30');
    $make('p2 due next month', Priority::P2, 1, '2026-12-01');
    Task::factory()->for($user)->done()->create(['title' => 'done', 'due_date' => '2026-10-01']);

    expect(app(UrgencyRanker::class)->top($user)->pluck('title')->all())->toBe([
        'p2 overdue',
        'p3 due today',
        'p1 first',
        'p1 second',
        'p3 due in 2 days',
        'p2',
        'p2 due next month',
        'backlog',
    ]);
});
