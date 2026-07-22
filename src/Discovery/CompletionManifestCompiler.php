<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandDescriptor;

/** @internal */
final class CompletionManifestCompiler
{
    /** @param list<CommandDescriptor> $commands @return array<string,array{aliases:list<string>,options:list<string>}> */
    public function compile(array $commands): array
    {
        $result = [];
        foreach ($commands as $command) {
            $result[$command->name()] = ['aliases' => $command->aliases(), 'options' => array_map(static fn($option): string => '--' . $option->name(), $command->options())];
        }
        ksort($result);

        return $result;
    }

    /** @param list<CommandDescriptor> $commands */
    public function write(array $commands, string $path): void
    {
        $this->writeManifest($this->compile($commands), $path);
    }

    /** @param array<string,array{aliases:list<string>,options:list<string>}> $manifest */
    private function writeManifest(array $manifest, string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create manifest directory "%s".', $directory));
        }
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($temporary, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($manifest, true) . ";\n", LOCK_EX);
        if (!rename($temporary, $path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new \RuntimeException(sprintf('Unable to publish manifest "%s".', $path));
        }
    }
}
