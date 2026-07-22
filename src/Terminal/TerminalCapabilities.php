<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

final readonly class TerminalCapabilities
{
    public function __construct(
        public bool $interactive,
        public bool $ansi,
        public bool $unicode,
        public ColorDepth $colorDepth,
        public int $width,
        public int $height,
    ) {}
}
