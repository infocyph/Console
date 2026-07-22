<?php

declare(strict_types=1);

namespace Infocyph\Console\Prompt;

final class AnswerQueue implements InputReader
{
    /** @param list<mixed> $answers */
    public function __construct(private array $answers = []) {}

    public function isEmpty(): bool
    {
        return $this->answers === [];
    }

    public function push(mixed $answer): void
    {
        $this->answers[] = $answer;
    }

    public function read(bool $secret = false): mixed
    {
        return $secret ? array_shift($this->answers) : array_shift($this->answers);
    }

    public function readKey(): ?string
    {
        $value = array_shift($this->answers);

        return $value === null ? null : (string) $value;
    }
}
