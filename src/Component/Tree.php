<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\TextWidth;

final readonly class Tree implements Renderable
{
    /** @param array<string, mixed> $items */
    public function __construct(private array $items) {}

    public function frame(int $width = 80): Frame
    {
        $lines = [];
        $this->append($this->items, '', $lines);

        return new Frame(array_map(static fn(Line $line): Line => new Line(TextWidth::truncate($line->text, $width), $line->role), $lines));
    }

    /** @param array<string, mixed> $items @param list<Line> $lines */
    private function append(array $items, string $indent, array &$lines): void
    {
        $last = array_key_last($items);
        foreach ($items as $key => $value) {
            $isLast = $key === $last;
            $lines[] = new Line($indent . ($isLast ? '└─ ' : '├─ ') . $key);
            if (is_array($value)) {
                $this->append($value, $indent . ($isLast ? '   ' : '│  '), $lines);
            } elseif ($value !== null) {
                $lines[] = new Line($indent . ($isLast ? '   ' : '│  ') . '└─ ' . $value, 'muted');
            }
        }
    }
}
