<?php

declare(strict_types=1);

namespace Infocyph\Console\Validation;

use Infocyph\Console\Support\ManifestValue;

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

        $manifest = ManifestValue::map(require $path, 'validation');
        $commands = [];
        foreach ($manifest as $commandName => $fieldsValue) {
            $fields = ManifestValue::map($fieldsValue, 'validation.' . $commandName);
            foreach ($fields as $fieldName => $definitionValue) {
                $definition = ManifestValue::map(
                    $definitionValue,
                    'validation.' . $commandName . '.' . $fieldName,
                );
                $commands[$commandName][$fieldName] = [
                    'rules' => ManifestValue::stringList(
                        $definition['rules'] ?? [],
                        'validation.' . $commandName . '.' . $fieldName . '.rules',
                    ),
                    'sanitizers' => ManifestValue::stringList(
                        $definition['sanitizers'] ?? [],
                        'validation.' . $commandName . '.' . $fieldName . '.sanitizers',
                    ),
                ];
            }
        }

        return new self($commands);
    }

    /** @return array<string,array{rules:list<string>,sanitizers:list<string>}> */
    public function for(string $command): array
    {
        return $this->commands[$command] ?? [];
    }
}
