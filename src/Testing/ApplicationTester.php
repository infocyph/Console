<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

use Infocyph\Console\Application;

final readonly class ApplicationTester
{
    public function __construct(private Application $application) {}

    public function command(string $name): PendingCommand
    {
        return new PendingCommand($this->application, $name);
    }
}
