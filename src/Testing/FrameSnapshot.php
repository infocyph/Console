<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\PlainRenderer;
use Infocyph\Console\Render\Renderer;

final readonly class FrameSnapshot
{
    public function __construct(private string $contents) {}

    public static function capture(Frame $frame, ?Renderer $renderer = null): self
    {
        return new self(($renderer ?? new PlainRenderer())->render($frame));
    }

    public function assertMatches(Frame $frame, ?Renderer $renderer = null): void
    {
        $actual = ($renderer ?? new PlainRenderer())->render($frame);
        if ($actual !== $this->contents) {
            throw new \AssertionError(sprintf("Frame snapshot mismatch.\nExpected:\n%s\nActual:\n%s", $this->contents, $actual));
        }
    }

    public function contents(): string
    {
        return $this->contents;
    }
}
