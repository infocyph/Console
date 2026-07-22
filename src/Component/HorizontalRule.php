<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;

final readonly class HorizontalRule implements Renderable
{
    public function __construct(private string $character = '─', private string $role = 'muted') {}

    public function frame(int $width = 80): Frame
    {
        return Frame::line(str_repeat($this->character, max(1, $width)), $this->role);
    }
}
