<?php

declare(strict_types=1);

namespace Infocyph\Console\Security;

use Infocyph\Epicrypt\Integrity\StringHasher;

final readonly class ArtifactVerifier
{
    public function __construct(private StringHasher $hasher = new StringHasher()) {}

    public function verifyContents(string $contents, string $digest): bool
    {
        return $this->hasher->verify($contents, $digest);
    }
}
