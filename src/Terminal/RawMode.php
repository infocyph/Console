<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

/**
 * Best-effort raw-mode guard. Platforms without a safe native implementation
 * remain in line-input mode rather than attempting shell-specific commands.
 */
final class RawMode
{
    private bool $active = false;

    private ?string $state = null;

    public function __construct(private readonly mixed $input = STDIN) {}

    public function __destruct()
    {
        $this->restore();
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function enable(): bool
    {
        if ($this->active) {
            return true;
        }
        if (DIRECTORY_SEPARATOR === '\\' || !$this->isTty()) {
            return false;
        }

        $state = shell_exec('stty -g < /dev/tty 2>/dev/null');
        if (!is_string($state) || preg_match('/^[0-9A-Fa-f:;]+\s*$/', $state) !== 1) {
            return false;
        }
        $status = 1;
        exec('stty -icanon -echo min 1 time 0 < /dev/tty', $unused, $status);
        if ($status !== 0) {
            return false;
        }

        $this->state = trim($state);
        $this->active = true;
        register_shutdown_function($this->restore(...));

        return true;
    }

    public function restore(): void
    {
        if (!$this->active || $this->state === null) {
            return;
        }
        $state = $this->state;
        $this->active = false;
        $this->state = null;
        if (DIRECTORY_SEPARATOR !== '\\' && preg_match('/^[0-9A-Fa-f:;]+$/', $state) === 1) {
            exec('stty ' . $state . ' < /dev/tty', $unused, $status);
        }
    }

    private function isTty(): bool
    {
        if (!is_resource($this->input)) {
            return false;
        }
        if (function_exists('stream_isatty')) {
            return stream_isatty($this->input);
        }

        return function_exists('posix_isatty') && posix_isatty($this->input);
    }
}
