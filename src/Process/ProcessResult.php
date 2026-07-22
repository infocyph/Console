<?php

declare(strict_types=1);

namespace Infocyph\Console\Process;

final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $errorOutput,
        public bool $timedOut = false,
        public bool $idleTimedOut = false,
        public bool $cancelled = false,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0 && !$this->timedOut && !$this->idleTimedOut && !$this->cancelled;
    }
}
