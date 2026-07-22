<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\TextWidth;

final readonly class Task implements Renderable
{
    public function __construct(private string $label, private string $status = 'pending') {}

    public function frame(int $width = 80): Frame
    {
        $symbol = match ($this->status) {
            'success' => '✔', 'failed' => '✘', 'running' => '…', default => '○',
        };
        $role = $this->status === 'failed' ? 'error' : ($this->status === 'success' ? 'success' : 'text');

        return Frame::line(TextWidth::truncate($symbol . ' ' . $this->label, $width), $role);
    }
}
