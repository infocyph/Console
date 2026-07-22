<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

use Infocyph\Console\Application;
use PHPUnit\Framework\TestCase;

abstract class CommandTestCase extends TestCase
{
    abstract protected function application(): Application;

    final protected function command(string $name): PendingCommand
    {
        return new ApplicationTester($this->application())->command($name);
    }
}
