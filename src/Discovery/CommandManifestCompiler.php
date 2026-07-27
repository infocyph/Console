<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandContract;
use Infocyph\Console\Command\CommandDescriptor;

final class CommandManifestCompiler
{
    /**
     * @param list<class-string<CommandContract>>|array<string, class-string<CommandContract>> $commands
     * @return array<string,array<string,mixed>>
     */
    public function compile(array $commands): array
    {
        $manifest = [];
        foreach ($commands as $name => $class) {
            $descriptor = CommandDescriptor::fromClass($class, is_string($name) ? $name : null);
            if (isset($manifest[$descriptor->name()])) {
                throw new \InvalidArgumentException(sprintf('Command "%s" is already in the manifest.', $descriptor->name()));
            }
            $manifest[$descriptor->name()] = $descriptor->toManifest();
        }
        ksort($manifest);

        return $manifest;
    }

    /** @param list<class-string<CommandContract>>|array<string, class-string<CommandContract>> $commands */
    public function write(array $commands, string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create manifest directory "%s".', $directory));
        }
        $descriptors = $this->compile($commands);
        $entryDirectory = $path . '.d';
        if (!is_dir($entryDirectory) && !mkdir($entryDirectory, 0777, true) && !is_dir($entryDirectory)) {
            throw new \RuntimeException(sprintf('Unable to create manifest entry directory "%s".', $entryDirectory));
        }

        $index = [];
        foreach ($descriptors as $name => $descriptor) {
            $filename = hash('sha256', (string) $name) . '.php';
            $entryPath = $entryDirectory . DIRECTORY_SEPARATOR . $filename;
            $temporaryEntry = $entryPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
            file_put_contents($temporaryEntry, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($descriptor, true) . ";\n", LOCK_EX);
            if (!rename($temporaryEntry, $entryPath)) {
                if (is_file($temporaryEntry)) {
                    unlink($temporaryEntry);
                }

                throw new \RuntimeException(sprintf('Unable to publish manifest entry "%s".', $entryPath));
            }
            $index[$name] = [
                'file' => basename($entryDirectory) . DIRECTORY_SEPARATOR . $filename,
                'aliases' => $descriptor['aliases'] ?? [],
                'hidden' => $descriptor['hidden'] ?? false,
            ];
        }

        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($temporary, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export(['version' => 2, 'commands' => $index], true) . ";\n", LOCK_EX);
        if (!rename($temporary, $path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new \RuntimeException(sprintf('Unable to publish manifest "%s".', $path));
        }
    }
}
