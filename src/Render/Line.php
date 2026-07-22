<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

final readonly class Line
{
    public function __construct(public string $text, public string $role = 'text') {}
}
