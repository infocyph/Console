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
use Infocyph\Console\Prompt\InputReader;
use Infocyph\Console\Prompt\PromptManager;
use Infocyph\Console\Prompt\StreamInputReader;
use Infocyph\Console\Render\AnsiRenderer;
use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\FrameDiffer;
use Infocyph\Console\Render\JsonRenderer;
use Infocyph\Console\Render\LiveRenderer;
use Infocyph\Console\Render\PlainRenderer;
use Infocyph\Console\Render\Renderer;
use Infocyph\Console\Style\Theme;
use Infocyph\Console\Terminal\CapabilityDetector;
use Infocyph\Console\Terminal\Terminal;
use Infocyph\Console\Terminal\TerminalCapabilities;

final class ConsoleIO implements IO
{
    private readonly PromptManager $prompts;

    private ?bool $ansiOverride = null;

    private string $format = 'text';

    private ?LiveRenderer $liveRenderer = null;

    private Renderer $renderer;

    private ?Theme $theme = null;

    private ?int $widthOverride = null;

    /** @param resource $output @param resource $errorOutput */
    public function __construct(
        private readonly mixed $output,
        private readonly mixed $errorOutput,
        private readonly TerminalCapabilities $capabilities,
        ?InputReader $input = null,
    ) {
        if (!is_resource($output) || !is_resource($errorOutput)) {
            throw new \InvalidArgumentException('Console output streams must be resources.');
        }
        $this->renderer = $this->renderer();
        $this->prompts = new PromptManager($input ?? new StreamInputReader(), function (string $line): void {
            $this->text($line);
        }, $capabilities->interactive);
    }

    public static function standard(): self
    {
        $capabilities = new CapabilityDetector()->detect();

        return new self(STDOUT, STDERR, $capabilities);
    }

    public function box(string $title, string|array $content): void
    {
        $this->frame(new Box($title, $content)->frame($this->width()));
    }

    public function definitions(array $items): void
    {
        $this->frame(new DefinitionList($items)->frame($this->width()));
    }

    public function details(array $items): void
    {
        $this->frame(new Details($items)->frame($this->width()));
    }

    public function error(string $message): void
    {
        $this->emit($message, 'error', true);
    }

    public function frame(Frame $frame): void
    {
        fwrite($this->output, $this->renderer->render($frame));
    }

    public function info(string $message): void
    {
        $this->emit($message, 'info');
    }

    public function listing(array $items, bool $ordered = false): void
    {
        $this->frame(new Listing($items, $ordered)->frame($this->width()));
    }

    public function live(Renderable $component): void
    {
        $this->liveRenderer()->render($component->frame($this->width()));
    }

    public function muted(string $message): void
    {
        $this->emit($message, 'muted');
    }

    public function note(string $message): void
    {
        $this->emit($message, 'note');
    }

    public function paragraph(string $text, string $role = 'text'): void
    {
        $this->frame(new Paragraph($text, $role)->frame($this->width()));
    }

    public function progress(int $total, string $label = ''): ProgressBar
    {
        return new ProgressBar($total, $label, function (ProgressBar $bar, bool $force): void {
            $this->liveRenderer()->render($bar->frame($this->width()), force: $force);
        });
    }

    public function prompts(): PromptManager
    {
        return $this->prompts;
    }

    public function rule(string $character = '─'): void
    {
        $this->frame(new HorizontalRule($character)->frame($this->width()));
    }

    public function section(string $title): void
    {
        $this->frame(new Section($title)->frame($this->width()));
    }

    public function setAnsi(?bool $ansi): void
    {
        $this->ansiOverride = $ansi;
        $this->renderer = $this->renderer();
        $this->liveRenderer = null;
    }

    public function setFormat(string $format): void
    {
        if (!in_array($format, ['text', 'json'], true)) {
            throw new \InvalidArgumentException('Output format must be text or json.');
        } $this->format = $format;
        $this->renderer = $this->renderer();
        $this->liveRenderer = null;
    }

    public function setInteractive(bool $interactive): void
    {
        $this->prompts->interactive($interactive);
    }

    public function setTheme(?Theme $theme): void
    {
        $this->theme = $theme;
        $this->renderer = $this->renderer();
        $this->liveRenderer = null;
    }

    public function setWidth(?int $width): void
    {
        if ($width !== null && $width < 20) {
            throw new \InvalidArgumentException('Terminal width must be at least 20 columns.');
        } $this->widthOverride = $width;
    }

    public function spinner(string $label = ''): Spinner
    {
        return new Spinner($label, function (Spinner $spinner): void {
            $this->live($spinner);
        });
    }

    public function status(string $text, string $role = 'info'): void
    {
        $this->frame(new Status($text, $role)->frame($this->width()));
    }

    public function success(string $message): void
    {
        $this->emit($message, 'success');
    }

    public function table(array $headers, array $rows): void
    {
        $this->frame(new Table($headers, $rows)->frame($this->width()));
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
        $this->emit($message, 'text');
    }

    public function title(string $title): void
    {
        $this->frame(new Title($title)->frame($this->width()));
    }

    public function tree(array $items): void
    {
        $this->frame(new Tree($items)->frame($this->width()));
    }

    public function validationFailures(array $failures): void
    {
        if ($this->format === 'json') {
            fwrite($this->errorOutput, json_encode(['error' => 'invalid_input', 'exit_code' => 2, 'failures' => $failures], JSON_THROW_ON_ERROR) . PHP_EOL);

            return;
        }
        $this->error('Invalid command input');
        foreach ($failures as $failure) {
            $this->error(sprintf('%s: %s', $failure['field'], $failure['message']));
        }
    }

    public function warning(string $message): void
    {
        $this->emit($message, 'warning', true);
    }

    private function emit(string $message, string $role, bool $error = false): void
    {
        $contents = $this->renderer->render(Frame::line($message, $role));
        fwrite($error ? $this->errorOutput : $this->output, $contents);
    }

    private function liveRenderer(): LiveRenderer
    {
        return $this->liveRenderer ??= new LiveRenderer(new Terminal($this->output, $this->errorOutput, $this->capabilities), $this->renderer, new FrameDiffer());
    }

    private function renderer(): Renderer
    {
        if ($this->format === 'json') {
            return new JsonRenderer();
        }

        return ($this->ansiOverride ?? $this->capabilities->ansi) ? new AnsiRenderer($this->theme) : new PlainRenderer();
    }

    private function width(): int
    {
        return $this->widthOverride ?? $this->capabilities->width;
    }
}
