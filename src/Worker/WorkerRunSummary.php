<?php

declare(strict_types=1);

namespace Infocyph\Console\Worker;

final readonly class WorkerRunSummary
{
    public function __construct(
        public int $started,
        public int $completed,
        public int $failed,
        public int $forced,
        public bool $interrupted,
        public float $durationSeconds,
    ) {}

    public function successful(): bool
    {
        return !$this->interrupted && $this->failed === 0 && $this->forced === 0;
    }
}
