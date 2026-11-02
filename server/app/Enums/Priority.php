<?php

namespace App\Enums;

enum Priority: int
{
    case P1 = 1;
    case P2 = 2;
    case P3 = 3;
    case Backlog = 4;

    public function label(): string
    {
        return match ($this) {
            self::Backlog => 'Backlog',
            default => 'P'.$this->value,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::P1 => 'red',
            self::P2 => 'amber',
            self::P3 => 'sky',
            self::Backlog => 'zinc',
        };
    }

    /** Max open tasks in this column, null = unlimited. */
    public function capacity(): ?int
    {
        return config('prio.capacity')[$this->value] ?? null;
    }

    public function higher(): ?self
    {
        return self::tryFrom($this->value - 1);
    }

    public function lower(): ?self
    {
        return self::tryFrom($this->value + 1);
    }
}
