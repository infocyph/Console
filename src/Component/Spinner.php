<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\TextWidth;

final class Spinner implements Renderable
{
    private readonly ?\Closure $onUpdate;

    /** @var list<string> */
    private array $frames = ['-', '\\', '|', '/'];

    private int $position = 0;

    public function __construct(private readonly string $label = '', ?callable $onUpdate = null)
    {
        $this->onUpdate = $onUpdate === null ? null : \Closure::fromCallable($onUpdate);
    }

    public function frame(int $width = 80): Frame
    {
        return Frame::line(TextWidth::truncate($this->frames[$this->position] . ' ' . $this->label, $width));
    }

    public function tick(): self
    {
        $this->position = ($this->position + 1) % count($this->frames);
        if ($this->onUpdate !== null) {
            ($this->onUpdate)($this);
        }

        return $this;
    }
}
