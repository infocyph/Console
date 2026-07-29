<?php

declare(strict_types=1);

namespace Infocyph\Console\Omnibus;

use Infocyph\Console\Worker\WorkloadProbe;
use Infocyph\Omnibus\Transport\Receiver;

final readonly class ReceiverWorkloadProbe implements WorkloadProbe
{
    public function __construct(
        private Receiver $receiver,
        private string $queue = 'default',
    ) {
        if ($queue === '') {
            throw new \InvalidArgumentException('The workload queue cannot be empty.');
        }
    }

    public function pending(): int
    {
        return $this->receiver->size($this->queue);
    }
}
