<?php

declare(strict_types=1);

namespace Infocyph\Console\Worker;

enum WorkerStopReason: string
{
    case EMPTY = 'empty';

    case FAILURE_LIMIT = 'failure-limit';

    case HEARTBEAT_LOST = 'heartbeat-lost';

    case INTERRUPTED = 'interrupted';

    case START_LIMIT = 'start-limit';

    case SUPERVISOR_LIMIT = 'supervisor-limit';
}
