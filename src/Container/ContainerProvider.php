<?php

declare(strict_types=1);

namespace Infocyph\Console\Container;

use Infocyph\InterMix\DI\Container;

interface ContainerProvider
{
    public function container(): Container;
}
