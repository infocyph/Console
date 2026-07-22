<?php

declare(strict_types=1);

namespace Infocyph\Console\Prompt;

use Infocyph\Console\Exception\UsageException;
use Infocyph\Console\Terminal\RawMode;

final readonly class StreamInputReader implements InputReader
{
    /** @param resource $input */
    public function __construct(private mixed $input = STDIN) {}

    public function read(bool $secret = false): mixed
    {
        if ($secret) {
            return $this->secret();
        }
        $value = fgets($this->input);

        return $value === false ? null : rtrim($value, "\r\n");
    }

    public function readKey(): ?string
    {
        $value = fread($this->input, 1);

        return $value === false || $value === '' ? null : $value;
    }

    private function secret(): string
    {
        $raw = new RawMode($this->input);
        if (!$raw->enable()) {
            throw new UsageException('Secure password input requires an interactive POSIX terminal.');
        }

        try {
            $value = '';
            while (($key = $this->readKey()) !== null) {
                if ($key === "\r" || $key === "\n") {
                    break;
                }
                if ($key === "\177" || $key === "\010") {
                    $value = substr($value, 0, -1);

                    continue;
                }
                $value .= $key;
            }
            fwrite(STDOUT, PHP_EOL);

            return $value;
        } finally {
            $raw->restore();
        }
    }
}
