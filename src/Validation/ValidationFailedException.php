<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

use Infocyph\Console\Exception\UsageException;

final class ValidationFailedException extends UsageException
{
    /** @param list<ValidationFailure> $failures */
    public function __construct(private readonly array $failures)
    {
        parent::__construct('Invalid command input.');
    }

    /** @return list<ValidationFailure> */
    public function failures(): array
    {
        return $this->failures;
    }
}
