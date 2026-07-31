<?php

declare(strict_types=1);

namespace Infocyph\Console\Support;

/** @internal */
final class PhpManifestWriter
{
    /** @param array<array-key, mixed> $manifest */
    public static function write(array $manifest, string $path, string $name): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create %s directory "%s".', $name, $directory));
        }

        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($manifest, true) . ";\n";
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write temporary %s "%s".', $name, $temporary));
        }
        if (rename($temporary, $path)) {
            return;
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }

        throw new \RuntimeException(sprintf('Unable to publish %s "%s".', $name, $path));
    }
}
