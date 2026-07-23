<?php

declare(strict_types=1);

namespace Infocyph\Console\Benchmarks;

use Infocyph\Console\Application;
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Container\ContainerProvider;
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
