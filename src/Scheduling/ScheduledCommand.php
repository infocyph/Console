<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

final class ScheduledCommand
{
    private CronExpression $cron;

    private ?\Closure $onFailure = null;

    private bool $onOneServer = false;

    private ?\Closure $onSuccess = null;

    private \DateTimeZone $timezone;

    private bool $withoutOverlap = false;

    public function __construct(private readonly string $command)
    {
        $this->cron = new CronExpression('* * * * *');
        $this->timezone = new \DateTimeZone(date_default_timezone_get());
    }

    public function command(): string
    {
        return $this->command;
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

    public function onFailure(callable $callback): self
    {
        $this->onFailure = \Closure::fromCallable($callback);

        return $this;
    }

    public function onOneServer(bool $enabled = true): self
    {
        $this->onOneServer = $enabled;

        return $this;
    }

    public function onSuccess(callable $callback): self
    {
        $this->onSuccess = \Closure::fromCallable($callback);

        return $this;
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

    public function timezone(string|\DateTimeZone $timezone): self
    {
        $this->timezone = is_string($timezone) ? new \DateTimeZone($timezone) : $timezone;

        return $this;
    }

    /** @return array{command:string,cron:string,timezone:string,without_overlap:bool,on_one_server:bool} */
    public function toManifest(): array
    {
        return ['command' => $this->command, 'cron' => $this->cron->expression(), 'timezone' => $this->timezone->getName(), 'without_overlap' => $this->withoutOverlap, 'on_one_server' => $this->onOneServer];
    }

    public function withoutOverlap(bool $enabled = true): self
    {
        $this->withoutOverlap = $enabled;

        return $this;
    }
}
