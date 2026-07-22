<?php

declare(strict_types=1);

namespace Infocyph\Console\Terminal;

final class CapabilityDetector
{
    /** @param resource $input @param resource $output @param array<string, string|false>|null $environment */
    public function detect(mixed $input = STDIN, mixed $output = STDOUT, ?array $environment = null): TerminalCapabilities
    {
        $environment ??= [];
        $inputTty = $this->isTty($input);
        $outputTty = $this->isTty($output);
        $term = strtolower($this->env('TERM', $environment));
        $ci = $this->env('CI', $environment) !== '';
        $noColor = $this->env('NO_COLOR', $environment) !== '';
        $forceColor = !in_array(strtolower($this->env('FORCE_COLOR', $environment)), ['', '0', 'false'], true);
        $windowsTerminal = $this->env('WT_SESSION', $environment) !== '' || $this->env('ANSICON', $environment) !== '' || strtoupper($this->env('ConEmuANSI', $environment)) === 'ON';
        $ansi = $outputTty && !$noColor && $term !== 'dumb' && (!$ci || $windowsTerminal || $forceColor);
        $colorTerm = strtolower($this->env('COLORTERM', $environment));
        $depth = !$ansi ? ColorDepth::NONE : match (true) {
            str_contains($colorTerm, 'truecolor'), str_contains($colorTerm, '24bit') => ColorDepth::TRUE_COLOR,
            str_contains($term, '256color') => ColorDepth::ANSI_256,
            default => ColorDepth::BASIC,
        };

        return new TerminalCapabilities(
            interactive: $inputTty && $outputTty,
            ansi: $ansi,
            unicode: $this->unicodeSupported($environment),
            colorDepth: $depth,
            width: $this->dimension('COLUMNS', 80, $environment),
            height: $this->dimension('LINES', 24, $environment),
        );
    }

    /** @param array<string, string|false> $environment */
    private function dimension(string $name, int $fallback, array $environment): int
    {
        $value = $this->env($name, $environment);

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : $fallback;
    }

    /** @param array<string, string|false> $environment */
    private function env(string $name, array $environment): string
    {
        $value = $environment[$name] ?? getenv($name);

        return is_string($value) ? $value : '';
    }

    /** @param resource $stream */
    private function isTty(mixed $stream): bool
    {
        if (!is_resource($stream)) {
            return false;
        }
        if (function_exists('stream_isatty')) {
            return stream_isatty($stream);
        }

        return function_exists('posix_isatty') && posix_isatty($stream);
    }

    /** @param array<string, string|false> $environment */
    private function unicodeSupported(array $environment): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return $this->env('WT_SESSION', $environment) !== '' || strtoupper($this->env('ConEmuANSI', $environment)) === 'ON';
        }

        return !in_array(strtolower($this->env('LANG', $environment)), ['', 'c', 'posix'], true);
    }
}
