<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;

interface Renderable
{
    public function frame(int $width = 80): Frame;
}
