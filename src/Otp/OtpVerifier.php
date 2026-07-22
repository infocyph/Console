<?php

declare(strict_types=1);

namespace Infocyph\Console\Otp;

interface OtpVerifier
{
    public function verify(string $code): bool;
}
