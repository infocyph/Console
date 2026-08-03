<?php

declare(strict_types=1);

namespace Infocyph\Console;

use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Command\CommandExecutionCoordinator;
use Infocyph\Console\Command\CommandListPresenter;
use Infocyph\Console\Command\CommandRegistry;
use Infocyph\Console\Command\CommandResolverProvider;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Completion\CompletionManifest;
use Infocyph\Console\Completion\ShellCompletionGenerator;
use Infocyph\Console\Discovery\CompletionManifestCompiler;
use Infocyph\Console\Exception\AuthorizationDeniedException;
use Infocyph\Console\Exception\ProvidesExitCode;
use Infocyph\Console\Exception\UsageException;
use Infocyph\Console\Input\ArgvParser;
use Infocyph\Console\IO\IO;
use Infocyph\Console\IO\QuietIO;
use Infocyph\Console\Prompt\PromptCancelled;
use Infocyph\Console\Validation\ValidationFailedException;

final readonly class Application
{
    /** @internal */
    public function __construct(
        private ApplicationMetadata $metadata,
        private CommandRegistry $registry,
        /** @var array<string, string> */
        private array $commandGroups,
        private CommandResolverProvider $commands,
        private IO $io,
        private ?string $completionManifest = null,
        private ?CommandExecutionCoordinator $execution = null,
    ) {}

    public static function configure(): ApplicationBuilder
    {
        return new ApplicationBuilder();
    }

    /** @param list<string>|null $argv */
    public function run(?array $argv = null): int
    {
        $io = $this->io;
        $verbosity = 0;

        try {
            $arguments = $argv ?? $this->serverArguments();
            [$global, $commandName, $commandTokens] = $this->splitGlobalOptions($arguments);
            $verbosity = $global['verbosity'];
            $io = $this->configureIO($global);

            return $this->dispatch($global, $commandName, $commandTokens, $arguments, $io);
        } catch (ValidationFailedException $exception) {
            $io->validationFailures(array_map(static fn($failure): array => $failure->toArray(), $exception->failures()));

            return ExitCode::INVALID_USAGE;
        } catch (PromptCancelled $exception) {
            $io->error($exception->getMessage());

            return ExitCode::INTERRUPTED;
        } catch (UsageException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::INVALID_USAGE;
        } catch (AuthorizationDeniedException $exception) {
            $io->error($exception->getMessage());

            return $exception->exitCode();
        } catch (\Throwable $exception) {
            $this->renderUnexpectedError($exception, $io, $verbosity);

            return $exception instanceof ProvidesExitCode ? $exception->exitCode() : ExitCode::FAILURE;
        }
    }

    public function withIO(IO $io): self
    {
        return new self(
            $this->metadata,
            $this->registry,
            $this->commandGroups,
            $this->commands,
            $io,
            $this->completionManifest,
            $this->execution,
        );
    }

    private function commandNotFound(string $name, IO $io): int
    {
        $io->error(sprintf('Command "%s" was not found.', $name));
        $suggestions = $this->registry->suggestions($name);
        if ($suggestions !== []) {
            $io->note('Did you mean ' . implode(', ', $suggestions) . '?');
        }

        return ExitCode::COMMAND_NOT_FOUND;
    }

    private function commandUsage(CommandDescriptor $command): string
    {
        $usage = $this->metadata->name() . ' ' . $command->name();
        foreach ($command->arguments() as $argument) {
            $name = $argument->isVariadic() ? $argument->name() . '...' : $argument->name();
            $usage .= $argument->isRequired() ? ' <' . $name . '>' : ' [' . $name . ']';
        }

        return $usage . ' [options]';
    }

    /**
     * @param array{
     *     help: bool,
     *     version: bool,
     *     quiet: bool,
     *     noInteraction: bool,
     *     format: string,
     *     ansi: ?bool,
     *     width: ?int,
     *     verbosity: int,
     *     profile: ?string
     * } $global
     */
    private function configureIO(array $global): IO
    {
        $io = $global['quiet'] ? new QuietIO($this->io) : $this->io;
        $io->setInteractive(!$global['noInteraction']);
        if (!in_array($global['format'], ['text', 'json'], true)) {
            throw new UsageException('Output format must be text or json.');
        }
        $io->setFormat($global['format']);
        $io->setAnsi($global['ansi']);
        $io->setWidth($global['width']);

        if ($global['version']) {
            $io->text($this->metadata->name() . ' ' . $this->metadata->version());
        }

        return $io;
    }

    /**
     * @param list<string> $tokens
     * @param array{
     *     help: bool,
     *     version: bool,
     *     quiet: bool,
     *     noInteraction: bool,
     *     format: string,
     *     ansi: ?bool,
     *     width: ?int,
     *     verbosity: int,
     *     profile: ?string
     * } $global
     * @param-out array{
     *     help: bool,
     *     version: bool,
     *     quiet: bool,
     *     noInteraction: bool,
     *     format: string,
     *     ansi: ?bool,
     *     width: ?int,
     *     verbosity: int,
     *     profile: ?string
     * } $global
     */
    private function consumeGlobalOption(string $token, array $tokens, int &$index, array &$global): bool
    {
        $flagged = $this->globalFlag($token, $global);
        if ($flagged || $this->globalVerbosity($token, $global)) {
            return true;
        }

        $inline = $this->inlineGlobalValue($token);
        if ($inline !== null) {
            $this->setGlobalValue($inline[0], $inline[1], $global);

            return true;
        }

        if (!in_array($token, ['--format', '--width', '--profile'], true)) {
            return false;
        }

        $index++;
        if (!array_key_exists($index, $tokens)) {
            throw new UsageException(sprintf('Option "%s" requires a value.', $token));
        }
        $this->setGlobalValue($token, $tokens[$index], $global);

        return true;
    }

    /**
     * @param array{
     *     help: bool,
     *     version: bool,
     *     quiet: bool,
     *     noInteraction: bool,
     *     format: string,
     *     ansi: ?bool,
     *     width: ?int,
     *     verbosity: int,
     *     profile: ?string
     * } $global
     * @param list<string> $commandTokens
     * @param list<string> $arguments
     */
    private function dispatch(
        array $global,
        ?string $commandName,
        array $commandTokens,
        array $arguments,
        IO $io,
    ): int {
        if ($global['version']) {
            return ExitCode::SUCCESS;
        }

        if ($commandName === null) {
            $this->renderApplicationHelp($io);

            return ExitCode::SUCCESS;
        }

        $builtInResult = $this->dispatchBuiltIn($commandName, $commandTokens, $io);
        if ($builtInResult !== null) {
            return $builtInResult;
        }

        $descriptor = $this->registry->find($commandName);
        if ($descriptor === null) {
            return $this->commandNotFound($commandName, $io);
        }

        if ($global['help']) {
            $this->renderCommandHelp($descriptor, $io);

            return ExitCode::SUCCESS;
        }

        $commands = $this->commands->get();
        $commands->useProfile($global['profile']);
        $input = new ArgvParser()->parse($descriptor, $commandTokens);

        $inline = static fn(): int => $commands->run($descriptor, $input, $io);

        return $this->execution?->run($descriptor, $arguments, $inline, $io) ?? $inline();
    }

    /** @param list<string> $commandTokens */
    private function dispatchBuiltIn(string $commandName, array $commandTokens, IO $io): ?int
    {
        if ($commandName === 'list') {
            $this->renderCommandList($io);

            return ExitCode::SUCCESS;
        }

        if ($commandName === 'completion') {
            return $this->renderCompletion($commandTokens[0] ?? 'bash', $io);
        }

        return $commandName === 'help' ? $this->renderRequestedHelp($commandTokens[0] ?? null, $io) : null;
    }

    /**
     * @param array{help: bool, version: bool, quiet: bool, noInteraction: bool, format: string, ansi: ?bool, width: ?int, verbosity: int, profile: ?string} $global
     * @param-out array{help: bool, version: bool, quiet: bool, noInteraction: bool, format: string, ansi: ?bool, width: ?int, verbosity: int, profile: ?string} $global
     */
    private function globalFlag(string $token, array &$global): bool
    {
        $updated = match ($token) {
            '--help', '-h' => [...$global, 'help' => true],
            '--version', '-V' => [...$global, 'version' => true],
            '--quiet', '-q' => [...$global, 'quiet' => true],
            '--no-interaction', '-n' => [...$global, 'noInteraction' => true],
            '--ansi' => [...$global, 'ansi' => true],
            '--no-ansi', '--no-color' => [...$global, 'ansi' => false],
            default => null,
        };
        if ($updated === null) {
            return false;
        }

        $global = $updated;

        return true;
    }

    /**
     * @param array{help: bool, version: bool, quiet: bool, noInteraction: bool, format: string, ansi: ?bool, width: ?int, verbosity: int, profile: ?string} $global
     * @param-out array{help: bool, version: bool, quiet: bool, noInteraction: bool, format: string, ansi: ?bool, width: ?int, verbosity: int, profile: ?string} $global
     */
    private function globalVerbosity(string $token, array &$global): bool
    {
        if (preg_match('/^-v+$/', $token) !== 1) {
            return false;
        }

        $global['verbosity'] += strlen($token) - 1;

        return true;
    }

    /** @return array{string, string}|null */
    private function inlineGlobalValue(string $token): ?array
    {
        foreach (['--format=' => '--format', '--width=' => '--width', '--profile=' => '--profile'] as $prefix => $option) {
            if (str_starts_with($token, $prefix)) {
                return [$option, substr($token, strlen($prefix))];
            }
        }

        return null;
    }

    private function renderApplicationHelp(IO $io): void
    {
        $io->title($this->metadata->name());
        $io->paragraph(sprintf('Usage: %s <command> [options]', $this->metadata->name()), 'accent');
        $io->text('');
        $this->renderCommandList($io);
        $io->text('');
        $io->section('Global options:');
        $io->definitions([
            '  -h, --help' => 'Display help for a command',
            '  -V, --version' => 'Display the application version',
            '  -q, --quiet' => 'Suppress normal output',
            '  -v, -vv, -vvv' => 'Increase diagnostic verbosity',
            '  -n, --no-interaction' => 'Disable interactive prompts',
            '  completion [shell]' => 'Generate bash, zsh, or fish completion',
        ]);
    }

    private function renderArgumentHelp(CommandDescriptor $command, IO $io): void
    {
        if ($command->arguments() === []) {
            return;
        }

        $io->text('');
        $io->section('Arguments:');
        $arguments = [];
        foreach ($command->arguments() as $argument) {
            $arguments['  ' . $argument->name()] = $argument->descriptionText();
        }
        $io->definitions($arguments);
    }

    private function renderCommandHelp(CommandDescriptor $command, IO $io): void
    {
        $io->paragraph('Usage: ' . $this->commandUsage($command), 'accent');
        if ($command->description() !== '') {
            $io->text('');
            $io->text($command->description());
        }

        $this->renderArgumentHelp($command, $io);
        $this->renderOptionHelp($command, $io);
    }

    private function renderCommandList(IO $io): void
    {
        new CommandListPresenter($this->commandGroups)->render($this->registry->visible(), $io);
    }

    private function renderCompletion(string $shell, IO $io): int
    {
        try {
            $manifest = $this->completionManifest === null
                ? CompletionManifest::fromArray(new CompletionManifestCompiler()->compile($this->registry->visible()))
                : CompletionManifest::load($this->completionManifest);
            $io->text(new ShellCompletionGenerator()->generate($shell, $this->metadata->name(), $manifest));

            return ExitCode::SUCCESS;
        } catch (\InvalidArgumentException $exception) {
            throw new UsageException($exception->getMessage(), previous: $exception);
        }
    }

    private function renderOptionHelp(CommandDescriptor $command, IO $io): void
    {
        if ($command->options() === []) {
            return;
        }

        $io->text('');
        $io->section('Options:');
        $options = [];
        foreach ($command->options() as $option) {
            $names = '--' . $option->name();
            $names = $option->shortName() === null ? $names : '-' . $option->shortName() . ', ' . $names;
            $names .= $option->acceptsValue() ? '=VALUE' : '';
            $options['  ' . $names] = $option->descriptionText();
        }
        $io->definitions($options);
    }

    private function renderRequestedHelp(?string $requested, IO $io): int
    {
        if ($requested === null) {
            $this->renderApplicationHelp($io);

            return ExitCode::SUCCESS;
        }

        $descriptor = $this->registry->find($requested);
        if ($descriptor === null) {
            return $this->commandNotFound($requested, $io);
        }

        $this->renderCommandHelp($descriptor, $io);

        return ExitCode::SUCCESS;
    }

    private function renderUnexpectedError(\Throwable $exception, IO $io, int $verbosity): void
    {
        $verbosity = max($verbosity, getenv('CONSOLE_DEBUG') !== false ? 3 : 0);
        if ($verbosity === 0) {
            $io->error('An unexpected error occurred.');
            $io->muted('Re-run with -v for diagnostics.');

            return;
        }

        $io->error($exception->getMessage());
        $io->muted(sprintf('%s at %s:%d', $exception::class, $exception->getFile(), $exception->getLine()));
        if ($verbosity >= 2) {
            $trace = explode("\n", $exception->getTraceAsString());
            foreach (array_slice($trace, 0, $verbosity >= 3 ? null : 5) as $line) {
                $io->muted($line);
            }
        }
    }

    /** @return list<string> */
    private function serverArguments(): array
    {
        $arguments = $_SERVER['argv'] ?? [];
        if (!is_array($arguments)) {
            throw new UsageException('Server arguments must be an array.');
        }

        $normalized = [];
        foreach ($arguments as $argument) {
            if (!is_string($argument)) {
                throw new UsageException('Server arguments must contain only strings.');
            }
            $normalized[] = $argument;
        }

        return $normalized;
    }

    /**
     * @param array{help: bool, version: bool, quiet: bool, noInteraction: bool, format: string, ansi: ?bool, width: ?int, verbosity: int, profile: ?string} $global
     * @param-out array{help: bool, version: bool, quiet: bool, noInteraction: bool, format: string, ansi: ?bool, width: ?int, verbosity: int, profile: ?string} $global
     */
    private function setGlobalValue(string $option, string $value, array &$global): void
    {
        if ($option === '--format') {
            $global['format'] = $value;

            return;
        }

        if ($option === '--width') {
            $global['width'] = $this->width($value);

            return;
        }

        $global['profile'] = $value;
    }

    /**
     * @param list<string> $argv
     * @return array{
     *     array{
     *         help: bool,
     *         version: bool,
     *         quiet: bool,
     *         noInteraction: bool,
     *         format: string,
     *         ansi: ?bool,
     *         width: ?int,
     *         verbosity: int,
     *         profile: ?string
     *     },
     *     string|null,
     *     list<string>
     * }
     */
    private function splitGlobalOptions(array $argv): array
    {
        $tokens = array_slice($argv, 1);
        $global = ['help' => false, 'version' => false, 'quiet' => false, 'noInteraction' => false, 'format' => 'text', 'ansi' => null, 'width' => null, 'verbosity' => 0, 'profile' => null];
        $command = null;
        $commandTokens = [];

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token === '--') {
                $remaining = array_slice($tokens, $index + 1);
                if ($command === null) {
                    $command = array_shift($remaining);
                } else {
                    $commandTokens[] = '--';
                }
                array_push($commandTokens, ...$remaining);

                break;
            }
            if ($this->consumeGlobalOption($token, $tokens, $index, $global)) {
                continue;
            }
            if ($command === null) {
                $command = $token;
            } else {
                $commandTokens[] = $token;
            }
        }

        return [$global, $command, $commandTokens];
    }

    private function width(string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 20) {
            throw new UsageException('Terminal width must be an integer of at least 20.');
        }

        return (int) $value;
    }
}
