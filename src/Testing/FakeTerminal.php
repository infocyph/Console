<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

use Infocyph\Console\Terminal\ColorDepth;
use Infocyph\Console\Terminal\TerminalCapabilities;

final readonly class FakeTerminal
{
    public function __construct(private TerminalCapabilities $capabilities) {}

    public static function interactive(int $width = 80, int $height = 24, bool $ansi = true): self
    {
        return new self(new TerminalCapabilities(true, $ansi, true, $ansi ? ColorDepth::BASIC : ColorDepth::NONE, $width, $height));
    }

    public static function redirected(int $width = 80, int $height = 24): self
    {
        return new self(new TerminalCapabilities(false, false, false, ColorDepth::NONE, $width, $height));
    }

    public function capabilities(): TerminalCapabilities
    {
        return $this->capabilities;
    }
}
