<?php

declare(strict_types=1);

namespace Infocyph\Console\Input;

final readonly class ParsedInput
{
    /** @param list<string> $tokens */
    public function __construct(
        private ArgumentCollection $arguments,
        private OptionCollection $options,
        private array $tokens,
    ) {}

    public function arguments(): ArgumentCollection
    {
        return $this->arguments;
    }

    public function options(): OptionCollection
    {
        return $this->options;
    }

    /** @return list<string> */
    public function tokens(): array
    {
        return $this->tokens;
    }
}
