<?php

declare(strict_types=1);

namespace Infocyph\Console\IO;

use Infocyph\Console\Component\Box;
use Infocyph\Console\Component\DefinitionList;
use Infocyph\Console\Component\Details;
use Infocyph\Console\Component\HorizontalRule;
use Infocyph\Console\Component\Listing;
use Infocyph\Console\Component\Paragraph;
use Infocyph\Console\Component\ProgressBar;
use Infocyph\Console\Component\Renderable;
use Infocyph\Console\Component\Section;
use Infocyph\Console\Component\Spinner;
use Infocyph\Console\Component\Status;
use Infocyph\Console\Component\Table;
use Infocyph\Console\Component\Task;
use Infocyph\Console\Component\TaskGroup;
use Infocyph\Console\Component\Title;
use Infocyph\Console\Component\Tree;
use Infocyph\Console\Prompt\AnswerQueue;
use Infocyph\Console\Prompt\PromptManager;
use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\PlainRenderer;
use Infocyph\Console\Style\Theme;

final class BufferedIO implements IO
{
    private readonly PromptManager $prompts;

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $output = [];

    /** @param list<mixed> $answers */
    public function __construct(array $answers = [])
    {
        $this->prompts = new PromptManager(new AnswerQueue($answers), function (string $line): void {
            $this->text($line);
        });
    }

    public function box(string $title, string|array $content): void
    {
        $this->frame(new Box($title, $content)->frame());
    }

    public function definitions(array $items): void
    {
        $this->frame(new DefinitionList($items)->frame());
    }

    public function details(array $items): void
    {
        $this->frame(new Details($items)->frame());
    }

    public function error(string $message): void
    {
        $this->errors[] = '[ERROR] ' . $message;
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function errorText(): string
    {
        return implode(PHP_EOL, $this->errors);
    }

    public function frame(Frame $frame): void
    {
        $rendered = rtrim(new PlainRenderer()->render($frame), "\r\n");
        if ($rendered === '') {
            return;
        }
        foreach (explode(PHP_EOL, $rendered) as $line) {
            $this->output[] = $line;
        }
    }

    public function info(string $message): void
    {
        $this->output[] = '[INFO] ' . $message;
    }

    public function listing(array $items, bool $ordered = false): void
    {
        $this->frame(new Listing($items, $ordered)->frame());
    }

    public function live(Renderable $component): void
    {
        $this->frame($component->frame());
    }

    public function muted(string $message): void
    {
        $this->output[] = $message;
    }

    public function note(string $message): void
    {
        $this->output[] = '[NOTE] ' . $message;
    }

    /** @return list<string> */
    public function output(): array
    {
        return $this->output;
    }

    public function outputText(): string
    {
        return implode(PHP_EOL, $this->output);
    }

    public function paragraph(string $text, string $role = 'text'): void
    {
        $this->frame(new Paragraph($text, $role)->frame());
    }

    public function progress(int $total, string $label = ''): ProgressBar
    {
        return new ProgressBar($total, $label, function (ProgressBar $bar, bool $force): void {
            if ($force) {
                $this->live($bar);

                return;
            }
            $this->live($bar);
        });
    }

    public function prompts(): PromptManager
    {
        return $this->prompts;
    }

    public function rule(string $character = '─'): void
    {
        $this->frame(new HorizontalRule($character)->frame());
    }

    public function section(string $title): void
    {
        $this->frame(new Section($title)->frame());
    }

    public function setAnsi(?bool $ansi): void {}

    public function setFormat(string $format): void {}

    public function setInteractive(bool $interactive): void
    {
        $this->prompts->interactive($interactive);
    }

    public function setTheme(?Theme $theme): void {}

    public function setWidth(?int $width): void {}

    public function spinner(string $label = ''): Spinner
    {
        return new Spinner($label, function (Spinner $spinner): void {
            $this->live($spinner);
        });
    }

    public function status(string $text, string $role = 'info'): void
    {
        $this->frame(new Status($text, $role)->frame());
    }

    public function success(string $message): void
    {
        $this->output[] = '[OK] ' . $message;
    }

    /**
     * @param list<string> $headers
     * @param list<array<array-key, scalar|null>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $this->frame(new Table($headers, $rows)->frame());
    }

    public function task(string $label, string $status = 'pending'): Task
    {
        return new Task($label, $status);
    }

    public function taskGroup(array $tasks): TaskGroup
    {
        return new TaskGroup($tasks);
    }

    public function text(string $message): void
    {
        $this->output[] = $message;
    }

    public function title(string $title): void
    {
        $this->frame(new Title($title)->frame());
    }

    public function tree(array $items): void
    {
        $this->frame(new Tree($items)->frame());
    }

    public function validationFailures(array $failures): void
    {
        $this->error('Invalid command input');
        foreach ($failures as $failure) {
            $this->error(sprintf('%s: %s', $failure['field'], $failure['message']));
        }
    }

    public function warning(string $message): void
    {
        $this->errors[] = '[WARNING] ' . $message;
    }
}
