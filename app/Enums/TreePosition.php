<?php

namespace App\Enums;

enum TreePosition: string
{
    case Left = 'left';
    case Right = 'right';

    public function label(): string
    {
        return match ($this) {
            self::Left => 'Left',
            self::Right => 'Right',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::Left => self::Right,
            self::Right => self::Left,
        };
    }
}
