<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

use Infocyph\Console\Application;
use Infocyph\Console\IO\BufferedIO;

final class PendingCommand
{
    /** @var list<mixed> */
    private array $answers = [];

    /** @var list<array{name: string, value: scalar|null}> */
    private array $arguments = [];

    /** @var list<array{name: string, value: scalar|list<scalar>|null}> */
    private array $options = [];

    public function __construct(private readonly Application $application, private readonly string $name) {}

    public function answer(string $question, mixed $answer): self
    {
        if ($question === '') {
            throw new \InvalidArgumentException('A prompt question is required.');
        }
        $this->answers[] = $answer;

        return $this;
    }

    public function argument(string $name, string|int|float|bool|null $value): self
    {
        $this->arguments[] = ['name' => $name, 'value' => $value];

        return $this;
    }

    /** @param scalar|list<scalar>|null $value */
    public function option(string $name, string|int|float|bool|array|null $value = true): self
    {
        $this->options[] = ['name' => $name, 'value' => $value];

        return $this;
    }

    public function run(): CommandResult
    {
        $io = new BufferedIO($this->answers);
        $argv = ['console', $this->name];
        foreach ($this->arguments as $argument) {
            $argv[] = $this->stringify($argument['value']);
        }
        foreach ($this->options as $option) {
            $value = $option['value'];
            if ($value === true) {
                $argv[] = '--' . $option['name'];
            } elseif ($value === false) {
                $argv[] = '--no-' . $option['name'];
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    $argv[] = '--' . $option['name'] . '=' . $this->stringify($item);
                }
            } elseif ($value !== null) {
                $argv[] = '--' . $option['name'] . '=' . $this->stringify($value);
            }
        }

        $exitCode = $this->application->withIO($io)->run($argv);

        return new CommandResult($exitCode, $io->output(), $io->errors());
    }

    private function stringify(string|int|float|bool|null $value): string
    {
        return match ($value) {
            null => '',
            true => 'true',
            false => 'false',
            default => (string) $value,
        };
    }
}
