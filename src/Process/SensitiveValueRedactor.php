<?php

declare(strict_types=1);

namespace Infocyph\Console\Process;

/** @internal */
final class SensitiveValueRedactor
{
    /** @var array<string,string> */
    private array $pending = [];

    /** @param list<string> $values */
    public function __construct(private readonly array $values) {}

    public function flush(string $stream): string
    {
        $value = $this->pending[$stream] ?? '';
        unset($this->pending[$stream]);

        return $this->redact($value);
    }

    public function push(string $stream, string $chunk): string
    {
        $value = ($this->pending[$stream] ?? '') . $chunk;
        $values = array_values(array_filter(array_unique($this->values), static fn(string $value): bool => $value !== ''));
        $length = max(array_map(strlen(...), $values) ?: [0]);
        if ($length === 0) {
            return $value;
        }

        // Retain enough context to both find a split secret and avoid emitting
        // its leading bytes before the remaining bytes arrive in a later chunk.
        $emitLength = max(0, strlen($value) - $length + 1);
        foreach ($values as $secret) {
            $position = strrpos($value, $secret);
            if ($position !== false && $position + strlen($secret) > $emitLength) {
                $emitLength = min($emitLength, $position);
            }
        }
        $emit = substr($value, 0, $emitLength);
        $this->pending[$stream] = substr($value, $emitLength);

        return $this->redact($emit);
    }

    public function redact(string $value): string
    {
        $values = array_values(array_filter(array_unique($this->values), static fn(string $item): bool => $item !== ''));
        usort($values, static fn(string $left, string $right): int => strlen($right) <=> strlen($left));

        return str_replace($values, '[REDACTED]', $value);
    }
}
