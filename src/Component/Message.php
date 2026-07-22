<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\TextWidth;

final readonly class Message implements Renderable
{
    public function __construct(private string $message, private string $role = 'text') {}

    public function frame(int $width = 80): Frame
    {
        return Frame::line(TextWidth::truncate($this->message, $width), $this->role);
    }
}
