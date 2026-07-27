<?php

declare(strict_types=1);

namespace Infocyph\Console\Infrastructure;

use Closure;
use Infocyph\Console\Command\Capability;
use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Identity\CommandExecution;
use Infocyph\Console\Identity\ExecutionIdGenerator;
use Infocyph\Console\Identity\UidExecutionIdGenerator;
use Infocyph\InterMix\DI\Container;

/** @internal */
final class CapabilityLoader
{
    /** @var \WeakMap<Container, array<string, true>> */
    private \WeakMap $loaded;

    /** @param array<string, list<Closure(Container): void>> $configurers */
    public function __construct(private array $configurers = [], private ?ExecutionIdGenerator $generator = null)
    {
        $this->loaded = new \WeakMap();
    }

    public function load(Container $container, CommandDescriptor $command): ?CommandExecution
    {
        $loaded = $this->loaded[$container] ?? [];
        $identity = false;
        $changed = false;
        foreach ($command->capabilities() as $capability) {
            if ($capability === Capability::IDENTITY) {
                $identity = true;
            }
            if (isset($loaded[$capability->value])) {
                continue;
            }

            foreach ($this->configurers[$capability->value] ?? [] as $configurer) {
                $configurer($container);
            }

            $loaded[$capability->value] = true;
            $changed = true;
        }
        if ($changed) {
            $this->loaded[$container] = $loaded;
        }

        if (!$identity) {
            return null;
        }

        $generator = $this->generator ??= new UidExecutionIdGenerator();
        if (!isset($loaded[ExecutionIdGenerator::class])) {
            $container->definitions()->bind(ExecutionIdGenerator::class, $generator);
            $loaded[ExecutionIdGenerator::class] = true;
            $this->loaded[$container] = $loaded;
        }

        return new CommandExecution($generator->generate(), $command->name(), time());
    }
}
