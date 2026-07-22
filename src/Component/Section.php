<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\TextWidth;

final readonly class Section implements Renderable
{
    public function __construct(private string $text) {}

    public function frame(int $width = 80): Frame
    {
        return Frame::line(TextWidth::truncate($this->text, $width), 'section');
    }
}
