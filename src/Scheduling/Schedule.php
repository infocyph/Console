<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

final class Schedule
{
    /** @var list<ScheduledCommand> */
    private array $commands = [];

    public function command(string $command): ScheduledCommand
    {
        new ScheduleDefinitionValidator()->command($command);
        $scheduled = new ScheduledCommand($command);
        $this->commands[] = $scheduled;

        return $scheduled;
    }

    /** @return list<ScheduledCommand> */
    public function entries(): array
    {
        return $this->commands;
    }
}
