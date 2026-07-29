<?php

declare(strict_types=1);

namespace Infocyph\Console\Omnibus;

use Infocyph\Console\Scheduling\Schedule;
use Infocyph\Console\Scheduling\ScheduledCommand;

final readonly class ScheduledMessages
{
    public function __construct(private Schedule $schedule) {}

    public function message(string $factory): ScheduledCommand
    {
        if ($factory === '') {
            throw new \InvalidArgumentException('A scheduled message factory key is required.');
        }

        return $this->schedule
            ->command(DispatchScheduledMessageCommand::NAME)
            ->arguments([$factory]);
    }
}
