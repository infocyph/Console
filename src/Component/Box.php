<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\TextWidth;

final readonly class Box implements Renderable
{
    /** @param string|array<string, scalar|null> $content */
    public function __construct(private string $title, private string|array $content) {}

    public function frame(int $width = 80): Frame
    {
        $lines = is_array($this->content)
            ? array_map(static fn(mixed $value, string $key): string => $key . ': ' . $value, $this->content, array_keys($this->content))
            : explode("\n", $this->content);
        $innerWidth = max(1, min($width - 4, max(TextWidth::width($this->title) + 2, ...array_map(TextWidth::width(...), $lines))));
        $frame = [new Line('┌ ' . TextWidth::pad($this->title, $innerWidth - 1) . '┐', 'border')];
        foreach ($lines as $line) {
            $frame[] = new Line('│ ' . TextWidth::pad($line, $innerWidth - 1) . '│', 'text');
        }
        $frame[] = new Line('└' . str_repeat('─', $innerWidth) . '┘', 'border');

        return new Frame($frame);
    }
}
