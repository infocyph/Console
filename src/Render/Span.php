<?php

declare(strict_types=1);

namespace Infocyph\Console\Render;

/**
 * A styled fragment within one logical output line.
 *
 * @internal
 */
final readonly class Span
{
    public function __construct(
        public string $text,
        public string $role = 'text',
    ) {}
}
