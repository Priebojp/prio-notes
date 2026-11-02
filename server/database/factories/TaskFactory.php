<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim(fake()->sentence(4), '.'),
            'priority' => Priority::Backlog,
            'position' => 0,
        ];
    }

    /**
     * Note: bypasses capacity checks; tests use it to build a board directly.
     */
    public function priority(Priority $priority, int $position = 0): static
    {
        return $this->state(['priority' => $priority, 'position' => $position]);
    }

    public function due(string $date): static
    {
        return $this->state(['due_date' => $date]);
    }

    public function done(): static
    {
        return $this->state(['completed_at' => now()]);
    }
}
