# Infocyph Console

A fast, typed foundation for building modern PHP command-line applications.

## Install

```bash
composer require infocyph/console
```

The core installs ArrayKit and InterMix. Capability adapters remain optional:
install the package used by the selected command, such as
`infocyph/reqshield` for validation or `infocyph/dblayer` for DBLayer
persistence. UID is part of the core command-execution lifecycle, but its
generator remains lazy until a command declares the identity capability.
Unselected adapters are not initialized or required by preflight and ordinary
command paths.

## Current status

Phases 1–9 and 11 of the architecture are implemented:

- Typed command kernel, native argv parsing, and preflight help/list/version.
- Lazy InterMix command resolution with constructor injection and scope-seeded
  command state.
- Capability-aware text, ANSI, and JSON frame rendering.
- Typed terminal components for tables, trees, boxes, lists, status, progress,
  spinners, and task groups.
- Interactive and queued-test prompts with safe non-interactive fallbacks.
- ReqShield semantic validation with one-pass sanitized typed input and JSON
  validation failures.
- ArrayKit-backed lazy configuration and directly includable compiled command
  manifests for production preflight.
- Opt-in infrastructure services built on CacheLayer, DBLayer, EpiCrypt, OTP,
  Pathwise, TalkingBytes, and UID.
- A public testing API with in-memory command execution, prompt simulation,
  output and validation assertions, frame snapshots, and subprocess fixtures.
- Cross-platform hardening for CI and redirected terminals, global option
  validation, JSON error paths, subprocess environments, signal fallbacks,
  and workspace boundary checks.
- Compiled command execution policies for zero-overhead inline commands and
  opt-in isolated commands with overlap locks, timeouts, idle timeouts, memory
  limits, renewable leases, and graceful termination.
- Scheduling with cron frequencies, callbacks, non-throwing skip outcomes,
  single-server/overlap leases, compiled manifests, and per-entry process
  limits.
- A queue-neutral dynamic worker supervisor with bounded incremental scaling,
  process and supervisor lifetimes, start limits, safe draining, opt-in
  signal-aware scale-down, and graceful/forced shutdown accounting.
- Compiled validation-manifest loading, shell completion generation, themed ANSI
  rendering, semantic components, prompt hints/filtering, fuzzy suggestions,
  verbosity-aware diagnostics, and CI performance guardrails.

## Production metadata and UX

Use directly includable manifests to keep validation and completion in the
preflight path:

```php
use Infocyph\Console\Discovery\CommandManifestCompiler;

$commands = [
    'user:create' => CreateUserCommand::class,
];

new CommandManifestCompiler()->write(
    $commands,
    __DIR__.'/cache/commands.php',
);

$application = Application::configure()
    ->commandManifest(__DIR__.'/cache/commands.php')
    ->validationManifest(__DIR__.'/cache/validation.php')
    ->completionManifest(__DIR__.'/cache/completion.php')
    ->build();
```

The compiler keeps `commands.php` as a small index and writes lazy descriptor
shards beside it as `commands-<hash>.php`. Command dispatch loads only the
selected descriptor; no secondary `commands.php.d` directory is created.

`completion bash`, `completion zsh`, and `completion fish` emit installable
shell definitions. Unexpected failures remain concise by default; `-v`, `-vv`,
and `-vvv` add exception location and trace detail. Run `composer benchmark --
--enforce` to apply the CI performance ceiling locally.

## Testing commands

```php
$this->command('user:create')
    ->argument('email', 'hasan@example.com')
    ->option('age', 30)
    ->answer('Continue?', true)
    ->run()
    ->assertSuccessful()
    ->assertOutputContains('Created user hasan@example.com.');
```

Use the fluent API directly from Pest tests:

```php
it('creates a user', function (): void {
    $application = Application::configure()
        ->commands([UserCreateCommand::class])
        ->build();

    $result = (new ApplicationTester($application))
        ->command('user:create')
        ->argument('email', 'hasan@example.com')
        ->run();

    $result->assertSuccessful();
});
```

`FakeTerminal`, `FakeClock`, `FakeKeyboard`, `FakeSignalManager`,
`FakeCapabilityLoader`, `FrameSnapshot`, and `SubprocessRunner` cover terminal,
lifecycle, and process-level scenarios. `CommandTestCase` remains available as
a compatibility helper for consumers that use class-based tests.

## Infrastructure capabilities

Infrastructure is activated only after Console resolves the selected command.
Preflight operations (`--version`, `list`, and help) never initialize it.

```php
use Infocyph\Console\Command\Capability;
use Infocyph\Console\Otp\TotpVerifier;
use Infocyph\InterMix\DI\Container;

$application = Application::configure()
    ->otpVerifier(new TotpVerifier($totp))
    ->configureCapability(Capability::NETWORK, function (Container $container) use ($client): void {
        $container->definitions()->bind(\Infocyph\Console\Communication\RemoteClient::class,
            new \Infocyph\Console\Communication\RemoteClient($client));
    })
    ->build();
```

Commands declare their requirements in `define()`:

```php
$command
    ->name('release:publish')
    ->capabilities([Capability::FILESYSTEM, Capability::NETWORK, Capability::IDENTITY])
    ->requiresOtp();
```

Reusable Console-specific services include `Workspace` for safe atomic files,
`CommandStateStore` and `CommandMutex`, `DBLayerCommandHistoryRepository`,
`SecretStore`, `SecureConfiguration`, `ArtifactVerifier`,
`ReleaseSignatureVerifier`, and `RemoteClient`. Database connections and HTTP
clients remain application-configured; Console never opens them automatically.

## Execution controls, schedules, and workers

Inline remains the default command mode. Controls that require enforcement
promote only the declaring command to a supervised child process:

```php
$command
    ->name('reports:build')
    ->withoutOverlap('reports', leaseSeconds: 120, waitSeconds: 5)
    ->timeout(90, terminationGraceSeconds: 5)
    ->idleTimeout(30)
    ->memoryLimit(256);
```

The execution policy is compiled into the command descriptor. Configure locks
with `ApplicationBuilder::lockProvider()` or
`lockProviderFactory()`. The factory stays lazy through help, list, completion,
version, and ordinary inline commands. CacheLayer file, Redis, Valkey,
Memcached, and PDO providers share the same lease contract.

Schedules accept argv as separate values, so arguments containing spaces do
not require shell parsing:

```php
$schedule
    ->command('reports:build')
    ->arguments(['--tenant=acme'])
    ->dailyAt('02:00')
    ->onOneServer(leaseSeconds: 180)
    ->withoutOverlap(leaseSeconds: 180)
    ->timeout(120)
    ->memoryLimit(256);
```

`ScheduleRunner` records an explicit skipped run when a lock is busy and
continues with other due entries. Its executor receives a `ScheduleLease`; pass
`heartbeat()` to the supervising process so expiring distributed locks are
renewed.

Applications using `DBLayerScheduleStateRepository` must provide its table,
including a `status` text column for the `completed` and `skipped` values.
Console records schedule state but never creates or migrates application tables.

Implement `WorkloadProbe::pending()` and run `WorkerSupervisor` with
`WorkerOptions`. Desired concurrency is
`ceil(pending / jobsPerProcess)`, bounded by the configured minimum, maximum,
and scale step. One-shot workers drain by default. Set
`scaleDownProcesses: true` only when child workers handle termination signals
and can stop without abandoning an active job.

## Framework integration

Frameworks can provide their existing InterMix container and configuration
repository without creating a second infrastructure graph. Providers remain
lazy: version, help, list, and completion paths do not request either provider.

```php
use Infocyph\Console\Application;

$application = Application::configure()
    ->containerProvider($frameworkContainerProvider)
    ->configurationProvider($frameworkConfigurationProvider)
    ->commands($commands)
    ->build();
```

`ContainerProvider::container()` is called only when a real command is
dispatched. Console applies its configured providers and bindings once to each
returned container, then creates a fresh command scope for every execution.
Standalone applications lazily create one container and reuse its immutable
wiring; command context, parsed input, IO, and identity remain isolated through
InterMix scope seeds and are removed when the command finishes.

`ConfigurationProvider` owns profile selection and returns a
`Configuration`. Use `Configuration::fromConfig()` to wrap an existing
ArrayKit configuration repository without copying or eagerly materializing it.
Local Console configuration layers, files, profiles, and validation cannot be
combined with an external provider, avoiding ambiguous precedence.
Configured validation manifests are not included for version, help, list, or
completion; they are loaded once when a real command first needs validation.
Command listings group commands by the first segment of their route name, so
`reports:daily` appears under `reports`. Commands without a namespace appear
under `Application`. Framework integrations may explicitly group owned commands
without changing their public route names:

```php
$application = Application::configure()
    ->commands($commands)
    ->commandGroup('System', 'config:cache', 'config:clear')
    ->build();
```

```php
use Infocyph\Console\Application;
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;

final class HelloCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('hello')->description('Say hello.');
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
