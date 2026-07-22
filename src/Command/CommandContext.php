<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\Identity\CommandExecution;
use Infocyph\Console\Input\ArgumentCollection;
use Infocyph\Console\Input\OptionCollection;
use Infocyph\Console\Input\ParsedInput;
use Infocyph\Console\IO\IO;

final readonly class CommandContext
{
    public function __construct(
        private ParsedInput $input,
        private IO $io,
        private ?CommandExecution $execution = null,
    ) {}

    public function arguments(): ArgumentCollection
    {
        return $this->input->arguments();
    }

    public function execution(): ?CommandExecution
    {
        return $this->execution;
    }

    public function input(): ParsedInput
    {
        return $this->input;
    }

    public function io(): IO
    {
        return $this->io;
    }

    public function options(): OptionCollection
    {
        return $this->input->options();
    }
}
