<?php

declare(strict_types=1);

namespace Infocyph\Console\Input;

final readonly class OptionCollection
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
        return $this->requireType($name, 'boolean');
    }

    public function float(string $name): float
    {
        return $this->requireType($name, 'double');
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
        return $this->requireType($name, 'integer');
    }

    public function nullableFloat(string $name): ?float
    {
        return $this->nullableType($name, 'double');
    }

    public function nullableInt(string $name): ?int
    {
        return $this->nullableType($name, 'integer');
    }

    public function nullableString(string $name): ?string
    {
        return $this->nullableType($name, 'string');
    }

    public function string(string $name): string
    {
        return $this->requireType($name, 'string');
    }

    private function nullableType(string $name, string $type): mixed
    {
        $value = $this->get($name);
        if ($value === null) {
            return null;
        }
        if (gettype($value) !== $type) {
            throw new \UnexpectedValueException(sprintf('Option "%s" is not a %s.', $name, $type));
        }

        return $value;
    }

    private function requireType(string $name, string $type): mixed
    {
        $value = $this->get($name);
        if (gettype($value) !== $type) {
            throw new \UnexpectedValueException(sprintf('Option "%s" is not a %s.', $name, $type));
        }

        return $value;
    }
}
