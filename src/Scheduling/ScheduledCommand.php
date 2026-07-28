<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

final class ScheduledCommand
{
    /** @var list<string> */
    private array $arguments = [];

    private CronExpression $cron;

    private ?float $idleTimeoutSeconds = null;

    private ?int $memoryLimitMegabytes = null;

    private ?\Closure $onFailure = null;

    private bool $onOneServer = false;

    private ?\Closure $onSuccess = null;

    private float $overlapLeaseSeconds = 300.0;

    private float $overlapWaitSeconds = 0.0;

    private float $terminationGraceSeconds = 5.0;

    private ?float $timeoutSeconds = null;

    private \DateTimeZone $timezone;

    private bool $withoutOverlap = false;

    public function __construct(private readonly string $command)
    {
        $this->cron = new CronExpression('* * * * *');
        $this->timezone = new \DateTimeZone(date_default_timezone_get());
    }

    /** @param list<string> $arguments */
    public function arguments(array $arguments): self
    {
        foreach ($arguments as $argument) {
            if ($argument === '') {
                throw new \InvalidArgumentException('Scheduled command arguments cannot be empty.');
            }
        }
        $this->arguments = $arguments;

        return $this;
    }

    public function command(): string
    {
        return $this->command;
    }

    /** @return list<string> */
    public function commandArguments(): array
    {
        return $this->arguments;
    }

    public function cron(string $expression): self
    {
        $this->cron = new CronExpression($expression);

        return $this;
    }

    public function dailyAt(string $time): self
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            throw new \InvalidArgumentException('Daily schedule times must use HH:MM format.');
        }
        [$hour, $minute] = explode(':', $time);

        return $this->cron((int) $minute . ' ' . (int) $hour . ' * * *');
    }

    public function due(\DateTimeInterface $now): bool
    {
        return $this->cron->matches(new \DateTimeImmutable('@' . $now->getTimestamp())->setTimezone($this->timezone));
    }

    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    public function failure(ScheduleRun $run): void
    {
        if ($this->onFailure !== null) {
            ($this->onFailure)($run);
        }
    }

    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    public function idleTimeout(float $seconds): self
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Schedule idle timeout must be positive.');
        }
        $this->idleTimeoutSeconds = $seconds;

        return $this;
    }

    public function idleTimeoutSeconds(): ?float
    {
        return $this->idleTimeoutSeconds;
    }

    public function memoryLimit(int $megabytes): self
    {
        if ($megabytes < 1) {
            throw new \InvalidArgumentException('Schedule memory limit must be at least one megabyte.');
        }
        $this->memoryLimitMegabytes = $megabytes;

        return $this;
    }

    public function memoryLimitMegabytes(): ?int
    {
        return $this->memoryLimitMegabytes;
    }

    public function onFailure(callable $callback): self
    {
        $this->onFailure = \Closure::fromCallable($callback);

        return $this;
    }

    public function onOneServer(
        bool $enabled = true,
        float $leaseSeconds = 300.0,
        float $waitSeconds = 0.0,
    ): self {
        if ($leaseSeconds <= 0 || $waitSeconds < 0) {
            throw new \InvalidArgumentException('Schedule lock lease must be positive and wait cannot be negative.');
        }
        $this->onOneServer = $enabled;
        $this->overlapLeaseSeconds = $leaseSeconds;
        $this->overlapWaitSeconds = $waitSeconds;

        return $this;
    }

    public function onSuccess(callable $callback): self
    {
        $this->onSuccess = \Closure::fromCallable($callback);

        return $this;
    }

    public function overlapLeaseSeconds(): float
    {
        return $this->overlapLeaseSeconds;
    }

    public function overlapWaitSeconds(): float
    {
        return $this->overlapWaitSeconds;
    }

    public function preventsOverlap(): bool
    {
        return $this->withoutOverlap;
    }

    public function requiresSingleServer(): bool
    {
        return $this->onOneServer;
    }

    public function success(ScheduleRun $run): void
    {
        if ($this->onSuccess !== null) {
            ($this->onSuccess)($run);
        }
    }

    public function terminationGraceSeconds(): float
    {
        return $this->terminationGraceSeconds;
    }

    public function timeout(float $seconds, float $terminationGraceSeconds = 5.0): self
    {
        if ($seconds <= 0 || $terminationGraceSeconds < 0) {
            throw new \InvalidArgumentException('Schedule timeout must be positive and grace cannot be negative.');
        }
        $this->timeoutSeconds = $seconds;
        $this->terminationGraceSeconds = $terminationGraceSeconds;

        return $this;
    }

    public function timeoutSeconds(): ?float
    {
        return $this->timeoutSeconds;
    }

    public function timezone(string|\DateTimeZone $timezone): self
    {
        $this->timezone = is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;

        return $this;
    }

    /** @return array{command:string,arguments:list<string>,cron:string,timezone:string,without_overlap:bool,on_one_server:bool,overlap_wait_seconds:float,overlap_lease_seconds:float,timeout_seconds:?float,idle_timeout_seconds:?float,termination_grace_seconds:float,memory_limit_megabytes:?int} */
    public function toManifest(): array
    {
        return [
            'command' => $this->command,
            'arguments' => $this->arguments,
            'cron' => $this->cron->expression(),
            'timezone' => $this->timezone->getName(),
            'without_overlap' => $this->withoutOverlap,
            'on_one_server' => $this->onOneServer,
            'overlap_wait_seconds' => $this->overlapWaitSeconds,
            'overlap_lease_seconds' => $this->overlapLeaseSeconds,
            'timeout_seconds' => $this->timeoutSeconds,
            'idle_timeout_seconds' => $this->idleTimeoutSeconds,
            'termination_grace_seconds' => $this->terminationGraceSeconds,
            'memory_limit_megabytes' => $this->memoryLimitMegabytes,
        ];
    }

    public function withoutOverlap(
        bool $enabled = true,
        float $leaseSeconds = 300.0,
        float $waitSeconds = 0.0,
    ): self {
        if ($leaseSeconds <= 0 || $waitSeconds < 0) {
            throw new \InvalidArgumentException('Schedule overlap lease must be positive and wait cannot be negative.');
        }
        $this->withoutOverlap = $enabled;
        $this->overlapLeaseSeconds = $leaseSeconds;
        $this->overlapWaitSeconds = $waitSeconds;

        return $this;
    }
}
