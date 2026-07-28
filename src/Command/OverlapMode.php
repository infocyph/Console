<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

enum OverlapMode: string
{
    case ALLOW = 'allow';

    case SKIP = 'skip';

    case WAIT = 'wait';
}
