<?php

declare(strict_types=1);

namespace Infocyph\Console\Completion;

use Infocyph\Console\Support\ManifestValue;

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

        $manifest = ManifestValue::map(require $path, 'completion');
        $commands = [];
        foreach ($manifest as $name => $value) {
            $command = ManifestValue::map($value, 'completion.' . $name);
            $commands[$name] = [
                'aliases' => ManifestValue::stringList(
                    $command['aliases'] ?? [],
                    'completion.' . $name . '.aliases',
                ),
                'options' => ManifestValue::stringList(
                    $command['options'] ?? [],
                    'completion.' . $name . '.options',
                ),
            ];
        }

        return new self($commands);
    }

    /** @return array<string,array{aliases:list<string>,options:list<string>}> */
    public function commands(): array
    {
        return $this->commands;
    }
}
