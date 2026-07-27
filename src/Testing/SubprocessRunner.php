<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

final class SubprocessRunner
{
    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    public function run(array $command, array $environment = [], ?string $workingDirectory = null, string $input = ''): SubprocessResult
    {
        if ($command === []) {
            throw new \InvalidArgumentException('A subprocess command is required.');
        }

        $processEnvironment = $environment === [] ? null : array_replace(getenv(), $environment);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $workingDirectory, $processEnvironment);
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start subprocess.');
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return new SubprocessResult(proc_close($process), $output === false ? '' : $output, $errors === false ? '' : $errors);
    }
}
