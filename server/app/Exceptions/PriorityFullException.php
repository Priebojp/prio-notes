<?php

namespace App\Exceptions;

use App\Enums\Priority;
use App\Models\Task;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Thrown when a task should enter a priority column that has no free slot.
 * The caller is expected to ask the user which occupant to demote and retry.
 */
class PriorityFullException extends RuntimeException
{
    /**
     * @param  Collection<int, Task>  $occupants
     */
    public function __construct(
        public readonly Task $task,
        public readonly Priority $priority,
        public readonly ?int $index,
        public readonly Collection $occupants,
    ) {
        parent::__construct("Priority {$priority->label()} is full.");
    }
}
