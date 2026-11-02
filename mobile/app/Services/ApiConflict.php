<?php

namespace App\Services;

use RuntimeException;

/** The server says a priority column is full; the user must pick what moves down. */
class ApiConflict extends RuntimeException
{
    /** @param  array<string, mixed>  $payload  task_id, priority, index, occupants */
    public function __construct(public readonly array $payload)
    {
        parent::__construct((string) ($payload['message'] ?? 'Priorita je plná.'));
    }
}
