<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

interface ScheduleStateRepository
{
    public function record(ScheduleRun $run): void;
}
