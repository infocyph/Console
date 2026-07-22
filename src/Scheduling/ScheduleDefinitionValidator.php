<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

use Infocyph\ReqShield\Validator;

/** @internal */
final class ScheduleDefinitionValidator
{
    public function command(string $command): void
    {
        $result = Validator::compile(['command' => ['required', 'max:255']])->validator()->validate(['command' => $command]);
        if (!$result->passes()) {
            throw new \InvalidArgumentException('Scheduled command names must be non-empty and at most 255 characters.');
        }
    }
}
