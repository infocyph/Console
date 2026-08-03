<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

use Closure;
use Infocyph\Console\Command\Capability;
use Infocyph\InterMix\DI\Container;

final class FakeCapabilityLoader
{
    /** @var list<Capability> */
    private array $loaded = [];

    /**
     * @return Closure Capability configuration callback.
     * @phpstan-return Closure(Container): void
     * @psalm-return Closure(Container): void
     */
    public function configurer(Capability $capability): Closure
    {
        return function () use ($capability): void {
            $this->loaded[] = $capability;
        };
    }

    /** @return list<Capability> */
    public function loaded(): array
    {
        return $this->loaded;
    }

    public function wasLoaded(Capability $capability): bool
    {
        return in_array($capability, $this->loaded, true);
    }
}
