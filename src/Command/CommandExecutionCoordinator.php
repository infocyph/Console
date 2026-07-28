<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\CacheLayer\Cache\Lock\LockHandle;
use Infocyph\Console\Cache\CommandMutex;
use Infocyph\Console\IO\IO;
use Infocyph\Console\Process\ProcessMode;
use Infocyph\Console\Process\ProcessOptions;
use Infocyph\Console\Process\ProcessRunner;

/**
 * @internal
 */
final class CommandExecutionCoordinator
{
    private const string CHILD_ENVIRONMENT_KEY = 'INFOCYPH_CONSOLE_SUPERVISED';

    private ?CommandMutex $mutex = null;

    /** @param null|\Closure(): CommandMutex $mutexFactory */
    public function __construct(
        private readonly ProcessRunner $processes,
        private readonly ?\Closure $mutexFactory = null,
        private readonly ?string $executable = null,
    ) {}

    /**
     * @param list<string> $arguments
     * @param callable(): int $inline
     */
    public function run(
        CommandDescriptor $command,
        array $arguments,
        callable $inline,
        IO $io,
    ): int {
        $policy = $command->execution();
        if (!$policy->requiresSupervisor() || getenv(self::CHILD_ENVIRONMENT_KEY) === '1') {
            return $inline();
        }

        $handle = null;
        $mutex = null;
        if ($policy->overlap !== OverlapMode::ALLOW) {
            $mutex = $this->mutex() ?? throw new \LogicException(sprintf(
                'Command "%s" requires a configured lock provider.',
                $command->name(),
            ));
            $handle = $mutex->acquire(
                $policy->mutex ?? $command->name(),
                $policy->overlap === OverlapMode::WAIT ? $policy->waitSeconds : 0.0,
                $policy->leaseSeconds,
            );
            if ($handle === null) {
                $io->note(sprintf('Command "%s" is already running; this execution was skipped.', $command->name()));

                return ExitCode::SUCCESS;
            }
        }

        try {
            return $this->runChild($policy, $arguments, $handle, $mutex);
        } finally {
            $mutex?->release($handle);
        }
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    private function command(CommandExecutionPolicy $policy, array $arguments): array
    {
        $executable = $this->executable ?? $arguments[0] ?? null;
        if ($executable === null || $executable === '' || !is_file($executable)) {
            throw new \LogicException('Isolated commands require an executable application entry point.');
        }

        $command = [PHP_BINARY];
        if ($policy->memoryLimitMegabytes !== null) {
            $command[] = '-d';
            $command[] = 'memory_limit=' . $policy->memoryLimitMegabytes . 'M';
        }
        $command[] = $executable;
        array_push($command, ...array_slice($arguments, 1));

        return $command;
    }

    private function heartbeat(
        CommandExecutionPolicy $policy,
        ?LockHandle $handle,
        ?CommandMutex $mutex,
    ): ?\Closure {
        if ($handle === null || $mutex === null) {
            return null;
        }

        $nextRefresh = microtime(true) + max(0.1, $policy->leaseSeconds / 3);

        return static function () use ($handle, $mutex, $policy, &$nextRefresh): bool {
            if (microtime(true) < $nextRefresh) {
                return true;
            }
            $nextRefresh = microtime(true) + max(0.1, $policy->leaseSeconds / 3);

            return $mutex->refresh($handle, $policy->leaseSeconds);
        };
    }

    private function mutex(): ?CommandMutex
    {
        if ($this->mutex !== null || $this->mutexFactory === null) {
            return $this->mutex;
        }

        return $this->mutex = ($this->mutexFactory)();
    }

    /**
     * @param list<string> $arguments
     */
    private function runChild(
        CommandExecutionPolicy $policy,
        array $arguments,
        ?LockHandle $handle,
        ?CommandMutex $mutex,
    ): int {
        $result = $this->processes->run(
            $this->command($policy, $arguments),
            new ProcessOptions(
                environment: [self::CHILD_ENVIRONMENT_KEY => '1'],
                timeoutSeconds: $policy->timeoutSeconds,
                idleTimeoutSeconds: $policy->idleTimeoutSeconds,
                heartbeat: $this->heartbeat($policy, $handle, $mutex),
                passthrough: true,
                inheritInput: true,
                mode: ProcessMode::STREAM,
                terminationGraceSeconds: $policy->terminationGraceSeconds,
            ),
        );

        return $result->exitCode;
    }
}
