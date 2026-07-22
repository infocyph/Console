<?php

declare(strict_types=1);

namespace Infocyph\Console\Prompt;

interface InputReader
{
    public function read(bool $secret = false): mixed;

    public function readKey(): ?string;
}
