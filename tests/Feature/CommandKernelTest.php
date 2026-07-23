<?php

declare(strict_types=1);

use Infocyph\Console\Application;
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Input\Argument;
use Infocyph\Console\Input\Option;
use Infocyph\Console\Input\ValueType;
use Infocyph\Console\IO\BufferedIO;

final class KernelFixtureCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command
            ->name('users:create')
            ->description('Create a user.')
            ->argument(Argument::required('id')->type(ValueType::INTEGER))
            ->option(Option::flag('force')->shortcut('f')->negatable()->env('CONSOLE_FORCE'))
            ->option(Option::value('age')->shortcut('a')->type(ValueType::INTEGER))
            ->option(Option::multiple('tag')->shortcut('t'));
    }

    protected function handle(): int
    {
        $this->io()->success(json_encode([
            'id' => $this->arguments()->int('id'),
            'force' => $this->options()->bool('force'),
            'age' => $this->options()->nullableInt('age'),
            'tags' => $this->options()->get('tag'),
        ], JSON_THROW_ON_ERROR));

        return ExitCode::SUCCESS;
    }
}

final class UnconstructedFixtureCommand extends Command
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

    public static function define(CommandDefinition $command): void
    {
        $command->name('unconstructed');
    }

    protected function handle(): int
    {
        return ExitCode::SUCCESS;
    }
}

final class VariadicEnvironmentFixtureCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('ids:show')->argument(Argument::variadic('ids')->type(ValueType::INTEGER)->env('CONSOLE_IDS', ':'));
    }

    protected function handle(): int
    {
        $this->io()->success(json_encode($this->arguments()->get('ids'), JSON_THROW_ON_ERROR));

        return ExitCode::SUCCESS;
    }
}

it('parses typed arguments, flags, short options, and multiple values', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()
        ->name('tool')
        ->version('1.0.0')
        ->commands([KernelFixtureCommand::class])
        ->io($io)
        ->build();

    expect($application->run(['tool', 'users:create', '42', '-f', '--age=30', '-tone', '--tag', 'two']))->toBe(0)
        ->and($io->output())->toBe(['[OK] {"id":42,"force":true,"age":30,"tags":["one","two"]}']);
});

it('uses command map keys as the authoritative route names', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()
        ->commands(['accounts:create' => KernelFixtureCommand::class])
        ->io($io)
        ->build();

    expect($application->run(['tool', 'accounts:create', '42']))->toBe(ExitCode::SUCCESS)
        ->and($io->output())->toBe(['[OK] {"id":42,"force":false,"age":null,"tags":[]}'])
        ->and($application->run(['tool', 'users:create', '42']))->toBe(ExitCode::COMMAND_NOT_FOUND);
});

it('uses the command preflight paths without constructing registered commands', function (): void {
    UnconstructedFixtureCommand::$instances = 0;
    $io = new BufferedIO;
    $application = Application::configure()
        ->name('tool')
        ->version('1.2.3')
        ->commands([UnconstructedFixtureCommand::class])
        ->io($io)
        ->build();

    expect($application->run(['tool', '--version']))->toBe(0)
        ->and($io->output())->toBe(['tool 1.2.3'])
        ->and(UnconstructedFixtureCommand::$instances)->toBe(0);
});

it('reports parser failures as invalid usage', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()
        ->commands([KernelFixtureCommand::class])
        ->io($io)
        ->build();

    expect($application->run(['tool', 'users:create', '--age', 'old']))->toBe(ExitCode::INVALID_USAGE)
        ->and($io->errors())->toBe(['[ERROR] Value for "--age" must be an integer.']);
});

it('preserves the command delimiter and uses typed environment defaults', function (): void {
    putenv('CONSOLE_FORCE=false');
    try {
        $io = new BufferedIO;
        $application = Application::configure()
            ->commands([KernelFixtureCommand::class])
            ->io($io)
            ->build();

        expect($application->run(['tool', 'users:create', '--', '-5']))->toBe(ExitCode::SUCCESS)
            ->and($io->output())->toBe(['[OK] {"id":-5,"force":false,"age":null,"tags":[]}']);
    } finally {
        putenv('CONSOLE_FORCE');
    }
});

it('rejects incompatible option and argument defaults at definition time', function (): void {
    expect(fn (): Argument => Argument::optional('count', 'invalid')->type(ValueType::INTEGER))->toThrow(InvalidArgumentException::class)
        ->and(fn (): Argument => Argument::variadic('ids')->type(ValueType::INTEGER)->default(['bad']))->toThrow(InvalidArgumentException::class)
        ->and(fn (): Argument => Argument::variadic('ids')->env('CONSOLE_IDS'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): Option => Option::flag('force')->type(ValueType::STRING))->toThrow(LogicException::class)
        ->and(fn (): Option => Option::multiple('tag')->default('one'))->toThrow(InvalidArgumentException::class);
});

it('splits variadic environment values with their declared delimiter', function (): void {
    putenv('CONSOLE_IDS=10:20:30');
    try {
        $io = new BufferedIO;
        $application = Application::configure()->commands([VariadicEnvironmentFixtureCommand::class])->io($io)->build();

        expect($application->run(['tool', 'ids:show']))->toBe(ExitCode::SUCCESS)
            ->and($io->output())->toBe(['[OK] [10,20,30]']);
    } finally {
        putenv('CONSOLE_IDS');
    }
});
