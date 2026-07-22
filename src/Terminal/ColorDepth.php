<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

enum ColorDepth: int
{
    case ANSI_256 = 256;

    case BASIC = 16;

    case NONE = 0;

    case TRUE_COLOR = 16777216;
}
