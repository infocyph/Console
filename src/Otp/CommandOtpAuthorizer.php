<?php

declare(strict_types=1);

namespace Infocyph\Console\Otp;

use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Exception\UsageException;
use Infocyph\Console\IO\IO;
use Infocyph\Console\Security\OtpChallenge;

final readonly class CommandOtpAuthorizer
{
    public function __construct(private ?OtpVerifier $verifier = null) {}

    public function authorize(CommandDescriptor $command, IO $io): void
    {
        if (!$command->requiresOtp()) {
            return;
        }

        if ($this->verifier === null) {
            throw new UsageException(sprintf('Command "%s" requires OTP verification, but no verifier is configured.', $command->name()));
        }

        $challenge = new OtpChallenge($command->name());
        $code = $io->prompts()->password($challenge->prompt);
        if (!$this->verifier->verify($code)) {
            throw new UsageException('The verification code is invalid.');
        }
    }
}
