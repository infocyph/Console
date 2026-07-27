<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\Support\ManifestValue;

/**
 * @internal
 */
final class CommandRegistry
{
    /** @var array<string, CommandDescriptor|string> */
    private array $byName = [];

    /** @var list<CommandDescriptor|string> */
    private array $commands = [];

    /** @var array<string, string> */
    private array $indexedFiles = [];

    /**
     * @param array<array-key, class-string<CommandContract>> $commands
     */
    public function __construct(array $commands)
    {
        foreach ($commands as $name => $command) {
            $this->register(CommandDescriptor::fromClass(
                $command,
                is_string($name) ? $name : null,
            ));
        }
    }

    /** @param array<string, mixed> $manifest */
    public static function fromIndexedManifest(array $manifest, string $baseDirectory): self
    {
        $registry = new self([]);
        $commands = ManifestValue::map($manifest['commands'] ?? null, 'commands');
        foreach ($commands as $name => $value) {
            $summary = ManifestValue::map($value, 'commands.' . $name);
            $registry->registerIndexed(
                $name,
                ManifestValue::stringList($summary['aliases'] ?? [], 'commands.' . $name . '.aliases'),
                $baseDirectory . DIRECTORY_SEPARATOR . ManifestValue::string(
                    $summary['file'] ?? null,
                    'commands.' . $name . '.file',
                ),
            );
        }

        return $registry;
    }

    /** @param array<string, mixed> $manifest */
    public static function fromManifest(array $manifest): self
    {
        $registry = new self([]);
        foreach ($manifest as $name => $descriptor) {
            $registry->register(CommandDescriptor::fromManifest(
                ManifestValue::map($descriptor, 'commands.' . $name),
            ));
        }

        return $registry;
    }

    public function find(string $name): ?CommandDescriptor
    {
        $command = $this->byName[$name] ?? null;
        if ($command instanceof CommandDescriptor || $command === null) {
            return $command;
        }

        return $this->loadIndexed($command);
    }

    /** @return list<string> */
    public function suggestions(string $name, int $limit = 3): array
    {
        $needle = strtolower($name);
        $candidates = [];
        foreach (array_keys($this->byName) as $candidate) {
            $distance = levenshtein($needle, strtolower($candidate));
            $threshold = max(2, (int) floor(max(strlen($needle), strlen($candidate)) / 3));
            if ($distance <= $threshold || str_contains(strtolower($candidate), $needle)) {
                $candidates[$candidate] = $distance;
            }
        }
        asort($candidates, SORT_NUMERIC);

        return array_slice(array_keys($candidates), 0, $limit);
    }

    /** @return list<CommandDescriptor> */
    public function visible(): array
    {
        $visible = [];
        foreach ($this->commands as $command) {
            $descriptor = $command instanceof CommandDescriptor ? $command : $this->loadIndexed($command);
            if (!$descriptor->hidden()) {
                $visible[] = $descriptor;
            }
        }

        return $visible;
    }

    private function loadIndexed(string $name): CommandDescriptor
    {
        $file = $this->indexedFiles[$name] ?? null;
        if ($file === null || !is_file($file)) {
            throw new \RuntimeException(sprintf('Compiled descriptor for command "%s" is unavailable.', $name));
        }

        $manifest = require $file;
        if (!is_array($manifest)) {
            throw new \RuntimeException(sprintf('Compiled descriptor for command "%s" is invalid.', $name));
        }
        $command = CommandDescriptor::fromManifest(
            ManifestValue::map($manifest, 'commands.' . $name),
        );
        foreach ([$command->name(), ...$command->aliases()] as $candidate) {
            $this->byName[$candidate] = $command;
        }

        return $command;
    }

    private function register(CommandDescriptor $command): void
    {
        foreach ([$command->name(), ...$command->aliases()] as $name) {
            if (isset($this->byName[$name])) {
                throw new \InvalidArgumentException(sprintf('Command name or alias "%s" is already registered.', $name));
            }

            $this->byName[$name] = $command;
        }

        $this->commands[] = $command;
    }

    /** @param list<string> $aliases */
    private function registerIndexed(string $name, array $aliases, string $file): void
    {
        foreach ([$name, ...$aliases] as $candidate) {
            if (isset($this->byName[$candidate])) {
                throw new \InvalidArgumentException(sprintf('Command name or alias "%s" is already registered.', $candidate));
            }
            $this->byName[$candidate] = $name;
        }

        $this->indexedFiles[$name] = $file;
        $this->commands[] = $name;
    }
}
