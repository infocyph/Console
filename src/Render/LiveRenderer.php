<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

use Infocyph\Console\Terminal\Screen;
use Infocyph\Console\Terminal\Terminal;

/** @internal */
final class LiveRenderer
{
    private readonly Screen $screen;

    private float $lastDraw = 0.0;

    private ?Frame $previous = null;

    private int $renderedHeight = 0;

    public function __construct(private readonly Terminal $terminal, private readonly Renderer $renderer, private readonly FrameDiffer $differ = new FrameDiffer())
    {
        $this->screen = $terminal->screen();
    }

    public function render(Frame $frame, int $maxFps = 20, bool $force = false): void
    {
        if (!$this->terminal->capabilities()->interactive) {
            $this->terminal->write($this->renderer->render($frame));

            return;
        }
        $now = microtime(true);
        if (!$force && $now - $this->lastDraw < 1 / max(1, $maxFps)) {
            return;
        }
        $this->lastDraw = $now;
        $this->screen->beginLiveRegion();

        try {
            $cursor = $this->terminal->cursor();
            if ($this->renderedHeight > 0) {
                $cursor->up($this->renderedHeight);
            }
            $changed = array_flip($this->differ->changedLineIndexes($this->previous, $frame));
            foreach ($frame->lines as $index => $line) {
                if (isset($changed[$index])) {
                    $cursor->clearLine();
                    $this->terminal->write($this->renderer->render(new Frame([$line])));
                } else {
                    $cursor->down();
                }
            }
            for ($index = 0; $index < $this->differ->staleLineCount($this->previous, $frame); $index++) {
                $cursor->clearLine();
                $this->terminal->write(PHP_EOL);
            }
            $this->previous = $frame;
            $this->renderedHeight = count($frame->lines);
        } finally {
            $this->screen->restore();
        }
    }
}
