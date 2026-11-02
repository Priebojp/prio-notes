<?php

namespace App\Support;

/** One row of the time report: a company, project or task with its total. */
final readonly class TimeBucket
{
    /**
     * @param  list<TimeBucket>  $children
     */
    public function __construct(
        public string $name,
        public int $seconds,
        public array $children = [],
        public string $color = 'zinc',
        public ?int $taskId = null,
    ) {}
}
