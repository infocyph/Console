<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\Span;
use Infocyph\Console\Render\TextWidth;

final readonly class DefinitionList implements Renderable
{
    /** @param array<string, scalar|null> $items */
    public function __construct(private array $items) {}

    public function frame(int $width = 80): Frame
    {
        $labelWidth = max(0, ...array_map(strlen(...), array_keys($this->items)));

        return new Frame(array_map(static function (mixed $value, string $key) use ($labelWidth, $width): Line {
            $label = TextWidth::truncate(TextWidth::pad($key, $labelWidth), $width);
            $remaining = max(0, $width - TextWidth::width($label));
            $separator = TextWidth::truncate(': ', $remaining);
            $remaining -= TextWidth::width($separator);
            $description = TextWidth::truncate((string) $value, $remaining);

            return Line::fromSpans([
                new Span($label, 'definition-label'),
                new Span($separator, 'definition-separator'),
                new Span($description, 'definition-value'),
            ], 'definition');
        }, $this->items, array_keys($this->items)));
    }
}
