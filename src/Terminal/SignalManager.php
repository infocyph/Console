<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

final class SignalManager
{
    /** @var list<callable> */
    private array $callbacks = [];

    public function dispatchInterrupt(): void
    {
        foreach ($this->callbacks as $callback) {
            $callback();
        }
    }

    public function onInterrupt(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function register(): bool
    {
        if (!function_exists('pcntl_signal') || !defined('SIGINT')) {
            return false;
        }
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
        }
        pcntl_signal(SIGINT, $this->dispatchInterrupt(...));
        if (defined('SIGTERM')) {
            pcntl_signal(SIGTERM, $this->dispatchInterrupt(...));
        }

        return true;
    }
}
