<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandContract;

/** @internal */
final class CommandDiscoverer
{
    /** @param list<string> $paths */
    public function discover(array $paths): DiscoveryResult
    {
        $commands = [];
        foreach ($paths as $path) {
            array_push($commands, ...$this->discoverPath($path));
        }
        sort($commands);

        return new DiscoveryResult(array_values(array_unique($commands)));
    }

    /** @return list<class-string<CommandContract>> */
    private function discoverPath(string $path): array
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf('Command discovery path "%s" does not exist.', $path));
        }

        $commands = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            array_push($commands, ...$this->loadCommands($file->getPathname()));
        }

        return $commands;
    }

    /** @return list<class-string<CommandContract>> */
    private function loadCommands(string $file): array
    {
        $before = get_declared_classes();
        require_once $file;

        $commands = [];
        foreach (array_diff(get_declared_classes(), $before) as $class) {
            if (is_a($class, CommandContract::class, true) && !new \ReflectionClass($class)->isAbstract()) {
                $commands[] = $class;
            }
        }

        return $commands;
    }
}
