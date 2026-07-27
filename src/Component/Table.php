<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\TextWidth;

final readonly class Table implements Renderable
{
    /**
     * @param list<string> $headers
     * @param list<array<array-key, scalar|null>> $rows
     */
    public function __construct(private array $headers, private array $rows) {}

    public function frame(int $width = 80): Frame
    {
        $rows = [];
        foreach ($this->rows as $row) {
            $values = [];
            foreach ($row as $value) {
                $values[] = (string) $value;
            }
            $rows[] = $values;
        }
        $widths = array_map(TextWidth::width(...), $this->headers);
        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $widths[$index] = max($widths[$index] ?? 0, TextWidth::width($value));
            }
        }
        $separator = '+-' . implode('-+-', array_map(static fn(int $size): string => str_repeat('-', $size), $widths)) . '-+';
        $lines = [new Line($separator, 'border'), new Line($this->formatRow($this->headers, $widths), 'section'), new Line($separator, 'border')];
        foreach ($rows as $row) {
            $lines[] = new Line($this->formatRow($row, $widths));
        }
        $lines[] = new Line($separator, 'border');

        return new Frame(array_map(static fn(Line $line): Line => new Line(TextWidth::truncate($line->text, $width), $line->role), $lines));
    }

    /**
     * @param list<string> $row
     * @param array<int, int> $widths
     */
    private function formatRow(array $row, array $widths): string
    {
        $cells = [];
        foreach ($row as $index => $value) {
            $cells[] = TextWidth::pad($value, $widths[$index]);
        }

        return '| ' . implode(' | ', $cells) . ' |';
    }
}
