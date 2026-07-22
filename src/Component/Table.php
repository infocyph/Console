<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\TextWidth;

final readonly class Table implements Renderable
{
    /** @param list<string> $headers @param list<array<array-key, scalar|null>> $rows */
    public function __construct(private array $headers, private array $rows) {}

    public function frame(int $width = 80): Frame
    {
        $rows = array_map(static fn(array $row): array => array_map(static fn(mixed $value): string => (string) $value, array_values($row)), $this->rows);
        $widths = array_map(TextWidth::width(...), $this->headers);
        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $widths[$index] = max($widths[$index] ?? 0, TextWidth::width($value));
            }
        }
        $format = static fn(array $row): string => '| ' . implode(' | ', array_map(static fn(string $value, int $index): string => TextWidth::pad($value, $widths[$index]), $row, array_keys($row))) . ' |';
        $separator = '+-' . implode('-+-', array_map(static fn(int $size): string => str_repeat('-', $size), $widths)) . '-+';
        $lines = [new Line($separator, 'border'), new Line($format($this->headers), 'section'), new Line($separator, 'border')];
        foreach ($rows as $row) {
            $lines[] = new Line($format($row));
        }
        $lines[] = new Line($separator, 'border');

        return new Frame(array_map(static fn(Line $line): Line => new Line(TextWidth::truncate($line->text, $width), $line->role), $lines));
    }
}
