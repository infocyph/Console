<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

final class Screen
{
    private bool $restored = true;

    public function __construct(private readonly Cursor $cursor) {}

    public function beginLiveRegion(): void
    {
        $this->restored = false;
        $this->cursor->hide();
    }

    public function restore(): void
    {
        if (!$this->restored) {
            $this->cursor->show();
            $this->restored = true;
        }
    }
}
