<?php

declare(strict_types=1);

namespace Infocyph\Console\Input;

enum ValueType: string
{
    case BOOLEAN = 'boolean';

    case FLOAT = 'float';

    case INTEGER = 'integer';

    case STRING = 'string';
}
