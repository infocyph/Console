<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

final readonly class Terminal
{
    /** @param resource $output @param resource $error */
    public function __construct(
        private mixed $output,
        private mixed $error,
        private TerminalCapabilities $capabilities,
    ) {}

    public static function standard(?TerminalCapabilities $capabilities = null): self
    {
        $capabilities ??= new CapabilityDetector()->detect();

        return new self(STDOUT, STDERR, $capabilities);
    }

    public function capabilities(): TerminalCapabilities
    {
        return $this->capabilities;
    }

    public function cursor(): Cursor
    {
        return new Cursor($this->output, $this->capabilities->ansi);
    }

    public function error(string $contents): void
    {
        fwrite($this->error, $contents);
    }

    public function screen(): Screen
    {
        return new Screen($this->cursor());
    }

    public function write(string $contents): void
    {
        fwrite($this->output, $contents);
    }
}
