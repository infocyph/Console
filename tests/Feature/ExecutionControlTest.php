<?php

declare(strict_types=1);

use Infocyph\CacheLayer\Cache\Lock\LockHandle;
use Infocyph\CacheLayer\Cache\Lock\LockProviderInterface;
use Infocyph\Console\Cache\CommandMutex;
use Infocyph\Console\Command\CommandContext;
use Infocyph\Console\Command\CommandContract;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Command\CommandExecutionMode;
use Infocyph\Console\Command\OverlapMode;
use Infocyph\Console\Scheduling\Schedule;
use Infocyph\Console\Scheduling\ScheduleRunner;
use Infocyph\Console\Worker\WorkerOptions;
use Infocyph\Console\Worker\WorkerSupervisor;
use Infocyph\Console\Worker\WorkloadProbe;

final class ControlledFixtureCommand implements CommandContract
{
    public static function define(CommandDefinition $definition): void
    {
        $definition
            ->name('controlled:run')
            ->withoutOverlap('controlled', leaseSeconds: 12.0, waitSeconds: 2.0)
            ->timeout(30.0, 3.0)
            ->idleTimeout(10.0)
            ->memoryLimit(64);
    }

    public function run(CommandContext $context): int
    {
        unset($context);

        return 0;
    }
}

it('compiles command execution controls into manifests', function (): void {
    $descriptor = CommandDescriptor::fromClass(ControlledFixtureCommand::class);
    $restored = CommandDescriptor::fromManifest($descriptor->toManifest());
    $policy = $restored->execution();

    expect($policy->mode)->toBe(CommandExecutionMode::ISOLATED)
        ->and($policy->overlap)->toBe(OverlapMode::WAIT)
        ->and($policy->mutex)->toBe('controlled')
        ->and($policy->leaseSeconds)->toBe(12.0)
        ->and($policy->waitSeconds)->toBe(2.0)
        ->and($policy->timeoutSeconds)->toBe(30.0)
        ->and($policy->idleTimeoutSeconds)->toBe(10.0)
        ->and($policy->terminationGraceSeconds)->toBe(3.0)
        ->and($policy->memoryLimitMegabytes)->toBe(64);
});

it('returns non-throwing lock and schedule skip outcomes', function (): void {
    $locks = new class implements LockProviderInterface
    {
        public function acquire(string $key, float $waitSeconds, float $leaseSeconds = 30.0): ?LockHandle
        {
            unset($key, $waitSeconds, $leaseSeconds);

            return null;
        }

        public function refresh(?LockHandle $handle, float $leaseSeconds): bool
        {
            unset($handle, $leaseSeconds);

            return false;
        }

        public function release(?LockHandle $handle): void
        {
            unset($handle);
        }
    };
    $mutex = new CommandMutex($locks);
    $result = $mutex->attempt('busy', static fn(): string => 'unreachable');
    $schedule = new Schedule();
    $schedule->command('busy:task')->everyMinute()->withoutOverlap();
    $runs = new ScheduleRunner($mutex)->runDue(
        $schedule,
        static fn(): int => 0,
        new DateTimeImmutable('2026-01-01 10:00:00 UTC'),
    );

    expect($result->acquired)->toBeFalse()
        ->and($runs)->toHaveCount(1)
        ->and($runs[0]->skipped())->toBeTrue()
        ->and($runs[0]->successful())->toBeFalse();
});

it('scales worker processes incrementally and stops when work is empty', function (): void {
    $probe = new class implements WorkloadProbe
    {
        private int $reads = 0;

        public function pending(): int
        {
            return $this->reads++ === 0 ? 2 : 0;
        }
    };
    $summary = new WorkerSupervisor()->run(
        [PHP_BINARY, '-r', 'exit(0);'],
        $probe,
        new WorkerOptions(
            maximumProcesses: 2,
            scaleStep: 2,
            pollIntervalSeconds: 0.02,
            scaleCooldownSeconds: 0.0,
            maximumProcessesStarted: 2,
            stopWhenEmpty: true,
        ),
    );

    expect($summary->started)->toBe(2)
        ->and($summary->completed)->toBe(2)
        ->and($summary->failed)->toBe(0)
        ->and($summary->forced)->toBe(0)
        ->and($summary->successful())->toBeTrue();
});
