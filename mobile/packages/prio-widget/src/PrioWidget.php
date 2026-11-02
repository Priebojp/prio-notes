<?php

namespace Prieboj\PrioWidget;

/**
 * PHP side of the home-screen widget. The widget fetches /api/now itself,
 * so the app only hands over credentials and nudges it after changes.
 * No-ops outside the native runtime (tests, browser).
 */
class PrioWidget
{
    public static function configure(string $serverUrl, ?string $token): void
    {
        self::call('PrioWidget.Configure', ['url' => $serverUrl, 'token' => $token]);
    }

    public static function refresh(): void
    {
        self::call('PrioWidget.Refresh');
    }

    /** @param  array<string, mixed>  $payload */
    private static function call(string $function, array $payload = []): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call($function, json_encode($payload));
        }
    }
}
