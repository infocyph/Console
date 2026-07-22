<?php

declare(strict_types=1);

namespace Infocyph\Console\Style;

final readonly class Style
{
    public function __construct(public Color $foreground = Color::DEFAULT, public bool $bold = false, public bool $dim = false) {}
}
