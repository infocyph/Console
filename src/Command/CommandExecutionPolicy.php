<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\Support\ManifestValue;

final readonly class CommandExecutionPolicy
{
    public function __construct(
        public CommandExecutionMode $mode = CommandExecutionMode::INLINE,
        public OverlapMode $overlap = OverlapMode::ALLOW,
        public ?string $mutex = null,
        public float $waitSeconds = 0.0,
        public float $leaseSeconds = 300.0,
        public ?float $timeoutSeconds = null,
        public ?float $idleTimeoutSeconds = null,
        public float $terminationGraceSeconds = 5.0,
        public ?int $memoryLimitMegabytes = null,
    ) {
        if ($mutex === '') {
            throw new \InvalidArgumentException('Command mutex names cannot be empty.');
        }
        if ($waitSeconds < 0) {
            throw new \InvalidArgumentException('Command mutex wait time cannot be negative.');
        }
        if ($leaseSeconds <= 0) {
            throw new \InvalidArgumentException('Command mutex leases must be positive.');
        }
        if ($timeoutSeconds !== null && $timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Command timeouts must be positive.');
        }
        if ($idleTimeoutSeconds !== null && $idleTimeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Command idle timeouts must be positive.');
        }
        if ($terminationGraceSeconds < 0) {
            throw new \InvalidArgumentException('Command termination grace cannot be negative.');
        }
        if ($memoryLimitMegabytes !== null && $memoryLimitMegabytes < 1) {
            throw new \InvalidArgumentException('Command memory limits must be at least one megabyte.');
        }
        if ($overlap === OverlapMode::ALLOW && ($mutex !== null || $waitSeconds > 0)) {
            throw new \InvalidArgumentException('Mutex configuration requires skip or wait overlap mode.');
        }
    }

    /** @param array<string, mixed> $manifest */
    public static function fromManifest(array $manifest): self
    {
        $mode = CommandExecutionMode::tryFrom(ManifestValue::string(
            $manifest['mode'] ?? null,
            'command.execution.mode',
            CommandExecutionMode::INLINE->value,
        ));
        $overlap = OverlapMode::tryFrom(ManifestValue::string(
            $manifest['overlap'] ?? null,
            'command.execution.overlap',
            OverlapMode::ALLOW->value,
        ));
        if ($mode === null || $overlap === null) {
            throw new \UnexpectedValueException('Command execution manifest contains an unsupported mode.');
        }

        return new self(
            $mode,
            $overlap,
            ManifestValue::nullableString($manifest['mutex'] ?? null, 'command.execution.mutex'),
            self::number($manifest['wait_seconds'] ?? 0.0, 'command.execution.wait_seconds'),
            self::number($manifest['lease_seconds'] ?? 300.0, 'command.execution.lease_seconds'),
            self::nullableNumber($manifest['timeout_seconds'] ?? null, 'command.execution.timeout_seconds'),
            self::nullableNumber($manifest['idle_timeout_seconds'] ?? null, 'command.execution.idle_timeout_seconds'),
            self::number(
                $manifest['termination_grace_seconds'] ?? 5.0,
                'command.execution.termination_grace_seconds',
            ),
            self::nullableInteger(
                $manifest['memory_limit_megabytes'] ?? null,
                'command.execution.memory_limit_megabytes',
            ),
        );
    }

    public function requiresSupervisor(): bool
    {
        return $this->mode === CommandExecutionMode::ISOLATED
            || $this->overlap !== OverlapMode::ALLOW
            || $this->timeoutSeconds !== null
            || $this->idleTimeoutSeconds !== null
            || $this->memoryLimitMegabytes !== null;
    }

    /** @return array<string, bool|float|int|string|null> */
    public function toManifest(): array
    {
        return [
            'mode' => $this->mode->value,
            'overlap' => $this->overlap->value,
            'mutex' => $this->mutex,
            'wait_seconds' => $this->waitSeconds,
            'lease_seconds' => $this->leaseSeconds,
            'timeout_seconds' => $this->timeoutSeconds,
            'idle_timeout_seconds' => $this->idleTimeoutSeconds,
            'termination_grace_seconds' => $this->terminationGraceSeconds,
            'memory_limit_megabytes' => $this->memoryLimitMegabytes,
        ];
    }

    private static function nullableInteger(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value)) {
            throw new \UnexpectedValueException(sprintf('Manifest field "%s" must be an integer.', $field));
        }

        return $value;
    }

    private static function nullableNumber(mixed $value, string $field): ?float
    {
        return $value === null ? null : self::number($value, $field);
    }

    private static function number(mixed $value, string $field): float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \UnexpectedValueException(sprintf('Manifest field "%s" must be numeric.', $field));
        }

        return (float) $value;
    }
}
