<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;

final readonly class Details implements Renderable
{
    /** @param array<string,scalar|null> $items */
    public function __construct(private array $items) {}

    public function frame(int $width = 80): Frame
    {
        return new DefinitionList($this->items)->frame($width);
    }
}
