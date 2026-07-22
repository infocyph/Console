<?php

declare(strict_types=1);

namespace Infocyph\Console\Security;

final readonly class OtpChallenge
{
    public function __construct(public string $command, public string $prompt = 'Verification code') {}
}
