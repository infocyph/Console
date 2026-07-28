<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

enum CommandExecutionMode: string
{
    case INLINE = 'inline';

    case ISOLATED = 'isolated';
}
