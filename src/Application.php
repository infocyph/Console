<?php

declare(strict_types=1);

namespace Infocyph\Console;

use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Command\CommandRegistry;
use Infocyph\Console\Command\CommandResolver;
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
        private CommandResolver $commands,
        private IO $io,
        private ?string $completionManifest = null,
    ) {}

    public static function configure(): ApplicationBuilder
    {
        return new ApplicationBuilder();
    }

    /** @param list<string>|null $argv */
    public function run(?array $argv = null): int
    {
        $io = $this->io;

        try {
            [$global, $commandName, $commandTokens] = $this->splitGlobalOptions($argv ?? $_SERVER['argv'] ?? []);
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

                return ExitCode::SUCCESS;
            }

            if ($commandName === null) {
                $this->renderApplicationHelp($io);

                return ExitCode::SUCCESS;
            }

            if ($commandName === 'list') {
                $this->renderCommandList($io);

                return ExitCode::SUCCESS;
            }

            if ($commandName === 'completion') {
                return $this->renderCompletion($commandTokens[0] ?? 'bash', $io);
            }

            if ($commandName === 'help') {
                $requested = $commandTokens[0] ?? null;
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

            $descriptor = $this->registry->find($commandName);
            if ($descriptor === null) {
                return $this->commandNotFound($commandName, $io);
            }

            if ($global['help']) {
                $this->renderCommandHelp($descriptor, $io);

                return ExitCode::SUCCESS;
            }

            $this->commands->useProfile($global['profile']);
            $input = new ArgvParser()->parse($descriptor, $commandTokens);

            return $this->commands->run($descriptor, $input, $io);
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
            $this->renderUnexpectedError($exception, $io, $global['verbosity'] ?? 0);

            return $exception instanceof ProvidesExitCode ? $exception->exitCode() : ExitCode::FAILURE;
        }
    }

    public function withIO(IO $io): self
    {
        return new self($this->metadata, $this->registry, $this->commands, $io, $this->completionManifest);
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

    private function renderApplicationHelp(IO $io): void
    {
        $io->text(sprintf('Usage: %s <command> [options]', $this->metadata->name()));
        $io->text('');
        $this->renderCommandList($io);
        $io->text('');
        $io->text('Global options:');
        $io->text('  -h, --help       Display help for a command');
        $io->text('  -V, --version    Display the application version');
        $io->text('  -q, --quiet      Suppress normal output');
        $io->text('  -v, -vv, -vvv    Increase diagnostic verbosity');
        $io->text('  -n, --no-interaction  Disable interactive prompts');
        $io->text('  completion [shell]    Generate bash, zsh, or fish completion');
    }

    private function renderCommandHelp(CommandDescriptor $command, IO $io): void
    {
        $usage = $this->metadata->name() . ' ' . $command->name();
        foreach ($command->arguments() as $argument) {
            $name = $argument->isVariadic() ? $argument->name() . '...' : $argument->name();
            $usage .= $argument->isRequired() ? ' <' . $name . '>' : ' [' . $name . ']';
        }
        $usage .= ' [options]';

        $io->text('Usage: ' . $usage);
        if ($command->description() !== '') {
            $io->text('');
            $io->text($command->description());
        }

        if ($command->arguments() !== []) {
            $io->text('');
            $io->text('Arguments:');
            foreach ($command->arguments() as $argument) {
                $io->text(sprintf('  %-24s %s', $argument->name(), $argument->descriptionText()));
            }
        }

        if ($command->options() !== []) {
            $io->text('');
            $io->text('Options:');
            foreach ($command->options() as $option) {
                $names = '--' . $option->name();
                if ($option->shortName() !== null) {
                    $names = '-' . $option->shortName() . ', ' . $names;
                }
                if ($option->acceptsValue()) {
                    $names .= '=VALUE';
                }
                $io->text(sprintf('  %-24s %s', $names, $option->descriptionText()));
            }
        }
    }

    private function renderCommandList(IO $io): void
    {
        $io->text('Available commands:');
        foreach ($this->registry->visible() as $command) {
            $io->text(sprintf('  %-24s %s', $command->name(), $command->description()));
        }
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

    /** @param list<string> $argv @return array{array{help: bool, version: bool, quiet: bool, noInteraction: bool, format: string, ansi: ?bool, width: ?int, verbosity: int, profile: ?string}, string|null, list<string>} */
    private function splitGlobalOptions(array $argv): array
    {
        $tokens = array_slice($argv, 1);
        $global = ['help' => false, 'version' => false, 'quiet' => false, 'noInteraction' => false, 'format' => 'text', 'ansi' => null, 'width' => null, 'verbosity' => 0, 'profile' => null];
        $command = null;
        $commandTokens = [];
        $endOfOptions = false;

        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($endOfOptions) {
                if ($command === null) {
                    $command = $token;
                } else {
                    $commandTokens[] = $token;
                }

                continue;
            }
            if ($token === '--') {
                if ($command !== null) {
                    $commandTokens[] = '--';
                    array_push($commandTokens, ...array_slice($tokens, $index + 1));

                    break;
                }
                $endOfOptions = true;

                continue;
            }
            if ($token === '--help' || $token === '-h') {
                $global['help'] = true;

                continue;
            }
            if ($token === '--version' || $token === '-V') {
                $global['version'] = true;

                continue;
            }
            if ($token === '--quiet' || $token === '-q') {
                $global['quiet'] = true;

                continue;
            }
            if ($token === '--no-interaction' || $token === '-n') {
                $global['noInteraction'] = true;

                continue;
            }
            if ($token === '--ansi') {
                $global['ansi'] = true;

                continue;
            }
            if ($token === '--no-ansi' || $token === '--no-color') {
                $global['ansi'] = false;

                continue;
            }
            if (preg_match('/^-v+$/', $token) === 1) {
                $global['verbosity'] += strlen($token) - 1;

                continue;
            }
            if (str_starts_with($token, '--format=')) {
                $global['format'] = substr($token, 9);

                continue;
            }
            if (str_starts_with($token, '--width=')) {
                $global['width'] = $this->width(substr($token, 8));

                continue;
            }
            if (str_starts_with($token, '--profile=')) {
                $global['profile'] = substr($token, 10);

                continue;
            }
            if ($token === '--format' || $token === '--width' || $token === '--profile') {
                $index++;
                if (!array_key_exists($index, $tokens)) {
                    throw new UsageException(sprintf('Option "%s" requires a value.', $token));
                }
                if ($token === '--format') {
                    $global['format'] = $tokens[$index];
                }
                if ($token === '--width') {
                    $global['width'] = $this->width($tokens[$index]);
                }
                if ($token === '--profile') {
                    $global['profile'] = $tokens[$index];
                }

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
