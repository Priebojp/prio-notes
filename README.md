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

Flux Pro is required: Composer needs credentials for `composer.fluxui.dev`
(locally in `~/.config/composer/auth.json`; on Forge add them under the site's
Composer credentials before the first deploy).

Tests: `./vendor/bin/pest` · static analysis: `./vendor/bin/phpstan analyse`

## Meetings

Recording (browser `MediaRecorder` or uploaded file) → `TranscribeMeeting` job (ElevenLabs
Scribe with diarization) → `SummarizeMeeting` job (Claude via the official PHP SDK, structured
output: minutes, decisions, open questions, suggested tasks). Suggestions become tasks only
after the user accepts them, through the same priority rules as everything else.
Recordings are deleted after `MEETINGS_AUDIO_RETENTION_DAYS` (`meetings:prune-audio`, daily).

Needs `ELEVENLABS_API_KEY` and `ANTHROPIC_API_KEY` in `.env`, a running queue worker and the scheduler.

## Daily e-mail

`App\Digest\DigestBuilder` collects what is due (same ranking as the dashboard), upcoming
deadlines, meetings with unreviewed suggestions and yesterday's tracked time; `digest:send`
mails it once a day after each user's chosen time (Settings → Denný e-mail, with a test button).
Locally mail goes to the log (`MAIL_MAILER=log`); in production set the SMTP `MAIL_*` variables
and `MAIL_FROM_ADDRESS`.

## Deploying on Forge

- Web directory `/server/public`; deploy script runs in `server/` (`cd server` first)
- Composer credentials for `composer.fluxui.dev` (Flux Pro)
- Queue worker: `database` connection, timeout ≥ 1200 s (long recordings); keep `DB_QUEUE_RETRY_AFTER` above it
- Scheduler enabled (`php artisan schedule:run` every minute)
- Uploads: PHP `upload_max_filesize` / `post_max_size` and nginx `client_max_body_size` ≥ `MEETINGS_MAX_UPLOAD_MB`

## Priority rules

All priority changes go through `App\Services\PriorityBoard`:

- each priority is an ordered column; P1 and P2 have a capacity (`PRIO_P1_CAPACITY`, `PRIO_P2_CAPACITY`)
- moving a task into a full column requires choosing which occupant drops one level;
  the incoming task takes its spot, and if the lower column is full too its last task cascades down
- "what's urgent now" (dashboard, later widget + e-mail) is `App\Services\UrgencyRanker`:
  overdue/today → P1 → due within 3 days → P2 → P3 → backlog

## Roadmap

1. ✅ Companies, projects, tasks, priority board
2. ✅ Time tracking (one running timer, manual entries, weekly/monthly report)
3. ✅ Meetings: record/upload → transcribe (ElevenLabs Scribe, sk/cs) → minutes + suggested tasks (Claude)
4. ✅ Daily e-mail digest (per-user time, weekends optional; `digest:send` runs every minute, sends once a day)
5. NativePHP Android app (tasks, timer, recording)
6. Home-screen widget
