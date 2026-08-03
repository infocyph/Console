<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

final readonly class Line
{
    /**
     * @param list<Span> $spans Styled fragments whose concatenated text must equal $text.
     */
    public function __construct(
        public string $text,
        public string $role = 'text',
        public array $spans = [],
    ) {}

    /**
     * Build one logical line from independently styled fragments.
     *
     * @param list<Span> $spans
     */
    public static function fromSpans(array $spans, string $role = 'text'): self
    {
        return new self(
            implode('', array_map(static fn(Span $span): string => $span->text, $spans)),
            $role,
            $spans,
        );
    }
}
