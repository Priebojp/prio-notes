<?php

use App\Enums\Priority;
use App\Exceptions\PriorityFullException;
use App\Models\Task;
use App\Models\User;
use App\Services\PriorityBoard;

beforeEach(function () {
    config(['prio.capacity' => [1 => 2, 2 => 2]]);
    $this->user = User::factory()->create();
    $this->board = app(PriorityBoard::class);
});

/** Create tasks in a column, top first. */
function fillColumn(User $user, Priority $priority, string ...$titles): array
{
    return collect($titles)
        ->map(fn ($title, $i) => Task::factory()->for($user)->priority($priority, $i)->create(['title' => $title]))
        ->all();
}

function titles(User $user, Priority $priority): array
{
    return app(PriorityBoard::class)->column($user, $priority)->pluck('title')->all();
}

it('adds a task to the end of a column with room', function () {
    fillColumn($this->user, Priority::P1, 'a');
    $task = Task::factory()->for($this->user)->create(['title' => 'new']);

    $this->board->place($task, Priority::P1);

    expect(titles($this->user, Priority::P1))->toBe(['a', 'new']);
});

it('refuses to overfill a column and lists the occupants', function () {
    fillColumn($this->user, Priority::P1, 'a', 'b');
    $task = Task::factory()->for($this->user)->create(['title' => 'new']);

    try {
        $this->board->place($task, Priority::P1);
        $this->fail('Expected PriorityFullException');
    } catch (PriorityFullException $e) {
        expect($e->occupants->pluck('title')->all())->toBe(['a', 'b'])
            ->and($e->priority)->toBe(Priority::P1);
    }

    expect($task->fresh()->priority)->toBe(Priority::Backlog);
});

it('demotes the chosen task to the top of the next column and takes its spot', function () {
    [$a, $b] = fillColumn($this->user, Priority::P1, 'a', 'b');
    fillColumn($this->user, Priority::P2, 'x');
    $task = Task::factory()->for($this->user)->create(['title' => 'new']);

    $this->board->place($task, Priority::P1, demote: $a);

    expect(titles($this->user, Priority::P1))->toBe(['new', 'b'])
        ->and(titles($this->user, Priority::P2))->toBe(['a', 'x']);
});

it('cascades the last task down when the lower column is full too', function () {
    [$a] = fillColumn($this->user, Priority::P1, 'a', 'b');
    fillColumn($this->user, Priority::P2, 'x', 'y');
    $task = Task::factory()->for($this->user)->create(['title' => 'new']);

    $this->board->place($task, Priority::P1, demote: $a);

    expect(titles($this->user, Priority::P1))->toBe(['new', 'b'])
        ->and(titles($this->user, Priority::P2))->toBe(['a', 'x'])
        ->and(titles($this->user, Priority::P3))->toBe(['y']);
});

it('swaps cleanly when promoting from the column the demoted task drops into', function () {
    [$a] = fillColumn($this->user, Priority::P1, 'a', 'b');
    [, $y] = fillColumn($this->user, Priority::P2, 'x', 'y');

    $this->board->place($y, Priority::P1, demote: $a);

    expect(titles($this->user, Priority::P1))->toBe(['y', 'b'])
        ->and(titles($this->user, Priority::P2))->toBe(['a', 'x'])
        ->and(titles($this->user, Priority::P3))->toBe([]);
});

it('ignores a demote candidate from another column', function () {
    fillColumn($this->user, Priority::P1, 'a', 'b');
    [$x] = fillColumn($this->user, Priority::P2, 'x');
    $task = Task::factory()->for($this->user)->create();

    $this->board->place($task, Priority::P1, demote: $x);
})->throws(PriorityFullException::class);

it('reorders within a full column without asking', function () {
    [$a, $b] = fillColumn($this->user, Priority::P1, 'a', 'b');

    $this->board->place($b, Priority::P1, 0);

    expect(titles($this->user, Priority::P1))->toBe(['b', 'a']);
});

it('moves up within a column and then into the column above', function () {
    fillColumn($this->user, Priority::P1, 'a');
    [, $y] = fillColumn($this->user, Priority::P2, 'x', 'y');

    $this->board->moveUp($y);
    expect(titles($this->user, Priority::P2))->toBe(['y', 'x']);

    $this->board->moveUp($y->fresh());
    expect(titles($this->user, Priority::P1))->toBe(['a', 'y'])
        ->and(titles($this->user, Priority::P2))->toBe(['x']);
});

it('asks before moving up into a full column', function () {
    fillColumn($this->user, Priority::P1, 'a', 'b');
    [$x] = fillColumn($this->user, Priority::P2, 'x');

    $this->board->moveUp($x);
})->throws(PriorityFullException::class);

it('moves the last task down into the next column', function () {
    [, $b] = fillColumn($this->user, Priority::P1, 'a', 'b');
    fillColumn($this->user, Priority::P2, 'x');

    $this->board->moveDown($b);

    expect(titles($this->user, Priority::P1))->toBe(['a'])
        ->and(titles($this->user, Priority::P2))->toBe(['b', 'x']);
});

it('frees the slot when a task is completed and keeps positions compact', function () {
    [$a, $b, $c] = fillColumn($this->user, Priority::P3, 'a', 'b', 'c');

    $this->board->complete($b);

    expect(titles($this->user, Priority::P3))->toBe(['a', 'c'])
        ->and($c->fresh()->position)->toBe(1);
});

it('reopens into a lower column when the old one filled up', function () {
    [$a] = fillColumn($this->user, Priority::P1, 'a');
    $this->board->complete($a);
    fillColumn($this->user, Priority::P1, 'b', 'c');

    $this->board->reopen($a->fresh());

    expect($a->fresh()->priority)->toBe(Priority::P2)
        ->and(titles($this->user, Priority::P1))->toBe(['b', 'c']);
});

it('keeps users boards separate', function () {
    fillColumn(User::factory()->create(), Priority::P1, 'foreign', 'foreign 2');
    $task = Task::factory()->for($this->user)->create(['title' => 'mine']);

    $this->board->place($task, Priority::P1);

    expect(titles($this->user, Priority::P1))->toBe(['mine']);
});
