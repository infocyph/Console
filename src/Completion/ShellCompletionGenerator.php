<?php

declare(strict_types=1);

namespace Infocyph\Console\Completion;

/** @internal */
final class ShellCompletionGenerator
{
    public function generate(string $shell, string $application, CompletionManifest $manifest): string
    {
        return match ($shell) {
            'bash' => $this->bash($application, $manifest),
            'zsh' => $this->zsh($application, $manifest),
            'fish' => $this->fish($application, $manifest),
            default => throw new \InvalidArgumentException(sprintf('Unsupported shell "%s". Use bash, zsh, or fish.', $shell)),
        };
    }

    private function bash(string $application, CompletionManifest $manifest): string
    {
        $commands = implode(' ', $this->names($manifest));
        $cases = [];
        foreach ($manifest->commands() as $command => $metadata) {
            $names = implode('|', [$command, ...$metadata['aliases']]);
            $cases[] = sprintf("        %s) COMPREPLY=( $(compgen -W '%s' -- \"\$cur\") ) ;;", $names, implode(' ', $metadata['options']));
        }

        return sprintf("_%1\$s_complete() {\n    local cur=\"\${COMP_WORDS[COMP_CWORD]}\" command=\"\${COMP_WORDS[1]}\"\n    if [[ \"\${COMP_CWORD}\" -eq 1 ]]; then\n        COMPREPLY=( $(compgen -W '%2\$s' -- \"\$cur\") )\n        return\n    fi\n    case \"\$command\" in\n%3\$s\n    esac\n}\ncomplete -F _%1\$s_complete %1\$s\n", $application, $commands, implode("\n", $cases));
    }

    private function fish(string $application, CompletionManifest $manifest): string
    {
        $lines = array_map(static fn(string $name): string => sprintf('complete -c %s -f -a %s', $application, escapeshellarg($name)), $this->names($manifest));
        foreach ($manifest->commands() as $command => $metadata) {
            foreach ($metadata['options'] as $option) {
                $lines[] = sprintf('complete -c %s -n %s -l %s', $application, escapeshellarg('__fish_seen_subcommand_from ' . $command), substr($option, 2));
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /** @return list<string> */
    private function names(CompletionManifest $manifest): array
    {
        $names = [];
        foreach ($manifest->commands() as $command => $metadata) {
            $names[] = $command;
            array_push($names, ...$metadata['aliases']);
        }
        sort($names);

        return array_values(array_unique($names));
    }

    private function zsh(string $application, CompletionManifest $manifest): string
    {
        return sprintf("#compdef %1\$s\n_%1\$s() {\n  local -a commands\n  commands=(%2\$s)\n  _describe 'command' commands\n}\n_%1\$s\n", $application, implode(' ', array_map(static fn(string $name): string => "'{$name}'", $this->names($manifest))));
    }
}
