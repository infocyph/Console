<?php

declare(strict_types=1);

namespace Infocyph\Console\Otp;

use Infocyph\OTP\TOTP;

final readonly class TotpVerifier implements OtpVerifier
{
    public function __construct(private TOTP $totp) {}

    public function verify(string $code): bool
    {
        return $this->totp->verify($code);
    }
}
