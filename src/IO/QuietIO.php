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

/** @internal */
final readonly class QuietIO implements IO
{
    public function __construct(private IO $inner) {}

    public function box(string $title, string|array $content): void {}

    public function definitions(array $items): void {}

    public function details(array $items): void {}

    public function error(string $message): void
    {
        $this->inner->error($message);
    }

    public function frame(Frame $frame): void {}

    public function info(string $message): void {}

    public function listing(array $items, bool $ordered = false): void {}

    public function live(Renderable $component): void {}

    public function muted(string $message): void {}

    public function note(string $message): void {}

    public function paragraph(string $text, string $role = 'text'): void {}

    public function progress(int $total, string $label = ''): ProgressBar
    {
        return new ProgressBar($total, $label);
    }

    public function prompts(): PromptManager
    {
        return $this->inner->prompts();
    }

    public function rule(string $character = '─'): void {}

    public function section(string $title): void {}

    public function setAnsi(?bool $ansi): void
    {
        $this->inner->setAnsi($ansi);
    }

    public function setFormat(string $format): void
    {
        $this->inner->setFormat($format);
    }

    public function setInteractive(bool $interactive): void
    {
        $this->inner->setInteractive($interactive);
    }

    public function setTheme(?Theme $theme): void
    {
        $this->inner->setTheme($theme);
    }

    public function setWidth(?int $width): void
    {
        $this->inner->setWidth($width);
    }

    public function spinner(string $label = ''): Spinner
    {
        return new Spinner($label);
    }

    public function status(string $text, string $role = 'info'): void {}

    public function success(string $message): void {}

    public function table(array $headers, array $rows): void {}

    public function task(string $label, string $status = 'pending'): Task
    {
        return $this->inner->task($label, $status);
    }

    public function taskGroup(array $tasks): TaskGroup
    {
        return $this->inner->taskGroup($tasks);
    }

    public function text(string $message): void {}

    public function title(string $title): void {}

    public function tree(array $items): void {}

    public function validationFailures(array $failures): void
    {
        $this->inner->validationFailures($failures);
    }

    public function warning(string $message): void
    {
        $this->inner->warning($message);
    }
}
