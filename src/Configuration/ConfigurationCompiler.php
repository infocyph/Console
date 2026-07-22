<?php

declare(strict_types=1);

namespace Infocyph\Console\Configuration;

final class ConfigurationCompiler
{
    public function compile(Configuration $configuration, string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create configuration directory "%s".', $directory));
        }
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($temporary, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($configuration->all(), true) . ";\n", LOCK_EX);
        if (!rename($temporary, $path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new \RuntimeException(sprintf('Unable to publish compiled configuration "%s".', $path));
        }
    }
}
