<?php

namespace App\Meetings;

use App\Enums\Priority;

final readonly class ActionItemDraft
{
    public function __construct(
        public string $title,
        public ?string $notes,
        public ?string $owner,
        public ?string $dueDate,
        public Priority $priority,
    ) {}
}
