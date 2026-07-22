<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;

final readonly class ErrorMessage implements Renderable
{
    public function __construct(private string $message) {}

    public function frame(int $width = 80): Frame
    {
        return new Message($this->message, 'error')->frame($width);
    }
}
