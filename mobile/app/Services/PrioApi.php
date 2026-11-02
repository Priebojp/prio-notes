<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Client for the Prio Notes server API (server/routes/api.php). */
class PrioApi
{
    public function __construct(private readonly Account $account) {}

    /**
     * @return array{token: string, user: array{name: string, email: string}}|array{two_factor: true}
     */
    public function login(string $serverUrl, string $email, string $password, ?string $code = null): array
    {
        $response = Http::baseUrl(rtrim($serverUrl, '/').'/api')
            ->acceptJson()
            ->timeout(20)
            ->post('tokens', array_filter([
                'email' => $email,
                'password' => $password,
                'device_name' => 'Prio Notes Android',
                'code' => $code,
            ]));

        if ($response->status() === 422 && $response->json('two_factor')) {
            return ['two_factor' => true];
        }

        return $this->check($response)->json();
    }

    public function logout(): void
    {
        rescue(fn () => $this->client()->delete('tokens/current'), report: false);
        $this->account->signOut();
    }

    /** @return array<string, mixed> */
    public function now(int $limit = 15): array
    {
        return $this->check($this->client()->get('now', ['limit' => $limit]))->json();
    }

    /** @return list<array{id: int, name: string, company: string}> */
    public function projects(): array
    {
        return $this->check($this->client()->get('projects'))->json('data');
    }

    /** @throws ApiConflict */
    public function createTask(string $title, int $priority, ?int $projectId = null): void
    {
        $this->check($this->client()->post('tasks', array_filter([
            'title' => $title,
            'priority' => $priority,
            'project_id' => $projectId,
        ])));
    }

    /** @throws ApiConflict */
    public function place(int $taskId, int $priority, ?int $index = null, ?int $demoteId = null): void
    {
        $this->check($this->client()->post("tasks/{$taskId}/place", array_filter([
            'priority' => $priority,
            'index' => $index,
            'demote_id' => $demoteId,
        ], fn ($v) => $v !== null)));
    }

    public function complete(int $taskId): void
    {
        $this->check($this->client()->post("tasks/{$taskId}/complete"));
    }

    public function startTimer(int $taskId): void
    {
        $this->check($this->client()->post("tasks/{$taskId}/timer"));
    }

    public function stopTimer(): void
    {
        $this->check($this->client()->delete('timer'));
    }

    /** @return list<array<string, mixed>> */
    public function meetings(): array
    {
        return $this->check($this->client()->get('meetings'))->json('data');
    }

    /**
     * @param  array{title?: string|null, project_id?: int|null, language?: string|null}  $fields
     * @return array<string, mixed>
     */
    public function uploadMeeting(string $path, array $fields): array
    {
        $response = $this->client()
            ->timeout(600)
            ->attach('audio', fopen($path, 'r'), basename($path))
            ->post('meetings', array_filter([
                ...$fields,
                'consent' => '1',
                'held_at' => now()->toIso8601String(),
            ], fn ($v) => $v !== null && $v !== ''));

        return $this->check($response)->json('data');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->account->serverUrl().'/api')
            ->withToken((string) $this->account->token())
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    private function check(Response $response): Response
    {
        if ($response->status() === 401) {
            $this->account->signOut();

            throw new RuntimeException('Prihlásenie vypršalo, prihlás sa znova.');
        }

        if ($response->status() === 409) {
            throw new ApiConflict($response->json());
        }

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?: "Server odpovedal chybou {$response->status()}.");
        }

        return $response;
    }
}
