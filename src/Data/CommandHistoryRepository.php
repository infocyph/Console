<?php

declare(strict_types=1);

namespace Infocyph\Console\Data;

use Infocyph\Console\Identity\CommandExecution;

interface CommandHistoryRepository
{
    /** @param array<string, scalar|null> $metadata */
    public function record(CommandExecution $execution, int $exitCode, array $metadata = []): void;
}
