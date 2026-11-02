<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Meetings\ClaudeSummarizer;
use App\Meetings\Contracts\Summarizer;
use App\Meetings\Contracts\Transcriber;
use App\Meetings\ElevenLabsTranscriber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Transcriber::class, fn () => new ElevenLabsTranscriber(
            config('services.elevenlabs.key'),
            config('services.elevenlabs.stt_model'),
        ));

        $this->app->bind(Summarizer::class, fn () => new ClaudeSummarizer(
            filled($key = config('services.anthropic.key'))
                ? new AnthropicClient(apiKey: $key, requestOptions: ['timeout' => 600])
                : null,
            config('services.anthropic.model'),
            config('services.anthropic.effort'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
