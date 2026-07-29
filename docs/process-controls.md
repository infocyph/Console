# Process controls

Inline execution is the default and has no child-process overhead. A command is
promoted to isolated execution only when its definition requests isolation or
a control that requires process supervision.

```php
$command
    ->name('reports:build')
    ->withoutOverlap('reports', leaseSeconds: 120, waitSeconds: 5)
    ->timeout(90, terminationGraceSeconds: 5)
    ->idleTimeout(30)
    ->memoryLimit(256);
```

The policy is compiled into the command descriptor.

## Command controls

| Control | Meaning |
| --- | --- |
| `isolated()` | Always execute through the configured Console executable |
| `withoutOverlap()` | Acquire a CacheLayer lease before starting |
| `timeout()` | Terminate after total elapsed time |
| `idleTimeout()` | Terminate after no stdout/stderr activity |
| `memoryLimit()` | Start the child with a PHP memory limit in MiB |

`withoutOverlap()` skips immediately when `waitSeconds` is `0`; a positive
value waits up to that bound. The lease must outlive the expected unit of work
and be renewable. Configure `lockProviderFactory()` when provider construction
is expensive so inline commands and preflight routes pay no lock cost.

## Direct process execution

`ProcessRunner` accepts argv values, never a shell command string:

```php
use Infocyph\Console\Process\ProcessMode;
use Infocyph\Console\Process\ProcessOptions;
use Infocyph\Console\Process\ProcessRunner;

$result = (new ProcessRunner)->run(
    [PHP_BINARY, 'tool.php', '--tenant=acme'],
    new ProcessOptions(
        workingDirectory: __DIR__,
        environment: ['APP_ENV' => 'production'],
        timeoutSeconds: 60,
        idleTimeoutSeconds: 15,
        sensitiveValues: [$token],
        mode: ProcessMode::CAPTURE,
        terminationGraceSeconds: 5,
    ),
);
```

`ProcessOptions` defaults are:

| Option | Type | Default | Valid/example value |
| --- | --- | --- | --- |
| `workingDirectory` | `?string` | `null` | `/srv/app` |
| `environment` | `array<string,string>` | `[]` | `['APP_ENV' => 'production']` |
| `timeoutSeconds` | `?float` | `null` | Positive seconds, for example `60.0` |
| `idleTimeoutSeconds` | `?float` | `null` | Positive seconds, for example `15.0` |
| `sensitiveValues` | `list<string>` | `[]` | Tokens/passwords to redact |
| `passthrough` | `bool` | `false` | `true` streams to the parent terminal |
| `onOutput` | `?callable` | `null` | `callable(string): void` |
| `onErrorOutput` | `?callable` | `null` | `callable(string): void` |
| `cancelled` | `?callable` | `null` | `callable(): bool` |
| `heartbeat` | `?callable` | `null` | `callable(): bool` |
| `input` | resource/string/null | `null` | Open stream or bounded string |
| `inheritInput` | `bool` | `false` | `true` for an interactive child |
| `mode` | `ProcessMode` | `CAPTURE` | `CAPTURE`, `STREAM`, or `INHERIT` |
| `terminationGraceSeconds` | `float` | `5.0` | Non-negative seconds |

On cancellation, timeout, interrupt, or heartbeat failure, Console sends a
graceful termination signal, waits for the configured grace period, then
escalates. A heartbeat returning `false` is ownership loss, not a warning; the
child must stop.
