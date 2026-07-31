<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Support\PhpManifestWriter;

/** @internal */
final class CompletionManifestCompiler
{
    /**
     * @param list<CommandDescriptor> $commands
     * @return array<string, array{aliases: list<string>, options: list<string>}>
     */
    public function compile(array $commands): array
    {
        $result = [];
        foreach ($commands as $command) {
            $options = [];
            foreach ($command->options() as $option) {
                $options[] = '--' . $option->name();
            }
            $result[$command->name()] = ['aliases' => $command->aliases(), 'options' => $options];
        }
        ksort($result);

        return $result;
    }

    /** @param list<CommandDescriptor> $commands */
    public function write(array $commands, string $path): void
    {
        $this->writeManifest($this->compile($commands), $path);
    }

    /** @param array<string, array{aliases: list<string>, options: list<string>}> $manifest */
    private function writeManifest(array $manifest, string $path): void
    {
        PhpManifestWriter::write($manifest, $path, 'completion manifest');
    }
}
