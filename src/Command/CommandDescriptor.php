<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\Input\Argument;
use Infocyph\Console\Input\Option;
use Infocyph\Console\Support\ManifestValue;

/**
 * @internal
 */
final readonly class CommandDescriptor
{
    /**
     * @param class-string<CommandContract> $class
     * @param list<Argument> $arguments
     * @param list<Option> $options
     * @param list<string> $aliases
     * @param list<Capability> $capabilities
     */
    public function __construct(
        private string $class,
        private string $name,
        private string $description,
        private array $arguments,
        private array $options,
        private array $aliases,
        private bool $hidden,
        private array $capabilities,
        private bool $requiresOtp = false,
    ) {}

    /**
     * @param class-string<CommandContract> $class
     */
    public static function fromClass(string $class, ?string $name = null): self
    {
        $definition = new CommandDefinition();
        $class::define($definition);

        if ($name !== null) {
            $definition->name($name);
        }

        return $definition->descriptor($class);
    }

    /** @param array<string,mixed> $manifest */
    public static function fromManifest(array $manifest): self
    {
        $class = ManifestValue::string($manifest['class'] ?? null, 'command.class');
        if ($class === '' || !is_a($class, CommandContract::class, true)) {
            throw new \UnexpectedValueException(sprintf(
                'Command manifest class "%s" must implement %s.',
                $class,
                CommandContract::class,
            ));
        }

        $arguments = [];
        foreach (ManifestValue::mapList($manifest['arguments'] ?? [], 'command.arguments') as $argument) {
            $arguments[] = Argument::fromManifest($argument);
        }
        $options = [];
        foreach (ManifestValue::mapList($manifest['options'] ?? [], 'command.options') as $option) {
            $options[] = Option::fromManifest($option);
        }
        $capabilities = [];
        foreach (ManifestValue::stringList($manifest['capabilities'] ?? [], 'command.capabilities') as $value) {
            $capability = Capability::tryFrom($value);
            if ($capability !== null) {
                $capabilities[] = $capability;
            }
        }

        return new self(
            $class,
            ManifestValue::string($manifest['name'] ?? null, 'command.name'),
            ManifestValue::string($manifest['description'] ?? null, 'command.description', ''),
            $arguments,
            $options,
            ManifestValue::stringList($manifest['aliases'] ?? [], 'command.aliases'),
            ManifestValue::bool($manifest['hidden'] ?? null, 'command.hidden'),
            $capabilities,
            ManifestValue::bool($manifest['requires_otp'] ?? null, 'command.requires_otp'),
        );
    }

    /** @return list<string> */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /** @return list<Argument> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return list<Capability> */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    /** @return class-string<CommandContract> */
    public function class(): string
    {
        return $this->class;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function hidden(): bool
    {
        return $this->hidden;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<Option> */
    public function options(): array
    {
        return $this->options;
    }

    public function requiresOtp(): bool
    {
        return $this->requiresOtp;
    }

    /** @return array<string,mixed> */
    public function toManifest(): array
    {
        return ['class' => $this->class, 'name' => $this->name, 'description' => $this->description, 'arguments' => array_map(static fn(Argument $argument): array => $argument->toManifest(), $this->arguments), 'options' => array_map(static fn(Option $option): array => $option->toManifest(), $this->options), 'aliases' => $this->aliases, 'hidden' => $this->hidden, 'capabilities' => array_map(static fn(Capability $capability): string => $capability->value, $this->capabilities), 'requires_otp' => $this->requiresOtp];
    }
}
