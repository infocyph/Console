<?php

declare(strict_types=1);

namespace Infocyph\Console\Process;

enum ProcessMode
{
    case CAPTURE;

    case INHERIT;

    case STREAM;
}
