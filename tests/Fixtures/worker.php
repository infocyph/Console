<?php

declare(strict_types=1);

// phpcs:disable Generic.PHP.ForbiddenFunctions.Found -- The subprocess fixture must expose exact exit states.

/**
 * Deterministic subprocess used by WorkerSupervisor integration and soak tests.
 *
 * @param non-empty-string $event
 */
function recordWorkerEvent(string $path, string $event): void
{
    if (file_put_contents($path, $event . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
        fwrite(STDERR, 'Unable to record worker fixture event.' . PHP_EOL);
        exit(70);
    }
}

/** @return positive-int */
function claimWorkerOrdinal(string $path): int
{
    $stream = fopen($path, 'c+');
    if ($stream === false || !flock($stream, LOCK_EX)) {
        fwrite(STDERR, 'Unable to lock worker fixture counter.' . PHP_EOL);
        exit(70);
    }

    $contents = stream_get_contents($stream);
    $ordinal = max(0, (int) ($contents === false ? 0 : trim($contents))) + 1;
    rewind($stream);
    ftruncate($stream, 0);
    fwrite($stream, (string) $ordinal);
    fflush($stream);
    flock($stream, LOCK_UN);
    fclose($stream);

    return $ordinal;
}

$options = getopt('', ['counter::', 'events:', 'lifetime-us::', 'mode:']);
$mode = $options['mode'] ?? null;
$events = $options['events'] ?? null;
$counter = $options['counter'] ?? null;
$lifetime = max(1_000, (int) ($options['lifetime-us'] ?? 20_000));

if (!is_string($mode) || $mode === '' || !is_string($events) || $events === '') {
    fwrite(STDERR, 'Worker fixture requires --mode and --events.' . PHP_EOL);
    exit(64);
}

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}

if ($mode === 'graceful' && function_exists('pcntl_signal') && defined('SIGTERM')) {
    pcntl_signal(SIGTERM, static function () use ($events): never {
        recordWorkerEvent($events, 'terminated');
        exit(0);
    });
}

if ($mode === 'stubborn' && function_exists('pcntl_signal') && defined('SIGTERM')) {
    pcntl_signal(SIGTERM, SIG_IGN);
}

recordWorkerEvent($events, 'started');

if ($mode === 'failure') {
    recordWorkerEvent($events, 'failed');
    exit(23);
}

if ($mode === 'mixed') {
    if (!is_string($counter) || $counter === '') {
        fwrite(STDERR, 'Mixed worker fixture requires --counter.' . PHP_EOL);
        exit(64);
    }

    $ordinal = claimWorkerOrdinal($counter);
    usleep($lifetime);
    recordWorkerEvent($events, $ordinal % 2 === 0 ? 'failed' : 'completed');
    exit($ordinal % 2 === 0 ? 23 : 0);
}

if ($mode === 'success') {
    usleep($lifetime);
    recordWorkerEvent($events, 'completed');
    exit(0);
}

if ($mode !== 'graceful' && $mode !== 'stubborn') {
    fwrite(STDERR, 'Unknown worker fixture mode.' . PHP_EOL);
    exit(64);
}

while (true) {
    usleep(10_000);
}
