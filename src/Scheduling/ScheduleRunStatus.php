<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

enum ScheduleRunStatus: string
{
    case COMPLETED = 'completed';

    case SKIPPED = 'skipped';
}
