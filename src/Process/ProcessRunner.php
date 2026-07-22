<?php

declare(strict_types=1);

namespace Infocyph\Console\Process;

use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Terminal\SignalManager;

final class ProcessRunner
{
    private bool $interrupted = false;

    public function __construct(?SignalManager $signals = null)
    {
        $signals?->onInterrupt(function (): void {
            $this->interrupted = true;
        });
    }

    /** @param list<string> $command */
    public function run(array $command, ?ProcessOptions $options = null): ProcessResult
    {
        if ($command === [] || $command[0] === '') {
            throw new \InvalidArgumentException('A process command is required.');
        }
        $options ??= new ProcessOptions();
        if ($options->mode === ProcessMode::INHERIT) {
            return $this->inherit($command, $options);
        }
        $inherited = getenv();
        $environment = $options->environment === [] ? null : array_replace($inherited, $options->environment);
        $inputDescriptor = $options->inheritInput && $options->passthrough ? STDIN : ['pipe', 'r'];
        $process = proc_open($command, [0 => $inputDescriptor, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $options->workingDirectory, $environment);
        if (!is_resource($process)) {
            return new ProcessResult(ExitCode::CANNOT_EXECUTE, '', 'Process could not be started.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            if (is_string($options->input) && $options->input !== '') {
                fwrite($pipes[0], $options->input);
            }
            if (is_resource($options->input)) {
                stream_copy_to_stream($options->input, $pipes[0]);
            }
            fclose($pipes[0]);
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $redactor = new SensitiveValueRedactor($options->sensitiveValues);
        $output = '';
        $errors = '';
        $startedAt = microtime(true);
        $lastActivity = $startedAt;
        $this->interrupted = false;
        $timedOut = false;
        $idleTimedOut = false;
        $cancelled = false;
        $reportedExitCode = null;

        try {
            while (true) {
                $status = proc_get_status($process);
                if ($status['exitcode'] >= 0) {
                    $reportedExitCode = $status['exitcode'];
                }
                $now = microtime(true);
                if (!$status['running']) {
                    foreach ([$pipes[1], $pipes[2]] as $stream) {
                        $remaining = stream_get_contents($stream);
                        if (!is_string($remaining) || $remaining === '') {
                            continue;
                        }
                        $this->emit($stream === $pipes[2], $redactor->push($stream === $pipes[2] ? 'stderr' : 'stdout', $remaining), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);
                    }

                    break;
                }
                if ($this->cancelled($options) || $this->interrupted) {
                    $cancelled = true;
                    proc_terminate($process, defined('SIGINT') ? SIGINT : 15);

                    break;
                }
                if ($options->timeoutSeconds !== null && $now - $startedAt >= $options->timeoutSeconds) {
                    $timedOut = true;
                    proc_terminate($process);

                    break;
                }
                if ($options->idleTimeoutSeconds !== null && $now - $lastActivity >= $options->idleTimeoutSeconds) {
                    $idleTimedOut = true;
                    proc_terminate($process);

                    break;
                }

                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;
                $ready = stream_select($read, $write, $except, 0, 100_000);
                if ($ready === false) {
                    break;
                }
                foreach ($read as $stream) {
                    $chunk = stream_get_contents($stream);
                    if ($chunk === false || $chunk === '') {
                        continue;
                    }
                    $lastActivity = microtime(true);
                    $this->emit($stream === $pipes[2], $redactor->push($stream === $pipes[2] ? 'stderr' : 'stdout', $chunk), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);
                }
            }
        } finally {
            foreach ([$pipes[1], $pipes[2]] as $stream) {
                $remaining = stream_get_contents($stream);
                if (is_string($remaining) && $remaining !== '') {
                    $this->emit($stream === $pipes[2], $redactor->push($stream === $pipes[2] ? 'stderr' : 'stdout', $remaining), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);
                }
                fclose($stream);
            }
        }

        $this->emit(false, $redactor->flush('stdout'), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);
        $this->emit(true, $redactor->flush('stderr'), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);

        $closedExitCode = proc_close($process);
        $exitCode = $reportedExitCode ?? $closedExitCode;
        if ($timedOut || $idleTimedOut || $cancelled) {
            $exitCode = ExitCode::INTERRUPTED;
        }

        return new ProcessResult($exitCode, $output, $errors, $timedOut, $idleTimedOut, $cancelled);
    }

    private function cancelled(ProcessOptions $options): bool
    {
        return $options->cancelled !== null && (bool) ($options->cancelled)();
    }

    private function emit(bool $error, string $chunk, ProcessOptions $options, string &$output, string &$errors, bool $capture): void
    {
        if ($chunk === '') {
            return;
        }
        if ($error) {
            if ($capture) {
                $errors .= $chunk;
            }
            if ($options->onErrorOutput !== null) {
                ($options->onErrorOutput)($chunk);
            }
            if ($options->passthrough) {
                fwrite(STDERR, $chunk);
            }

            return;
        }
        if ($capture) {
            $output .= $chunk;
        }
        if ($options->onOutput !== null) {
            ($options->onOutput)($chunk);
        }
        if ($options->passthrough) {
            fwrite(STDOUT, $chunk);
        }
    }

    /** @param list<string> $command */
    private function inherit(array $command, ProcessOptions $options): ProcessResult
    {
        $environment = $options->environment === [] ? null : array_replace(getenv(), $options->environment);
        $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $options->workingDirectory, $environment);
        if (!is_resource($process)) {
            return new ProcessResult(ExitCode::CANNOT_EXECUTE, '', 'Process could not be started.');
        }
        $started = microtime(true);
        while (($status = proc_get_status($process))['running']) {
            $timedOut = $options->timeoutSeconds !== null && microtime(true) - $started >= $options->timeoutSeconds;
            if ($this->cancelled($options) || $this->interrupted || $timedOut) {
                proc_terminate($process);

                return new ProcessResult(ExitCode::INTERRUPTED, '', '', $timedOut);
            }
            usleep(10_000);
        }

        return new ProcessResult(proc_close($process), '', '');
    }
}
