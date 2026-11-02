<?php

namespace App\Console\Commands;

use App\Digest\DigestBuilder;
use App\Mail\DailyDigest;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('digest:send {--user= : Only this user id} {--force : Ignore the time, weekend and already-sent checks}')]
#[Description('Send the daily e-mail to users whose digest time has come (runs every minute, sends once a day)')]
class SendDailyDigests extends Command
{
    public function handle(DigestBuilder $builder): int
    {
        $force = (bool) $this->option('force');
        $sent = 0;

        User::query()
            ->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))
            ->unless($force, fn ($q) => $q->where('digest_enabled', true))
            ->each(function (User $user) use ($builder, $force, &$sent) {
                if (! $force && ! $this->isDue($user)) {
                    return;
                }

                $digest = $builder->build($user);

                // Nothing to say is not worth an e-mail; still count the day as done.
                if (! $digest->isEmpty() || $force) {
                    Mail::to($user)->send(new DailyDigest($digest));
                    $sent++;
                }

                $user->forceFill(['digest_last_sent_on' => today()])->save();
            });

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }

    private function isDue(User $user): bool
    {
        return ! $user->digest_last_sent_on?->isToday()
            && now()->format('H:i') >= $user->digest_time
            && ($user->digest_weekends || ! now()->isWeekend());
    }
}
