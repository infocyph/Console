<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Support\PhpManifestWriter;

/** @internal */
final class ValidationManifestCompiler
{
    /**
     * @param list<CommandDescriptor> $commands
     * @return array<string, array<string, array{rules: list<string>, sanitizers: list<string>}>>
     */
    public function compile(array $commands): array
    {
        $manifest = [];
        foreach ($commands as $command) {
            $fields = [];
            foreach ([...$command->arguments(), ...$command->options()] as $definition) {
                if ($definition->ruleset() !== [] || $definition->sanitizers() !== []) {
                    $fields[$definition->name()] = ['rules' => $definition->ruleset(), 'sanitizers' => $definition->sanitizers()];
                }
            }
            if ($fields !== []) {
                $manifest[$command->name()] = $fields;
            }
        }
        ksort($manifest);

        return $manifest;
    }

    /** @param list<CommandDescriptor> $commands */
    public function write(array $commands, string $path): void
    {
        PhpManifestWriter::write($this->compile($commands), $path, 'validation manifest');
    }
}
