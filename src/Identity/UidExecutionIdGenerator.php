<?php

declare(strict_types=1);

namespace Infocyph\Console\Identity;

use Infocyph\UID\Id;

final readonly class UidExecutionIdGenerator implements ExecutionIdGenerator
{
    public function generate(): string
    {
        return Id::uuid();
    }
}
