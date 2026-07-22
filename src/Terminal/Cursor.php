<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

final readonly class Cursor
{
    /** @param resource $output */
    public function __construct(private mixed $output, private bool $enabled) {}

    public function clearLine(): void
    {
        $this->write("\033[2K\r");
    }

    public function down(int $lines = 1): void
    {
        $this->write("\033[{$lines}B\r");
    }

    public function hide(): void
    {
        $this->write("\033[?25l");
    }

    public function show(): void
    {
        $this->write("\033[?25h");
    }

    public function up(int $lines = 1): void
    {
        $this->write("\033[{$lines}A");
    }

    private function write(string $sequence): void
    {
        if ($this->enabled && is_resource($this->output)) {
            fwrite($this->output, $sequence);
        }
    }
}
