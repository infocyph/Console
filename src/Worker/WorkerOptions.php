<?php

declare(strict_types=1);

namespace Infocyph\Console\Worker;

final readonly class WorkerOptions
{
    /** @param array<string, string> $environment */
    public function __construct(
        public int $minimumProcesses = 0,
        public int $maximumProcesses = 1,
        public int $jobsPerProcess = 1,
        public int $scaleStep = 1,
        public bool $scaleDownProcesses = false,
        public float $pollIntervalSeconds = 1.0,
        public float $scaleCooldownSeconds = 1.0,
        public ?float $processMaxSeconds = null,
        public ?float $supervisorMaxSeconds = null,
        public ?int $maximumProcessesStarted = null,
        public float $terminationGraceSeconds = 5.0,
        public bool $stopWhenEmpty = false,
        public ?string $workingDirectory = null,
        public array $environment = [],
    ) {
        if ($minimumProcesses < 0 || $maximumProcesses < 1 || $minimumProcesses > $maximumProcesses) {
            throw new \InvalidArgumentException('Worker process bounds are invalid.');
        }
        if ($jobsPerProcess < 1 || $scaleStep < 1) {
            throw new \InvalidArgumentException('Worker job capacity and scale step must be positive.');
        }
        if ($pollIntervalSeconds <= 0 || $scaleCooldownSeconds < 0) {
            throw new \InvalidArgumentException('Worker polling must be positive and cooldown cannot be negative.');
        }
        if ($processMaxSeconds !== null && $processMaxSeconds <= 0) {
            throw new \InvalidArgumentException('Worker process lifetime must be positive.');
        }
        if ($supervisorMaxSeconds !== null && $supervisorMaxSeconds <= 0) {
            throw new \InvalidArgumentException('Worker supervisor lifetime must be positive.');
        }
        if ($maximumProcessesStarted !== null && $maximumProcessesStarted < 1) {
            throw new \InvalidArgumentException('Worker start limit must be positive.');
        }
        if ($terminationGraceSeconds < 0) {
            throw new \InvalidArgumentException('Worker termination grace cannot be negative.');
        }
    }
}
