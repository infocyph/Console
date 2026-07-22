<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\TextWidth;

final readonly class DefinitionList implements Renderable
{
    /** @param array<string, scalar|null> $items */
    public function __construct(private array $items) {}

    public function frame(int $width = 80): Frame
    {
        $labelWidth = max(0, ...array_map(strlen(...), array_keys($this->items)));

        return new Frame(array_map(static fn(mixed $value, string $key): Line => new Line(TextWidth::truncate(str_pad($key, $labelWidth) . ': ' . $value, $width)), $this->items, array_keys($this->items)));
    }
}
