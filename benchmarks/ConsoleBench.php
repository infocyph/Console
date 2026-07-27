<?php

declare(strict_types=1);

namespace Infocyph\Console\Benchmarks;

use Infocyph\Console\Application;
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Container\ContainerProvider;
use Infocyph\Console\Input\Argument;
use Infocyph\Console\Input\Option;
use Infocyph\Console\Input\ValueType;
use Infocyph\Console\IO\BufferedIO;
use Infocyph\InterMix\DI\Container;
use PhpBench\Attributes as Bench;

#[Bench\Revs(100)]
#[Bench\Iterations(5)]
#[Bench\Warmup(2)]
final class ConsoleBench
{
    private Application $external;

    private Application $standalone;

    private Application $typed;

    public function setUp(): void
    {
        $this->standalone = Application::configure()
            ->commands([BenchmarkCommand::class])
            ->io(new BufferedIO())
            ->build();

        $container = new Container('console.benchmark.external');
        $provider = new class ($container) implements ContainerProvider {
            public function __construct(private readonly Container $container) {}

            public function container(): Container
            {
                return $this->container;
            }
        };
        $this->external = Application::configure()
            ->commands([BenchmarkCommand::class])
            ->containerProvider($provider)
            ->io(new BufferedIO())
            ->build();

        $this->typed = Application::configure()
            ->commands([BenchmarkTypedCommand::class])
            ->io(new BufferedIO())
            ->build();
    }

    public function benchApplicationBuild(): void
    {
        Application::configure()
            ->commands([BenchmarkCommand::class])
            ->io(new BufferedIO())
            ->build();
    }

    #[Bench\BeforeMethods('setUp')]
    public function benchExternalContainerDispatch(): void
    {
        $this->external->run(['console', '--quiet', 'benchmark:noop']);
    }

    #[Bench\BeforeMethods('setUp')]
    public function benchPreflightVersion(): void
    {
        $this->external->run(['console', '--quiet', '--version']);
    }

    #[Bench\BeforeMethods('setUp')]
    public function benchStandaloneDispatch(): void
    {
        $this->standalone->run(['console', '--quiet', 'benchmark:noop']);
    }

    #[Bench\BeforeMethods('setUp')]
    public function benchTypedInputDispatch(): void
    {
        $this->typed->run([
            'console',
            '--quiet',
            'benchmark:typed',
            '42',
            '--force',
            '--tag=one',
            '--tag=two',
        ]);
    }
}

final class BenchmarkCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('benchmark:noop');
    }

    protected function handle(): int
    {
        return ExitCode::SUCCESS;
    }
}

final class BenchmarkTypedCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command
            ->name('benchmark:typed')
            ->argument(Argument::required('id')->type(ValueType::INTEGER))
            ->option(Option::flag('force'))
            ->option(Option::multiple('tag'));
    }

    protected function handle(): int
    {
        return ExitCode::SUCCESS;
    }
}
