<?php

declare(strict_types=1);

namespace Infocyph\Console\Filesystem;

use Infocyph\Pathwise\PathwiseFacade;

final readonly class Workspace
{
    private string $root;

    public function __construct(string $root)
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if ($root === '') {
            throw new \InvalidArgumentException('Workspace root cannot be empty.');
        }
        $this->root = $root;
    }

    public function atomicWrite(string $relativePath, string $contents): void
    {
        $path = $this->path($relativePath);
        PathwiseFacade::at(dirname($path))->directory()->create();
        if (!PathwiseFacade::at($path)->writer()->enableAtomicWrite()->writeAndVerify($contents)) {
            throw new \RuntimeException(sprintf('Could not verify atomic write to "%s".', $relativePath));
        }
    }

    public function checksum(string $relativePath, string $algorithm = 'sha256'): string
    {
        $checksum = hash_file($algorithm, $this->path($relativePath));
        if (!is_string($checksum)) {
            throw new \RuntimeException(sprintf('Could not calculate the %s checksum.', $algorithm));
        }

        return $checksum;
    }

    public function path(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $path = $this->root . '/' . $relativePath;
        $segments = explode('/', $relativePath);
        if ($relativePath === '' || in_array('..', $segments, true) || str_starts_with($relativePath, '../')) {
            throw new \InvalidArgumentException('Workspace paths must remain inside the configured root.');
        }

        $this->assertResolvedPathStaysInside($path);

        return $path;
    }

    public function read(string $relativePath): string
    {
        return PathwiseFacade::at($this->path($relativePath))->file()->read();
    }

    private function assertResolvedPathStaysInside(string $path): void
    {
        $resolvedRoot = realpath($this->root);
        $ancestor = $path;
        while (!file_exists($ancestor) && !is_link($ancestor)) {
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                break;
            }
            $ancestor = $parent;
        }
        $resolvedPath = realpath($ancestor);
        if ($resolvedRoot === false || $resolvedPath === false) {
            return;
        }

        $root = rtrim(str_replace('\\', '/', $resolvedRoot), '/') . '/';
        $candidate = rtrim(str_replace('\\', '/', $resolvedPath), '/') . '/';
        if (!str_starts_with($candidate, $root)) {
            throw new \InvalidArgumentException('Workspace paths must not escape through symbolic links.');
        }
    }
}
