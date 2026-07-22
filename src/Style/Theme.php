<?php

declare(strict_types=1);

namespace Infocyph\Console\Style;

interface Theme
{
    public function style(string $role): Style;
}
