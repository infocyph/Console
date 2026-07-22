<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

use Infocyph\Console\Command\ExitCode;

final readonly class CommandResult
{
    /** @param list<string> $output @param list<string> $errors */
    public function __construct(private int $exitCode, private array $output, private array $errors) {}

    public function assertAsked(string $question): self
    {
        return $this->assertOutputContains($question . ':');
    }

    public function assertErrorOutput(string $expected): self
    {
        if ($this->normalize($this->errorText()) !== $this->normalize($expected)) {
            $this->fail(sprintf("Expected error output:\n%s\nActual error output:\n%s", $expected, $this->errorText()));
        }

        return $this;
    }

    public function assertExitCode(int $expected): self
    {
        if ($this->exitCode !== $expected) {
            $this->fail(sprintf('Expected exit code %d, got %d.', $expected, $this->exitCode));
        }

        return $this;
    }

    public function assertFailed(): self
    {
        if ($this->exitCode === ExitCode::SUCCESS) {
            $this->fail('Expected the command to fail, but it succeeded.');
        }

        return $this;
    }

    /** @param array<string, mixed>|list<mixed>|string $expected */
    public function assertJson(array|string $expected): self
    {
        $actual = $this->outputText();
        $expectedJson = is_string($expected) ? $expected : json_encode($expected, JSON_THROW_ON_ERROR);
        if (json_decode($actual, true) !== json_decode($expectedJson, true)) {
            $this->fail(sprintf("Expected JSON:\n%s\nActual output:\n%s", $expectedJson, $actual));
        }

        return $this;
    }

    public function assertNotAsked(string $question): self
    {
        if (str_contains($this->outputText(), $question . ':')) {
            $this->fail(sprintf('Did not expect prompt "%s".', $question));
        }

        return $this;
    }

    public function assertOutput(string $expected): self
    {
        if ($this->normalize($this->outputText()) !== $this->normalize($expected)) {
            $this->fail(sprintf("Expected output:\n%s\nActual output:\n%s", $expected, $this->outputText()));
        }

        return $this;
    }

    public function assertOutputContains(string $expected): self
    {
        if (!str_contains($this->outputText(), $expected)) {
            $this->fail(sprintf('Expected output to contain "%s".', $expected));
        }

        return $this;
    }

    public function assertSuccessful(): self
    {
        return $this->assertExitCode(ExitCode::SUCCESS);
    }

    /** @param list<string> $headers @param list<array<array-key, scalar|null>> $rows */
    public function assertTable(array $headers, array $rows): self
    {
        foreach ([...$headers, ...array_merge([], ...$rows)] as $value) {
            if (!str_contains($this->outputText(), (string) $value)) {
                $this->fail(sprintf('Expected table content "%s".', (string) $value));
            }
        }

        return $this;
    }

    public function assertValidationFailed(): self
    {
        return $this->assertExitCode(ExitCode::INVALID_USAGE);
    }

    public function assertValidationPassed(): self
    {
        if ($this->exitCode === ExitCode::INVALID_USAGE) {
            $this->fail('Expected validation to pass, but it failed.');
        }

        return $this;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function errorText(): string
    {
        return implode(PHP_EOL, $this->errors);
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    /** @return list<string> */
    public function output(): array
    {
        return $this->output;
    }

    public function outputText(): string
    {
        return implode(PHP_EOL, $this->output);
    }

    private function fail(string $message): never
    {
        throw new \AssertionError($message);
    }

    private function normalize(string $value): string
    {
        return str_replace("\r\n", "\n", $value);
    }
}
