<?php

declare(strict_types=1);

namespace Infocyph\Console\Component;

use Infocyph\Console\Render\Frame;

final readonly class TaskGroup implements Renderable
{
    /** @param list<Task> $tasks */
    public function __construct(private array $tasks) {}

    public function frame(int $width = 80): Frame
    {
        if ($this->tasks === []) {
            return new Frame([]);
        }

        return new Frame(array_merge(...array_map(static fn(Task $task): array => $task->frame($width)->lines, $this->tasks)));
    }
}
