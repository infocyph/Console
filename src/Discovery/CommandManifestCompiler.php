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
        $entryPrefix = $this->entryPrefix($path);
        $index = [];
        $currentEntries = [];
        foreach ($descriptors as $name => $descriptor) {
            $filename = $entryPrefix . hash('sha256', (string) $name) . '.php';
            $this->publish(
                $directory . DIRECTORY_SEPARATOR . $filename,
                "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($descriptor, true) . ";\n",
            );
            $currentEntries[$filename] = true;
            $index[$name] = [
                'file' => $filename,
                'aliases' => $descriptor['aliases'] ?? [],
                'hidden' => $descriptor['hidden'] ?? false,
            ];
        }

        $this->publish(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
            . var_export(['version' => 2, 'commands' => $index], true)
            . ";\n",
        );
        $this->pruneEntries($directory, $entryPrefix, $currentEntries);
        $this->removeLegacyDirectory($path . '.d');
    }

    /**
     * @return list<string>
     */
    private function entries(string $directory, string $prefix): array
    {
        $entries = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $entry) {
            if (str_starts_with(basename($entry), $prefix)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function entryPrefix(string $path): string
    {
        return pathinfo(basename($path), PATHINFO_FILENAME) . '-';
    }

    /**
     * @param array<string, true> $current
     */
    private function pruneEntries(string $directory, string $prefix, array $current): void
    {
        foreach ($this->entries($directory, $prefix) as $entry) {
            if (!isset($current[basename($entry)]) && !unlink($entry)) {
                throw new \RuntimeException(sprintf('Unable to remove stale command manifest entry "%s".', $entry));
            }
        }
    }

    private function publish(string $path, string $contents): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write temporary command manifest "%s".', $temporary));
        }
        if (rename($temporary, $path)) {
            return;
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }

        throw new \RuntimeException(sprintf('Unable to publish command manifest "%s".', $path));
    }

    private function removeLegacyDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $entry) {
            if (!unlink($entry)) {
                throw new \RuntimeException(sprintf('Unable to remove legacy command manifest entry "%s".', $entry));
            }
        }
        if (!rmdir($directory)) {
            throw new \RuntimeException(sprintf('Unable to remove legacy command manifest directory "%s".', $directory));
        }
    }
}
