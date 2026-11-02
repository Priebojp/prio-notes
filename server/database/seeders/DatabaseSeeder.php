<?php

namespace Database\Seeders;

use App\Enums\MeetingStatus;
use App\Enums\Priority;
use App\Models\User;
use App\Services\PriorityBoard;
use App\Services\TimeTracker;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Local demo data. Log in as test@example.com / password.
     */
    public function run(PriorityBoard $board, TimeTracker $tracker): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $exteco = $user->companies()->create(['name' => 'Exteco', 'color' => 'blue']);
        $personal = $user->companies()->create(['name' => 'Osobné', 'color' => 'green']);

        $web = $user->projects()->create(['company_id' => $exteco->id, 'name' => 'Web']);
        $tm = $user->projects()->create(['company_id' => $exteco->id, 'name' => 'TM interný systém']);
        $home = $user->projects()->create(['company_id' => $personal->id, 'name' => 'Domácnosť']);

        $tasks = [
            ['Opraviť chybu v objednávkach', $tm, Priority::P1, today()->toDateString()],
            ['Pripraviť ponuku pre klienta', $exteco->projects()->first(), Priority::P1, today()->addDays(2)->toDateString()],
            ['Nasadiť novú verziu webu', $web, Priority::P2, null],
            ['Code review PR od kolegu', $tm, Priority::P2, null],
            ['Aktualizovať texty na úvodnej stránke', $web, Priority::P3, today()->addWeek()->toDateString()],
            ['Zaplatiť faktúru za internet', $home, Priority::P3, today()->subDay()->toDateString()],
            ['Prečítať dokumentáciu NativePHP', null, Priority::Backlog, null],
        ];

        foreach ($tasks as [$title, $project, $priority, $due]) {
            $task = $user->tasks()->make(['title' => $title, 'project_id' => $project?->id, 'due_date' => $due]);
            $task->save();
            $board->place($task, $priority);
        }

        $tasks = $user->tasks()->get()->keyBy('title');
        $tracker->log($tasks['Opraviť chybu v objednávkach'], 95, today()->subDay(), 'Analýza logov');
        $tracker->log($tasks['Opraviť chybu v objednávkach'], 40, today());
        $tracker->log($tasks['Code review PR od kolegu'], 30, today()->subDays(2));
        $tracker->log($tasks['Nasadiť novú verziu webu'], 50, today()->subDay());

        $meeting = $user->meetings()->create([
            'title' => 'Týždenný sync k webu',
            'project_id' => $web->id,
            'held_at' => now()->subDay()->setTime(10, 0),
            'language' => 'sk',
        ]);
        $meeting->forceFill([
            'status' => MeetingStatus::Ready,
            'processed_at' => now()->subDay(),
            'duration_seconds' => 1260,
            'speakers' => ['speaker_0' => 'Peter', 'speaker_1' => 'Jano'],
            'transcript' => [
                ['speaker' => 'speaker_0', 'start' => 2.0, 'end' => 9.5, 'text' => 'Tak poďme na web. Klient chce nový dizajn úvodnej stránky do konca mesiaca.'],
                ['speaker' => 'speaker_1', 'start' => 10.0, 'end' => 18.2, 'text' => 'Dobre, grafiku mám skoro hotovú. Potrebujem ešte texty od klienta.'],
                ['speaker' => 'speaker_0', 'start' => 18.8, 'end' => 26.0, 'text' => 'Texty mu napíšem dnes. A do piatku mu pošlem aj cenovú ponuku na eshop.'],
                ['speaker' => 'speaker_1', 'start' => 26.5, 'end' => 31.0, 'text' => 'Ešte nevieme, či chcú platobnú bránu GoPay alebo Stripe.'],
            ],
            'summary' => "Prebrali sme redizajn úvodnej stránky a rozšírenie o eshop.\n\n### Redizajn\n- Klient chce nový dizajn do konca mesiaca.\n- Grafika je takmer hotová, chýbajú texty od klienta.\n\n### Eshop\n- Cenová ponuka pôjde klientovi do piatku.",
            'decisions' => ['Redizajn sa odovzdá do konca mesiaca.', 'Ponuku na eshop pripraví Peter.'],
            'open_questions' => ['Platobná brána: GoPay alebo Stripe?'],
        ])->save();
        $meeting->actionItems()->createMany([
            ['title' => 'Napísať klientovi kvôli textom na web', 'owner' => 'Peter', 'due_date' => today(), 'priority' => 2, 'notes' => 'Grafika čaká na texty.'],
            ['title' => 'Poslať cenovú ponuku na eshop', 'owner' => 'Peter', 'due_date' => today()->next('Friday'), 'priority' => 1, 'notes' => 'Klient chce ponuku do piatku.'],
            ['title' => 'Zistiť preferovanú platobnú bránu', 'owner' => null, 'due_date' => null, 'priority' => 3, 'notes' => null],
        ]);
    }
}
