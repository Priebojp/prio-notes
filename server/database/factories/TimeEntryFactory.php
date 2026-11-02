<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        $minutes = fake()->numberBetween(5, 180);
        $start = now()->subDays(fake()->numberBetween(0, 6))->setTime(9, 0);

        return [
            'task_id' => Task::factory(),
            'user_id' => fn (array $attributes) => Task::query()->whereKey($attributes['task_id'])->value('user_id'),
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes($minutes),
            'seconds' => $minutes * 60,
        ];
    }

    public function running(): static
    {
        return $this->state(['started_at' => now()->subMinutes(10), 'ended_at' => null, 'seconds' => null]);
    }
}
