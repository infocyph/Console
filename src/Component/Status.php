<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\TextWidth;

final readonly class Status implements Renderable
{
    public function __construct(private string $text, private string $role = 'info') {}

    public function frame(int $width = 80): Frame
    {
        return Frame::line(TextWidth::truncate($this->text, $width), $this->role);
    }
}
