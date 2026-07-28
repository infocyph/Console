<?php

declare(strict_types=1);

namespace Infocyph\Console\Worker;

interface WorkloadProbe
{
    public function pending(): int;
}
