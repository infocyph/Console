<?php

declare(strict_types=1);

namespace Infocyph\Console\Input;

final readonly class ArgumentCollection
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }

    public function bool(string $name): bool
    {
        $value = $this->get($name);
        if (!is_bool($value)) {
            throw new \UnexpectedValueException(sprintf('Argument "%s" is not a boolean.', $name));
        }

        return $value;
    }

    public function float(string $name): float
    {
        $value = $this->get($name);
        if (!is_float($value)) {
            throw new \UnexpectedValueException(sprintf('Argument "%s" is not a double.', $name));
        }

        return $value;
    }

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function int(string $name): int
    {
        $value = $this->get($name);
        if (!is_int($value)) {
            throw new \UnexpectedValueException(sprintf('Argument "%s" is not an integer.', $name));
        }

        return $value;
    }

    public function nullableFloat(string $name): ?float
    {
        $value = $this->get($name);
        if ($value !== null && !is_float($value)) {
            throw new \UnexpectedValueException(sprintf('Argument "%s" is not a double.', $name));
        }

        return $value;
    }

    public function nullableInt(string $name): ?int
    {
        $value = $this->get($name);
        if ($value !== null && !is_int($value)) {
            throw new \UnexpectedValueException(sprintf('Argument "%s" is not an integer.', $name));
        }

        return $value;
    }

    public function nullableString(string $name): ?string
    {
        $value = $this->get($name);
        if ($value !== null && !is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Argument "%s" is not a string.', $name));
        }

        return $value;
    }

    public function string(string $name): string
    {
        $value = $this->get($name);
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Argument "%s" is not a string.', $name));
        }

        return $value;
    }
}
