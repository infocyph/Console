<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

final readonly class Frame
{
    /** @param list<Line> $lines */
    public function __construct(public array $lines) {}

    public static function line(string $text, string $role = 'text'): self
    {
        return new self([new Line($text, $role)]);
    }
}
