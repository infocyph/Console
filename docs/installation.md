# Installation

## Requirements

- PHP `8.4` or later within the supported `^8.4` line.
- Composer 2.
- `proc_open` for isolated commands and worker supervision.
- `pcntl` is recommended on Unix-like worker hosts for application-defined
  signal handling. Console can send termination signals without it, but child
  PHP workers need `pcntl` to implement graceful signal callbacks.

Install the core:

```bash
composer require infocyph/console
```

The runtime requires ArrayKit, InterMix, Omnibus, and UID. Omnibus defines the
message-consumer and scheduled-message boundaries that Console can supervise.
Its commands remain disabled until the application calls `omnibus()`, and its
transports are never created automatically. UID is mandatory because command
and schedule executions need stable identifiers, but identifier generation
remains lazy until an execution path needs it.

## Optional capabilities

Install only packages used by selected commands or framework adapters:

| Package | Install when |
| --- | --- |
| `infocyph/cachelayer` | Commands or schedules use overlap/single-server leases |
| `infocyph/dblayer` | Validation, command history, or schedule state uses DBLayer |
| `infocyph/epicrypt` | Commands verify artifacts/signatures or protect local secrets |
| `infocyph/otp` | Commands require TOTP authorization |
| `infocyph/pathwise` | Commands use the bounded workspace/file security adapters |
| `infocyph/reqshield` | Command or configuration validation is enabled |
| `infocyph/talkingbytes` | A selected command uses the remote communication adapter |

For example:

```bash
composer require infocyph/console infocyph/cachelayer
```

Installing an optional package does not activate it. The application must bind
its adapter and the selected command must declare the corresponding capability.

## Minimal application

```php
<?php

declare(strict_types=1);

use Infocyph\Console\Application;
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;

final class HelloCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('hello')->description('Print a greeting.');
    }

    protected function handle(): int
    {
        $this->io()->success('Hello!');

        return ExitCode::SUCCESS;
    }
}

$application = Application::configure()
    ->name('example')
    ->version('1.0.0')
    ->commands([HelloCommand::class])
    ->build();

exit($application->run());
```

Run it through the executable that requires this bootstrap:

```bash
php bin/console hello
```

For production, compile command metadata and call `production()` with the
resulting manifest. Production mode deliberately refuses runtime-only command
registration without a compiled command manifest.
