<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Infocyph\Console\Command\CommandRegistry;
use Infocyph\Console\Component\Table;
use Infocyph\Console\Input\ArgvParser;
use Infocyph\Console\Prompt\OptionViewport;
use Infocyph\Console\Render\AnsiRenderer;
use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\FrameDiffer;
use Infocyph\Console\Render\PlainRenderer;

/** @return float elapsed milliseconds */
function benchmark(callable $operation, int $iterations): float
{
    $started = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $operation();
    }

    return (hrtime(true) - $started) / 1_000_000;
}

$iterations = max(1, (int) (getenv('CONSOLE_BENCHMARK_ITERATIONS') ?: 10_000));
$emptyRegistry = new CommandRegistry([]);
$frame = Frame::line('Console benchmark output.', 'info');
$benchmarks = [
    'command_lookup' => static fn(): mixed => $emptyRegistry->find('missing'),
    'argv_parser_boot' => static fn(): ArgvParser => new ArgvParser(),
    'plain_render' => static fn(): string => (new PlainRenderer())->render($frame),
    'ansi_render' => static fn(): string => (new AnsiRenderer())->render($frame),
    'table_render' => static fn(): string => (new PlainRenderer())->render((new Table(['Name', 'State'], [['Console', 'ready']]))->frame()),
    'frame_diff' => static fn(): array => (new FrameDiffer())->changedLineIndexes($frame, $frame),
    'prompt_filter' => static fn(): array => (new OptionViewport(['one' => 'First', 'two' => 'Second', 'three' => 'Third']))->filter('sec')->visible(),
];

$results = [];
foreach ($benchmarks as $name => $operation) {
    $results[$name] = benchmark($operation, $iterations);
}
ksort($results);
fwrite(STDOUT, json_encode(['iterations' => $iterations, 'milliseconds' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL);

if (in_array('--enforce', $_SERVER['argv'] ?? [], true)) {
    $limit = (float) (getenv('CONSOLE_BENCHMARK_MAX_MS') ?: 2_500);
    foreach ($results as $name => $milliseconds) {
        if ($milliseconds > $limit) {
            fwrite(STDERR, sprintf("Benchmark %s exceeded %.2fms.\n", $name, $limit));

            throw new RuntimeException(sprintf('Benchmark %s exceeded %.2fms.', $name, $limit));
        }
    }
}
