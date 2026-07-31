<?php

declare(strict_types=1);

// phpcs:disable Generic.PHP.ForbiddenFunctions.Found -- CLI soak failures require non-zero process exits.

require dirname(__DIR__) . '/vendor/autoload.php';

use Infocyph\Console\Worker\WorkerOptions;
use Infocyph\Console\Worker\WorkerStopReason;
use Infocyph\Console\Worker\WorkerSupervisor;
use Infocyph\Console\Worker\WorkloadProbe;

if (!function_exists('pcntl_signal')) {
    fwrite(STDERR, 'Worker soak requires the pcntl extension.' . PHP_EOL);
    exit(2);
}

$cycles = max(1, min(1_000, (int) (getenv('CONSOLE_WORKER_SOAK_CYCLES') ?: 25)));
$maximumGrowthBytes = max(0, (int) (getenv('CONSOLE_WORKER_SOAK_MAX_GROWTH_BYTES') ?: 4_194_304));
$fixture = dirname(__DIR__) . '/tests/Fixtures/worker.php';
$startedAt = hrtime(true);
$initialMemory = memory_get_usage(true);
$totalStarted = 0;
$totalCompleted = 0;
$totalFailed = 0;
$totalForced = 0;

for ($cycle = 1; $cycle <= $cycles; $cycle++) {
    $suffix = getmypid() . '-' . $cycle . '-' . bin2hex(random_bytes(4));
    $events = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'console-worker-soak-events-' . $suffix;
    $counter = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'console-worker-soak-counter-' . $suffix;
    $probe = new class ($events) implements WorkloadProbe {
        private int $pending = 3;

        public function __construct(private readonly string $events) {}

        public function pending(): int
        {
            $recorded = is_file($this->events)
                ? file($this->events, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
                : [];
            $counts = is_array($recorded) ? array_count_values($recorded) : [];
            if ($this->pending === 3 && ($counts['started'] ?? 0) === 3) {
                $this->pending = 1;
            } elseif ($this->pending === 1 && ($counts['terminated'] ?? 0) >= 2) {
                $this->pending = 0;
            }

            return $this->pending;
        }
    };

    try {
        $summary = (new WorkerSupervisor())->run(
            [
                PHP_BINARY,
                $fixture,
                '--mode=graceful',
                '--events=' . $events,
                '--counter=' . $counter,
            ],
            $probe,
            new WorkerOptions(
                maximumProcesses: 3,
                scaleStep: 3,
                scaleDownProcesses: true,
                pollIntervalSeconds: 0.005,
                scaleCooldownSeconds: 0.0,
                maximumProcessesStarted: 3,
                terminationGraceSeconds: 0.2,
                stopWhenEmpty: true,
            ),
        );
    } finally {
        foreach ([$events, $counter] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    $totalStarted += $summary->started;
    $totalCompleted += $summary->completed;
    $totalFailed += $summary->failed;
    $totalForced += $summary->forced;

    if (
        $summary->started !== 3
        || $summary->completed !== 3
        || $summary->failed !== 0
        || $summary->forced !== 0
        || $summary->interrupted
        || $summary->stopReason !== WorkerStopReason::EMPTY
    ) {
        fwrite(STDERR, sprintf('Worker soak cycle %d produced an invalid summary.%s', $cycle, PHP_EOL));
        exit(1);
    }
}

$finalMemory = memory_get_usage(true);
$memoryGrowth = max(0, $finalMemory - $initialMemory);
$result = [
    'cycles' => $cycles,
    'duration_seconds' => (hrtime(true) - $startedAt) / 1_000_000_000,
    'started' => $totalStarted,
    'completed' => $totalCompleted,
    'failed' => $totalFailed,
    'forced' => $totalForced,
    'initial_memory_bytes' => $initialMemory,
    'final_memory_bytes' => $finalMemory,
    'memory_growth_bytes' => $memoryGrowth,
    'maximum_memory_growth_bytes' => $maximumGrowthBytes,
];

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);

if ($memoryGrowth > $maximumGrowthBytes) {
    fwrite(STDERR, sprintf(
        'Worker soak memory growth of %d bytes exceeded the %d-byte limit.%s',
        $memoryGrowth,
        $maximumGrowthBytes,
        PHP_EOL,
    ));
    exit(1);
}
