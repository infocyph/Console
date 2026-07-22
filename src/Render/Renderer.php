<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

interface Renderer
{
    public function render(Frame $frame): string;
}
