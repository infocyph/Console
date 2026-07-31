# Infocyph Console

A typed, framework-agnostic command runtime for PHP 8.4 and newer.

Console combines fast compiled command metadata, lazy InterMix resolution,
structured terminal presentation, prompts, process controls, scheduling,
dynamic worker supervision, and opt-in Omnibus message commands. Help, list,
version, and completion remain independent of application infrastructure.

## Installation

```bash
composer require infocyph/console
```

ArrayKit, InterMix, Omnibus, and UID are runtime dependencies. CacheLayer,
DBLayer, Epicrypt, OTP, Pathwise, ReqShield, and TalkingBytes integrations are
optional; applications install only the providers they use.

## Quick start

```php
use Infocyph\Console\Application;
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;

final class HelloCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command
            ->name('hello')
            ->description('Print a greeting.');
    }

    protected function handle(): int
    {
        $this->io()->success('Hello from Console.');

        return ExitCode::SUCCESS;
    }
}

$application = Application::configure()
    ->name('acme')
    ->version('1.0.0')
    ->commands([HelloCommand::class])
    ->build();

exit($application->run());
```

```console
php bin/acme list
php bin/acme help hello
php bin/acme hello
php bin/acme completion zsh
```

## Terminal experience

Semantic frames render through an adaptive default theme:

- 16-color, 256-color, and true-color ANSI palettes;
- reusable custom themes with foreground, background, bold, dim, italic, and
  underline styles;
- tables, trees, boxes, details, lists, progress, spinners, and task groups;
- plain output for redirection and `NO_COLOR`;
- newline-delimited JSON via `--format=json`.

```php
$io->title('Deployment');
$io->section('Preflight');
$io->details([
    'Release' => '2026.07.31',
    'Region' => 'ap-south-1',
]);
$io->success('Configuration is valid.');
```

## Process and worker safety

Inline execution stays on the short path. Commands opt into isolation only
when they declare overlap locks, timeouts, idle timeouts, or memory limits.

`WorkerSupervisor` provides bounded incremental scaling, restart backoff,
consecutive-failure circuit breaking, process and supervisor lifetimes, exact
child accounting, SIGINT/SIGTERM draining, heartbeat ownership, and explicit
stop reasons. Probe or callback failures drain active children before
propagating.

Omnibus owns queue delivery and message handling. Console contributes the
bounded `queue:consume` command, scheduled factory dispatch, queue-depth
workload probing, and child-process supervision without selecting a backend.

## Documentation

The complete RST/Sphinx manual starts at
[`docs/index.rst`](docs/index.rst). It includes:

- command definitions, validation, configuration, and compiled manifests;
- themes, terminal components, prompts, plain/ANSI/JSON behavior;
- process controls, schedules, leases, workers, and shutdown;
- standalone and framework integration;
- complete DBLayer/Redis Omnibus examples and operational recipes;
- testing, performance, deployment, and release checklists.

Read the Docs configuration is included in `.readthedocs.yaml`.

## Quality

```console
composer ic:process
composer ic:ci
composer ic:release:guard
composer benchmark
composer soak:worker
```

Console is released under the MIT license.
