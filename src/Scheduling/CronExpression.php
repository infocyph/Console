<?php

declare(strict_types=1);

namespace Infocyph\Console\Scheduling;

final readonly class CronExpression
{
    /** @var list<string> */
    private array $parts;

    public function __construct(string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (!is_array($parts) || count($parts) !== 5) {
            throw new \InvalidArgumentException('Cron expressions must contain five fields.');
        }
        $this->parts = $parts;
        foreach ($this->parts as $index => $part) {
            $this->validate($part, $this->range($index));
        }
    }

    public function expression(): string
    {
        return implode(' ', $this->parts);
    }

    public function matches(\DateTimeInterface $dateTime): bool
    {
        $values = [(int) $dateTime->format('i'), (int) $dateTime->format('G'), (int) $dateTime->format('j'), (int) $dateTime->format('n'), (int) $dateTime->format('w')];
        foreach ([0, 1, 3] as $index) {
            if (!$this->matchesPart($this->parts[$index], $values[$index], $this->range($index))) {
                return false;
            }
        }
        $dayOfMonth = $this->matchesPart($this->parts[2], $values[2], $this->range(2));
        $dayOfWeek = $this->matchesPart($this->parts[4], $values[4], $this->range(4));
        if ($this->parts[2] === '*' || $this->parts[4] === '*') {
            return $dayOfMonth && $dayOfWeek;
        }
        if (!($dayOfMonth || $dayOfWeek)) {
            return false;
        }

        return true;
    }

    /** @param array{int,int} $range */
    private function matchesPart(string $part, int $value, array $range): bool
    {
        foreach (explode(',', $part) as $segment) {
            [$base, $step] = array_pad(explode('/', $segment, 2), 2, null);
            $step = $step === null ? 1 : (int) $step;
            if ($step < 1) {
                continue;
            }
            if ($base === '*') {
                if (($value - $range[0]) % $step === 0) {
                    return true;
                }

                continue;
            }
            [$start, $end] = str_contains((string) $base, '-') ? array_map(intval(...), explode('-', (string) $base, 2)) : [(int) $base, (int) $base];
            foreach ($range[1] === 7 && $value === 0 ? [0, 7] : [$value] as $candidate) {
                if ($candidate >= $start && $candidate <= $end && ($candidate - $start) % $step === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{int,int} */
    private function range(int $index): array
    {
        return match ($index) {
            0 => [0, 59], 1 => [0, 23], 2 => [1, 31], 3 => [1, 12], 4 => [0, 7], default => throw new \InvalidArgumentException('Cron field index must be between zero and four.'),
        };
    }

    /** @param array{int,int} $range */
    private function validate(string $part, array $range): void
    {
        foreach (explode(',', $part) as $segment) {
            if (!preg_match('/^(\*|\d+(?:-\d+)?)(?:\/\d+)?$/', $segment)) {
                throw new \InvalidArgumentException(sprintf('Invalid cron segment "%s".', $segment));
            }
            [$base, $step] = array_pad(explode('/', $segment, 2), 2, null);
            if ($step !== null && (int) $step < 1) {
                throw new \InvalidArgumentException('Cron steps must be positive.');
            }
            if (str_contains((string) $base, '-')) {
                [$start, $end] = array_map(intval(...), explode('-', (string) $base, 2));
                if ($start > $end) {
                    throw new \InvalidArgumentException('Cron ranges must be ascending.');
                }
            }
            foreach (preg_split('/[-\/]/', $segment) ?: [] as $value) {
                if ($value !== '*' && ((int) $value < $range[0] || (int) $value > $range[1])) {
                    throw new \InvalidArgumentException(sprintf('Cron value "%s" is out of range.', $value));
                }
            }
        }
    }
}
