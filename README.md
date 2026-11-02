# Prio Notes

Personal task & meeting notes app: tasks per company/project, priority columns
with limited capacity (P1 max 3, P2 max 7), deadlines, and — later — meeting
recordings turned into minutes and tasks, daily e-mail digest, time tracking
and an Android app with a home-screen widget.

## Layout

- `server/` — Laravel 13 + Livewire 4 + Flux (web UI for PC, later the API for mobile)
- `mobile/` — NativePHP Android app (phase 5, not created yet)

## Local development

```bash
cd server
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed   # demo user: test@example.com / password
composer run dev             # http://localhost:8000
```

Tests: `./vendor/bin/pest` · static analysis: `./vendor/bin/phpstan analyse`

## Priority rules

All priority changes go through `App\Services\PriorityBoard`:

- each priority is an ordered column; P1 and P2 have a capacity (`PRIO_P1_CAPACITY`, `PRIO_P2_CAPACITY`)
- moving a task into a full column requires choosing which occupant drops one level;
  the incoming task takes its spot, and if the lower column is full too its last task cascades down
- "what's urgent now" (dashboard, later widget + e-mail) is `App\Services\UrgencyRanker`:
  overdue/today → P1 → due within 3 days → P2 → P3 → backlog

## Roadmap

1. ✅ Companies, projects, tasks, priority board
2. Time tracking
3. Meetings: record → transcribe (ElevenLabs Scribe, sk/cs) → minutes + suggested tasks (Claude)
4. Daily e-mail digest
5. NativePHP Android app (tasks, timer, recording)
6. Home-screen widget
