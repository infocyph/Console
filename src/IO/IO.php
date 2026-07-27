<?php

declare(strict_types=1);

namespace Infocyph\Console\IO;

use Infocyph\Console\Component\ProgressBar;
use Infocyph\Console\Component\Renderable;
use Infocyph\Console\Component\Spinner;
use Infocyph\Console\Component\Task;
use Infocyph\Console\Component\TaskGroup;
use Infocyph\Console\Prompt\PromptManager;
use Infocyph\Console\Render\Frame;
use Infocyph\Console\Style\Theme;

interface IO
{
    /** @param string|array<string, scalar|null> $content */
    public function box(string $title, string|array $content): void;

    /** @param array<string, scalar|null> $items */
    public function definitions(array $items): void;

    /** @param array<string, scalar|null> $items */
    public function details(array $items): void;

    public function error(string $message): void;

    public function frame(Frame $frame): void;

    public function info(string $message): void;

    /** @param list<string> $items */
    public function listing(array $items, bool $ordered = false): void;

    public function live(Renderable $component): void;

    public function muted(string $message): void;

    public function note(string $message): void;

    public function paragraph(string $text, string $role = 'text'): void;

    public function progress(int $total, string $label = ''): ProgressBar;

    public function prompts(): PromptManager;

    public function rule(string $character = '─'): void;

    public function section(string $title): void;

    public function setAnsi(?bool $ansi): void;

    public function setFormat(string $format): void;

    public function setInteractive(bool $interactive): void;

    public function setTheme(?Theme $theme): void;

    public function setWidth(?int $width): void;

    public function spinner(string $label = ''): Spinner;

    public function status(string $text, string $role = 'info'): void;

    public function success(string $message): void;

    /**
     * @param list<string> $headers
     * @param list<array<array-key, scalar|null>> $rows
     */
    public function table(array $headers, array $rows): void;

    public function task(string $label, string $status = 'pending'): Task;

    /** @param list<Task> $tasks */
    public function taskGroup(array $tasks): TaskGroup;

    public function text(string $message): void;

    public function title(string $title): void;

    /** @param array<string, mixed> $items */
    public function tree(array $items): void;

    /** @param list<array{field:string,rule:string,message:string}> $failures */
    public function validationFailures(array $failures): void;

    public function warning(string $message): void;
}
