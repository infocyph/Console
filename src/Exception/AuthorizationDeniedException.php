<?php

declare(strict_types=1);

namespace Infocyph\Console\Exception;

use Infocyph\Console\Command\ExitCode;

final class AuthorizationDeniedException extends \RuntimeException implements ProvidesExitCode
{
    public function exitCode(): int
    {
        return ExitCode::FAILURE;
    }
}
