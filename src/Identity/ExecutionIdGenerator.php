<?php

declare(strict_types=1);

namespace Infocyph\Console\Identity;

interface ExecutionIdGenerator
{
    public function generate(): string;
}
