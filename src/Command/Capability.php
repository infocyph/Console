<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

enum Capability: string
{
    case CACHE = 'cache';

    case CONFIGURATION = 'configuration';

    case CONTAINER = 'container';

    case CRYPTOGRAPHY = 'cryptography';

    case DATABASE = 'database';

    case FILESYSTEM = 'filesystem';

    case IDENTITY = 'identity';

    case NETWORK = 'network';

    case OTP = 'otp';

    case PROCESS = 'process';

    case SCHEDULER = 'scheduler';

    case VALIDATION = 'validation';
}
