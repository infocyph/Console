<?php

declare(strict_types=1);

namespace Infocyph\Console\Prompt;

use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\Line;

final class OptionViewport
{
    private int $offset = 0;

    private int $selected = 0;

    /** @var array<string,string> */
    private array $visible;

    /** @param array<string,string> $options */
    public function __construct(private readonly array $options, private readonly int $maximumVisible = 10)
    {
        if ($maximumVisible < 1) {
            throw new \InvalidArgumentException('Maximum visible options must be positive.');
        }
        $this->visible = $options;
    }

    public function filter(string $query): self
    {
        $query = strtolower($query);
        $this->visible = array_filter($this->options, static fn(string $value, string $key): bool => $query === '' || str_contains(strtolower($key . ' ' . $value), $query), ARRAY_FILTER_USE_BOTH);
        $this->selected = $this->offset = 0;

        return $this;
    }

    public function frame(): Frame
    {
        $lines = [];
        foreach ($this->visible() as $key => $label) {
            $lines[] = new Line(($key === $this->selected() ? '› ' : '  ') . $key . '  ' . $label, $key === $this->selected() ? 'selected' : 'text');
        }

        return new Frame($lines);
    }

    public function move(int $direction): self
    {
        $count = count($this->visible);
        if ($count === 0) {
            return $this;
        }
        $this->selected = max(0, min($count - 1, $this->selected + $direction));
        $this->offset = max(0, min($this->offset, max(0, $this->selected - $this->maximumVisible + 1)));
        if ($this->selected < $this->offset) {
            $this->offset = $this->selected;
        }

        return $this;
    }

    public function selected(): ?string
    {
        $keys = array_keys($this->visible);

        return $keys[$this->selected] ?? null;
    }

    /** @return array<string,string> */
    public function visible(): array
    {
        return array_slice($this->visible, $this->offset, $this->maximumVisible, true);
    }
}
