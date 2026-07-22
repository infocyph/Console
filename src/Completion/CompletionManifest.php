<?php

declare(strict_types=1);

namespace Infocyph\Console\Completion;

/** @internal */
final readonly class CompletionManifest
{
    /** @param array<string,array{aliases:list<string>,options:list<string>}> $commands */
    private function __construct(private array $commands) {}

    /** @param array<string,array{aliases:list<string>,options:list<string>}> $commands */
    public static function fromArray(array $commands): self
    {
        return new self($commands);
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Completion manifest "%s" does not exist.', $path));
        }

        $manifest = require $path;
        if (!is_array($manifest)) {
            throw new \UnexpectedValueException('Completion manifest must return an array.');
        }

        return new self($manifest);
    }

    /** @return array<string,array{aliases:list<string>,options:list<string>}> */
    public function commands(): array
    {
        return $this->commands;
    }
}
