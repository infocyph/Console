<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\IO\IO;

/**
 * Renders command ownership as an optional two-level hierarchy.
 *
 * @internal
 */
final readonly class CommandListPresenter
{
    /** @param array<string, string> $groups Command-name-to-group-path map. */
    public function __construct(private array $groups) {}

    /** @param list<CommandDescriptor> $commands */
    public function render(array $commands, IO $io): void
    {
        $io->section('Available commands:');

        $grouped = [];
        foreach ($commands as $command) {
            $path = $this->path($command);
            $grouped[$path['group']][$path['subgroup'] ?? ''][] = $command;
        }

        foreach ($grouped as $group => $subgroups) {
            $io->text('');
            $io->section($group . ':');
            $this->renderSubgroups($subgroups, $io);
        }
    }

    /** @return array{group: string, subgroup: string|null} */
    private function path(CommandDescriptor $command): array
    {
        $name = $command->name();
        if (isset($this->groups[$name])) {
            $parts = explode('/', $this->groups[$name], 2);

            return ['group' => $parts[0], 'subgroup' => $parts[1] ?? null];
        }

        $separator = strpos($name, ':');

        return [
            'group' => $separator === false ? 'Application' : substr($name, 0, $separator),
            'subgroup' => null,
        ];
    }

    /** @param array<string, list<CommandDescriptor>> $subgroups */
    private function renderSubgroups(array $subgroups, IO $io): void
    {
        $first = true;
        foreach ($subgroups as $subgroup => $commands) {
            if ($subgroup !== '') {
                if (!$first) {
                    $io->text('');
                }
                $io->paragraph('  ' . $subgroup . ':', 'heading');
            }

            $indent = $subgroup === '' ? '  ' : '    ';
            $definitions = [];
            foreach ($commands as $command) {
                $definitions[$indent . $command->name()] = $command->description();
            }
            $io->definitions($definitions);
            $first = false;
        }
    }
}
