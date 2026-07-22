<?php

declare(strict_types=1);

namespace Infocyph\Console\Security;

use Infocyph\Epicrypt\Crypto\Signature;

final readonly class ReleaseSignatureVerifier
{
    public function __construct(private Signature $signature = new Signature()) {}

    public function verify(string $metadata, string $signature, string $publicKey): bool
    {
        return $this->signature->verify($metadata, $signature, $publicKey);
    }
}
