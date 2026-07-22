<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;

final class ProgressBar implements Renderable
{
    private readonly ?\Closure $onUpdate;

    private int $current = 0;

    public function __construct(private readonly int $total, private readonly string $label = '', ?callable $onUpdate = null)
    {
        if ($total < 1) {
            throw new \InvalidArgumentException('A progress bar total must be positive.');
        }
        $this->onUpdate = $onUpdate === null ? null : \Closure::fromCallable($onUpdate);
    }

    public function advance(int $steps = 1): self
    {
        $this->current = min($this->total, $this->current + $steps);
        $this->updated(false);

        return $this;
    }

    public function finish(): self
    {
        $this->current = $this->total;
        $this->updated(true);

        return $this;
    }

    public function frame(int $width = 80): Frame
    {
        $barWidth = max(10, min(40, $width - 25));
        $filled = (int) round($barWidth * $this->current / $this->total);

        return Frame::line(sprintf('%s [%s%s] %d%%', $this->label, str_repeat('=', $filled), str_repeat(' ', $barWidth - $filled), (int) round($this->current / $this->total * 100)));
    }

    public function set(int $current): self
    {
        $this->current = min($this->total, max(0, $current));
        $this->updated(false);

        return $this;
    }

    private function updated(bool $force): void
    {
        if ($this->onUpdate !== null) {
            ($this->onUpdate)($this, $force);
        }
    }
}
