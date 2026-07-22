<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\Input\ArgumentCollection;
use Infocyph\Console\Input\OptionCollection;
use Infocyph\Console\IO\IO;

abstract class Command implements CommandContract
{
    private ?CommandContext $context = null;

    abstract protected function handle(): int;

    final public function run(CommandContext $context): int
    {
        if ($this->context !== null) {
            throw new \LogicException('A command is already running.');
        }
        $this->context = $context;

        try {
            return $this->handle();
        } finally {
            $this->context = null;
        }
    }

    final protected function arguments(): ArgumentCollection
    {
        return $this->context()->arguments();
    }

    final protected function context(): CommandContext
    {
        if ($this->context === null) {
            throw new \LogicException('A command context is available only while the command is running.');
        }

        return $this->context;
    }

    final protected function io(): IO
    {
        return $this->context()->io();
    }

    final protected function options(): OptionCollection
    {
        return $this->context()->options();
    }
}
