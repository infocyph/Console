<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

/**
 * Read-only compiled semantic-validation metadata.
 *
 * @internal
 */
final readonly class ValidationManifest
{
    /** @param array<string,array<string,array{rules:list<string>,sanitizers:list<string>}>> $commands */
    private function __construct(private array $commands) {}

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Validation manifest "%s" does not exist.', $path));
        }

        $manifest = require $path;
        if (!is_array($manifest)) {
            throw new \UnexpectedValueException('Validation manifest must return an array.');
        }

        return new self($manifest);
    }

    /** @return array<string,array{rules:list<string>,sanitizers:list<string>}> */
    public function for(string $command): array
    {
        return $this->commands[$command] ?? [];
    }
}
