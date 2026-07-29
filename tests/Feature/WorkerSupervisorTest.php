<?php

declare(strict_types=1);

use Infocyph\Console\Terminal\SignalManager;
use Infocyph\Console\Testing\SubprocessRunner;
use Infocyph\Console\Worker\WorkerOptions;
use Infocyph\Console\Worker\WorkerSupervisor;
use Infocyph\Console\Worker\WorkloadProbe;

/** @return array{events:string,counter:string} */
function workerFixtureFiles(): array
{
    $suffix = bin2hex(random_bytes(8));

    return [
        'events' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'console-worker-events-' . $suffix,
        'counter' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'console-worker-counter-' . $suffix,
    ];
}

/**
 * @param array{events:string,counter:string} $files
 *
 * @return list<string>
 */
function workerFixtureCommand(string $mode, array $files): array
{
    return [
        PHP_BINARY,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'worker.php',
        '--mode=' . $mode,
        '--events=' . $files['events'],
        '--counter=' . $files['counter'],
    ];
}

/** @return array<string, int> */
function recordedWorkerEvents(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $events = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    return $events === false ? [] : array_count_values($events);
}

/** @param array{events:string,counter:string} $files */
function clearWorkerFixtureFiles(array $files): void
{
    foreach ($files as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

it('accounts for successful and failed child processes exactly once', function (): void {
    $files = workerFixtureFiles();

    try {
        $summary = (new WorkerSupervisor)->run(
            workerFixtureCommand('mixed', $files),
            new class implements WorkloadProbe
            {
                public function pending(): int
                {
                    return 2;
                }
            },
            new WorkerOptions(
                maximumProcesses: 2,
                scaleStep: 2,
                pollIntervalSeconds: 0.01,
                scaleCooldownSeconds: 0.0,
                maximumProcessesStarted: 2,
            ),
        );

        expect($summary->started)->toBe(2)
            ->and($summary->completed)->toBe(2)
            ->and($summary->failed)->toBe(1)
            ->and($summary->forced)->toBe(0)
            ->and($summary->interrupted)->toBeFalse()
            ->and($summary->successful())->toBeFalse()
            ->and(recordedWorkerEvents($files['events']))->toMatchArray([
                'started' => 2,
                'completed' => 1,
                'failed' => 1,
            ]);
    } finally {
        clearWorkerFixtureFiles($files);
    }
});

it('stops all children when a worker heartbeat loses its lease', function (): void {
    $files = workerFixtureFiles();

    try {
        $summary = (new WorkerSupervisor)->run(
            workerFixtureCommand('graceful', $files),
            new class implements WorkloadProbe
            {
                public function pending(): int
                {
                    return 1;
                }
            },
            new WorkerOptions(
                minimumProcesses: 1,
                maximumProcessesStarted: 1,
                pollIntervalSeconds: 0.01,
                scaleCooldownSeconds: 0.0,
                terminationGraceSeconds: 0.2,
            ),
            static fn(): bool => (recordedWorkerEvents($files['events'])['started'] ?? 0) < 1,
        );

        expect($summary->started)->toBe(1)
            ->and($summary->completed)->toBe(1)
            ->and($summary->failed)->toBe(0)
            ->and($summary->forced)->toBe(0)
            ->and($summary->interrupted)->toBeTrue()
            ->and(recordedWorkerEvents($files['events'])['terminated'] ?? 0)->toBe(1);
    } finally {
        clearWorkerFixtureFiles($files);
    }
});

it('drains children after a supervisor interrupt', function (): void {
    $files = workerFixtureFiles();
    $signals = new SignalManager;
    $dispatched = false;

    try {
        $summary = (new WorkerSupervisor($signals))->run(
            workerFixtureCommand('graceful', $files),
            new class implements WorkloadProbe
            {
                public function pending(): int
                {
                    return 1;
                }
            },
            new WorkerOptions(
                minimumProcesses: 1,
                maximumProcessesStarted: 1,
                pollIntervalSeconds: 0.01,
                scaleCooldownSeconds: 0.0,
                terminationGraceSeconds: 0.2,
            ),
            static function () use ($signals, $files, &$dispatched): bool {
                if (!$dispatched && (recordedWorkerEvents($files['events'])['started'] ?? 0) === 1) {
                    $dispatched = true;
                    $signals->dispatchInterrupt();
                }

                return true;
            },
        );

        expect($summary->started)->toBe(1)
            ->and($summary->completed)->toBe(1)
            ->and($summary->failed)->toBe(0)
            ->and($summary->forced)->toBe(0)
            ->and($summary->interrupted)->toBeTrue()
            ->and(recordedWorkerEvents($files['events'])['terminated'] ?? 0)->toBe(1);
    } finally {
        clearWorkerFixtureFiles($files);
    }
});

it('scales down ready children and stops when the workload is empty', function (): void {
    if (!function_exists('pcntl_signal')) {
        $this->markTestSkipped('The deterministic graceful-shutdown fixture requires pcntl.');
    }

    $files = workerFixtureFiles();
    $probe = new class ($files['events']) implements WorkloadProbe
    {
        private int $pending = 3;

        public function __construct(private readonly string $events) {}

        public function pending(): int
        {
            $events = recordedWorkerEvents($this->events);
            if ($this->pending === 3 && ($events['started'] ?? 0) === 3) {
                $this->pending = 1;
            } elseif ($this->pending === 1 && ($events['terminated'] ?? 0) >= 2) {
                $this->pending = 0;
            }

            return $this->pending;
        }
    };

    try {
        $summary = (new WorkerSupervisor)->run(
            workerFixtureCommand('graceful', $files),
            $probe,
            new WorkerOptions(
                maximumProcesses: 3,
                scaleStep: 3,
                scaleDownProcesses: true,
                pollIntervalSeconds: 0.01,
                scaleCooldownSeconds: 0.0,
                maximumProcessesStarted: 3,
                terminationGraceSeconds: 0.2,
                stopWhenEmpty: true,
            ),
        );

        expect($summary->started)->toBe(3)
            ->and($summary->completed)->toBe(3)
            ->and($summary->failed)->toBe(0)
            ->and($summary->forced)->toBe(0)
            ->and($summary->interrupted)->toBeFalse()
            ->and($summary->successful())->toBeTrue()
            ->and(recordedWorkerEvents($files['events'])['terminated'] ?? 0)->toBe(3);
    } finally {
        clearWorkerFixtureFiles($files);
    }
});

it('escalates scale-down to a forced stop after the grace period', function (): void {
    if (!function_exists('pcntl_signal')) {
        $this->markTestSkipped('The deterministic stubborn-worker fixture requires pcntl.');
    }

    $files = workerFixtureFiles();
    $probe = new class ($files['events']) implements WorkloadProbe
    {
        public function __construct(private readonly string $events) {}

        public function pending(): int
        {
            return (recordedWorkerEvents($this->events)['started'] ?? 0) === 0 ? 1 : 0;
        }
    };

    try {
        $summary = (new WorkerSupervisor)->run(
            workerFixtureCommand('stubborn', $files),
            $probe,
            new WorkerOptions(
                maximumProcesses: 1,
                scaleDownProcesses: true,
                pollIntervalSeconds: 0.01,
                scaleCooldownSeconds: 0.0,
                maximumProcessesStarted: 1,
                terminationGraceSeconds: 0.03,
                stopWhenEmpty: true,
            ),
        );

        expect($summary->started)->toBe(1)
            ->and($summary->completed)->toBe(1)
            ->and($summary->failed)->toBe(1)
            ->and($summary->forced)->toBe(1)
            ->and($summary->interrupted)->toBeFalse()
            ->and($summary->successful())->toBeFalse();
    } finally {
        clearWorkerFixtureFiles($files);
    }
});

it('completes bounded repeated scale and shutdown soak cycles', function (): void {
    if (!function_exists('pcntl_signal')) {
        $this->markTestSkipped('Worker soak coverage requires pcntl.');
    }

    $result = (new SubprocessRunner)->run(
        [PHP_BINARY, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'benchmarks' . DIRECTORY_SEPARATOR . 'worker-soak.php'],
        ['CONSOLE_WORKER_SOAK_CYCLES' => '3'],
        dirname(__DIR__, 2),
    );
    $summary = json_decode($result->output, true, flags: JSON_THROW_ON_ERROR);

    expect($result->exitCode)->toBe(0)
        ->and($result->errorOutput)->toBe('')
        ->and($summary)->toMatchArray([
            'cycles' => 3,
            'started' => 9,
            'completed' => 9,
            'failed' => 0,
            'forced' => 0,
        ])
        ->and($summary['memory_growth_bytes'])->toBeLessThanOrEqual($summary['maximum_memory_growth_bytes']);
});
