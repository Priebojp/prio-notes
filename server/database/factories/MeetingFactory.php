<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Porada '.fake()->date('j. n.'),
            'held_at' => now(),
            'language' => 'sk',
        ];
    }

    public function ready(): static
    {
        return $this->state([
            'status' => MeetingStatus::Ready,
            'processed_at' => now(),
            'transcript' => [
                ['speaker' => 'speaker_0', 'start' => 0.0, 'end' => 4.2, 'text' => 'Ahoj, poďme prebrať web.'],
                ['speaker' => 'speaker_1', 'start' => 4.5, 'end' => 9.0, 'text' => 'Do piatku pošlem ponuku.'],
            ],
            'speakers' => ['speaker_0' => 'Rečník 1', 'speaker_1' => 'Rečník 2'],
            'summary' => 'Prebrali sme web.',
            'decisions' => ['Ponuka pôjde do piatku.'],
            'open_questions' => [],
        ]);
    }
}
