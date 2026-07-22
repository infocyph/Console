<?php

declare(strict_types=1);

namespace Infocyph\Console\Testing;

use Infocyph\Console\Prompt\AnswerQueue;
use Infocyph\Console\Terminal\Keyboard;

final readonly class FakeKeyboard
{
    private Keyboard $keyboard;

    /** @param list<string|null> $keys */
    public function __construct(array $keys = [])
    {
        $this->keyboard = new Keyboard(new AnswerQueue($keys));
    }

    public function read(): ?string
    {
        return $this->keyboard->read();
    }
}
