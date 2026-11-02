<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Models\User;
use App\Services\PriorityBoard;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Local demo data. Log in as test@example.com / password.
     */
    public function run(PriorityBoard $board): void
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
    }
}
