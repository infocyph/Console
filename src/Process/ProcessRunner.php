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

        $this->writeInput($pipes, $options);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return $this->monitor($process, $pipes, $options);
    }

    private function cancelled(ProcessOptions $options): bool
    {
        return $options->cancelled !== null && (bool) ($options->cancelled)();
    }

    /**
     * @param array<int, resource> $pipes
     * @param-out string $output
     * @param-out string $errors
     */
    private function drain(array $pipes, SensitiveValueRedactor $redactor, ProcessOptions $options, string &$output, string &$errors): void
    {
        foreach ([$pipes[1], $pipes[2]] as $stream) {
            $remaining = stream_get_contents($stream);
            if (!is_string($remaining) || $remaining === '') {
                continue;
            }
            $error = $stream === $pipes[2];
            $this->emit($error, $redactor->push($error ? 'stderr' : 'stdout', $remaining), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);
        }
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

    /**
     * @param resource $process
     * @param array<int, resource> $pipes
     */
    private function monitor($process, array $pipes, ProcessOptions $options): ProcessResult
    {
        $redactor = new SensitiveValueRedactor($options->sensitiveValues);
        $output = '';
        $errors = '';
        $startedAt = microtime(true);
        $lastActivity = $startedAt;
        $this->interrupted = false;
        $termination = null;
        $reportedExitCode = null;

        try {
            while (true) {
                $status = proc_get_status($process);
                if ($status['exitcode'] >= 0) {
                    $reportedExitCode = $status['exitcode'];
                }
                $now = microtime(true);
                if (!$status['running']) {
                    $this->drain($pipes, $redactor, $options, $output, $errors);

                    break;
                }

                $termination = $this->terminationReason($options, $now, $startedAt, $lastActivity);
                if ($termination !== null) {
                    proc_terminate($process, $termination === 'cancelled' && defined('SIGINT') ? SIGINT : 15);

                    break;
                }

                $activity = $this->readAvailable($pipes, $redactor, $options, $output, $errors);
                if ($activity === null) {
                    break;
                }
                if ($activity) {
                    $lastActivity = microtime(true);
                }
            }
        } finally {
            $this->drain($pipes, $redactor, $options, $output, $errors);
            fclose($pipes[1]);
            fclose($pipes[2]);
        }

        $this->emit(false, $redactor->flush('stdout'), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);
        $this->emit(true, $redactor->flush('stderr'), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);

        $closedExitCode = proc_close($process);
        $exitCode = $reportedExitCode ?? $closedExitCode;
        if ($termination !== null) {
            $exitCode = ExitCode::INTERRUPTED;
        }

        return new ProcessResult(
            $exitCode,
            $output,
            $errors,
            $termination === 'timeout',
            $termination === 'idle-timeout',
            $termination === 'cancelled',
        );
    }

    /**
     * @param array<int, resource> $pipes
     * @param-out string $output
     * @param-out string $errors
     */
    private function readAvailable(array $pipes, SensitiveValueRedactor $redactor, ProcessOptions $options, string &$output, string &$errors): ?bool
    {
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;
        if (stream_select($read, $write, $except, 0, 100_000) === false) {
            return null;
        }

        $activity = false;
        foreach ($read as $stream) {
            $chunk = stream_get_contents($stream);
            if ($chunk === false || $chunk === '') {
                continue;
            }
            $activity = true;
            $error = $stream === $pipes[2];
            $this->emit($error, $redactor->push($error ? 'stderr' : 'stdout', $chunk), $options, $output, $errors, $options->mode === ProcessMode::CAPTURE);
        }

        return $activity;
    }

    private function terminationReason(ProcessOptions $options, float $now, float $startedAt, float $lastActivity): ?string
    {
        if ($this->cancelled($options) || $this->interrupted) {
            return 'cancelled';
        }
        if ($options->timeoutSeconds !== null && $now - $startedAt >= $options->timeoutSeconds) {
            return 'timeout';
        }
        if ($options->idleTimeoutSeconds !== null && $now - $lastActivity >= $options->idleTimeoutSeconds) {
            return 'idle-timeout';
        }

        return null;
    }

    /** @param array<int, resource> $pipes */
    private function writeInput(array $pipes, ProcessOptions $options): void
    {
        if (!isset($pipes[0])) {
            return;
        }

        if (is_string($options->input) && $options->input !== '') {
            fwrite($pipes[0], $options->input);
        }
        if (is_resource($options->input)) {
            stream_copy_to_stream($options->input, $pipes[0]);
        }
        fclose($pipes[0]);
    }
}
