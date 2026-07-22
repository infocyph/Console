<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;

final readonly class NoteMessage implements Renderable
{
    public function __construct(private string $message) {}

    public function frame(int $width = 80): Frame
    {
        return new Message($this->message, 'note')->frame($width);
    }
}
