<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\TextWidth;

final readonly class Listing implements Renderable
{
    /** @param list<string> $items */
    public function __construct(private array $items, private bool $ordered = false) {}

    public function frame(int $width = 80): Frame
    {
        return new Frame(array_map(fn(string $item, int $index): Line => new Line(TextWidth::truncate(($this->ordered ? ($index + 1) . '. ' : '• ') . $item, $width)), $this->items, array_keys($this->items)));
    }
}
