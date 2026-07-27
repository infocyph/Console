<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

use Infocyph\Console\Prompt\InputReader;

final readonly class Keyboard
{
    public function __construct(private InputReader $input, private RawMode $rawMode = new RawMode()) {}

    public function begin(): bool
    {
        return $this->rawMode->enable();
    }

    public function read(): ?string
    {
        $value = $this->rawMode->active() ? $this->input->readKey() : $this->input->read();
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw new \UnexpectedValueException('Keyboard input must be scalar or stringable.');
        }
        $value = (string) $value;
        if ($value !== "\033") {
            return match ($value) {
                "\r", "\n" => 'enter', default => $value,
            };
        }
        $next = $this->input->readKey();
        $final = $next === '[' ? $this->input->readKey() : null;

        return match ($final) {
            'A' => 'up', 'B' => 'down', 'C' => 'right', 'D' => 'left', default => "\033",
        };
    }

    public function restore(): void
    {
        $this->rawMode->restore();
    }
}
