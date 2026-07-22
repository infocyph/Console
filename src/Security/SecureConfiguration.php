<?php

declare(strict_types=1);

namespace Infocyph\Console\Security;

use Infocyph\Epicrypt\DataProtection\StringProtector;

final readonly class SecureConfiguration
{
    public function __construct(private string $key, private StringProtector $protector = new StringProtector()) {}

    public function decrypt(string $value): string
    {
        return $this->protector->decrypt($value, $this->key, ['purpose' => 'console-configuration']);
    }

    public function encrypt(string $value): string
    {
        return $this->protector->encrypt($value, $this->key, ['purpose' => 'console-configuration']);
    }
}
