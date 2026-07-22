<?php

declare(strict_types=1);

namespace Infocyph\Console\Discovery;

use Infocyph\Console\Command\CommandDescriptor;

/** @internal */
final class ValidationManifestCompiler
{
    /** @param list<CommandDescriptor> $commands @return array<string,array<string,array{rules:list<string>,sanitizers:list<string>}>> */
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
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create manifest directory "%s".', $directory));
        }
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($temporary, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($this->compile($commands), true) . ";\n", LOCK_EX);
        if (!rename($temporary, $path)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }

            throw new \RuntimeException(sprintf('Unable to publish manifest "%s".', $path));
        }
    }
}
