<?php

declare(strict_types=1);

namespace Infocyph\Console\Process;

final readonly class ProcessOptions
{
    /**
     * @param array<string, string> $environment
     * @param list<string> $sensitiveValues
     * @param null|callable(string): void $onOutput
     * @param null|callable(string): void $onErrorOutput
     * @param null|callable(): bool $cancelled
     * @param resource|string|null $input
     */
    public function __construct(
        public ?string $workingDirectory = null,
        public array $environment = [],
        public ?float $timeoutSeconds = null,
        public ?float $idleTimeoutSeconds = null,
        public array $sensitiveValues = [],
        public bool $passthrough = false,
        public mixed $onOutput = null,
        public mixed $onErrorOutput = null,
        public mixed $cancelled = null,
        public mixed $input = null,
        public bool $inheritInput = false,
        public ProcessMode $mode = ProcessMode::CAPTURE,
    ) {
        if ($timeoutSeconds !== null && $timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Process timeout must be positive.');
        }
        if ($idleTimeoutSeconds !== null && $idleTimeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Process idle timeout must be positive.');
        }
    }
}
