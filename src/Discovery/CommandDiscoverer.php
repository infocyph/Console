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
            if (!is_dir($path)) {
                throw new \InvalidArgumentException(sprintf('Command discovery path "%s" does not exist.', $path));
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $before = get_declared_classes();
                require_once $file->getPathname();
                foreach (array_diff(get_declared_classes(), $before) as $class) {
                    if (is_a($class, CommandContract::class, true) && !new \ReflectionClass($class)->isAbstract()) {
                        $commands[] = $class;
                    }
                }
            }
        }
        sort($commands);

        return new DiscoveryResult(array_values(array_unique($commands)));
    }
}
