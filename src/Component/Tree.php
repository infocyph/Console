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
        $truncated = [];
        foreach ($lines as $line) {
            $truncated[] = new Line(TextWidth::truncate($line->text, $width), $line->role);
        }

        return new Frame($truncated);
    }

    /**
     * @param array<string, mixed> $items
     * @param list<Line> $lines
     */
    private function append(array $items, string $indent, array &$lines): void
    {
        $last = array_key_last($items);
        foreach ($items as $key => $value) {
            $isLast = $key === $last;
            $lines[] = new Line($indent . ($isLast ? '└─ ' : '├─ ') . $key);
            $this->appendValue($value, $indent . ($isLast ? '   ' : '│  '), $lines);
        }
    }

    /** @param list<Line> $lines */
    private function appendValue(mixed $value, string $indent, array &$lines): void
    {
        if (is_array($value)) {
            $this->append($this->normalizeChildren($value), $indent, $lines);

            return;
        }

        if ($value === null) {
            return;
        }

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new \UnexpectedValueException('Tree leaf values must be scalar or stringable.');
        }
        $lines[] = new Line($indent . '└─ ' . $value, 'muted');
    }

    /**
     * @param array<array-key, mixed> $children
     * @return array<string, mixed>
     */
    private function normalizeChildren(array $children): array
    {
        $normalized = [];
        foreach ($children as $key => $value) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Tree nodes must use string keys.');
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }
}
