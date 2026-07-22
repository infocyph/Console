<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

final readonly class SubprocessResult
{
    public function __construct(public int $exitCode, public string $output, public string $errorOutput) {}
}
