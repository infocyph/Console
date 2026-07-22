<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

/** @internal */
final class BufferedRenderer implements Renderer
{
    /** @var list<Frame> */
    private array $frames = [];

    /** @return list<Frame> */
    public function frames(): array
    {
        return $this->frames;
    }

    public function render(Frame $frame): string
    {
        $this->frames[] = $frame;

        return new PlainRenderer()->render($frame);
    }
}
