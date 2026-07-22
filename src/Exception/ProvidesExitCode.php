<?php

declare(strict_types=1);

namespace Infocyph\Console\Exception;

interface ProvidesExitCode
{
    public function exitCode(): int;
}
