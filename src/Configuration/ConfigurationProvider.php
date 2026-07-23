<?php

declare(strict_types=1);

namespace Infocyph\Console\Configuration;

interface ConfigurationProvider
{
    public function configuration(): Configuration;

    public function useProfile(?string $profile): void;
}
