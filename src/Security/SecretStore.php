<?php

declare(strict_types=1);

namespace Infocyph\Console\Security;

use Infocyph\Console\Filesystem\Workspace;
use Infocyph\Epicrypt\DataProtection\StringProtector;
use Infocyph\Pathwise\PathwiseFacade;

final readonly class SecretStore
{
    public function __construct(private Workspace $workspace, private string $key, private StringProtector $protector = new StringProtector()) {}

    public function forget(string $name): void
    {
        $path = $this->workspace->path($this->file($name));
        if (is_file($path)) {
            PathwiseFacade::at($path)->file()->delete();
        }
    }

    public function get(string $name): string
    {
        return $this->protector->decrypt($this->workspace->read($this->file($name)), $this->key, ['purpose' => 'console-secret']);
    }

    public function put(string $name, string $value): void
    {
        $this->workspace->atomicWrite($this->file($name), $this->protector->encrypt($value, $this->key, ['purpose' => 'console-secret']));
    }

    private function file(string $name): string
    {
        if ($name === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('Secret names may contain only letters, numbers, dots, underscores, and dashes.');
        }

        return '.console/secrets/' . $name . '.secret';
    }
}
