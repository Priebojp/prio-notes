<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Facades\SecureStorage;
use Prieboj\PrioWidget\PrioWidget;
use Throwable;

/**
 * Who is signed in on this phone. The server address is fixed per build
 * (PRIO_SERVER_URL in .env).
 *
 * The API token goes to the OS keystore (SecureStorage) when the device
 * supports it, otherwise it is kept encrypted in the app's private database.
 */
class Account
{
    private const TOKEN_KEY = 'prio_api_token';

    public function serverUrl(): string
    {
        return rtrim((string) config('services.prio.url'), '/');
    }

    public function userName(): ?string
    {
        return $this->get('user_name');
    }

    public function token(): ?string
    {
        try {
            $token = SecureStorage::get(self::TOKEN_KEY);
            if (filled($token)) {
                return $token;
            }
        } catch (Throwable) {
            // SecureStorage not available on this build; use the fallback below.
        }

        $encrypted = $this->get('token');

        return $encrypted ? Crypt::decryptString($encrypted) : null;
    }

    public function isSignedIn(): bool
    {
        return filled($this->token());
    }

    public function signIn(string $token, string $userName): void
    {
        $this->put('user_name', $userName);

        $stored = false;
        try {
            $stored = SecureStorage::set(self::TOKEN_KEY, $token);
        } catch (Throwable) {
        }

        $this->put('token', $stored ? null : Crypt::encryptString($token));

        PrioWidget::configure($this->serverUrl(), $token);
    }

    public function signOut(): void
    {
        try {
            SecureStorage::delete(self::TOKEN_KEY);
        } catch (Throwable) {
        }

        $this->put('token', null);
        $this->put('user_name', null);

        PrioWidget::configure($this->serverUrl(), null);
    }

    private function get(string $key): ?string
    {
        return DB::table('settings')->where('key', $key)->value('value');
    }

    private function put(string $key, ?string $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
    }
}
