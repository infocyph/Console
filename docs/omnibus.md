# Omnibus messages and queues

Console installs Omnibus as its message, event, and queue contract. Console
owns CLI metadata, schedules, child processes, scaling, and shutdown. Omnibus
owns envelopes, routing, transports, reservations, retries, failure storage,
workflows, and message execution.

## Registration and lifecycle

Register the integration explicitly:

```php
use Infocyph\Console\Application;
use Infocyph\InterMix\DI\Container;
use Infocyph\Omnibus\Consumer\Command\ConsumerTask;
use Infocyph\Omnibus\Scheduling\ScheduledMessageDispatcher;

$application = Application::configure()
    ->omnibus()
    ->configureContainer(
        static function (Container $container) use ($consumer, $scheduled): void {
            $container->definitions()->bind(ConsumerTask::class, $consumer);
            $container->definitions()->bind(
                ScheduledMessageDispatcher::class,
                $scheduled,
            );
        },
    )
    ->build();
```

The example uses already composed application services. Console does not
select a transport, create a database/cache connection, discover handlers, or
construct a serializer. A framework container may supply the same bindings.

Calling `omnibus()` registers static descriptors only. Version, list, help,
completion, and unrelated commands do not create the Console container or
resolve Omnibus services.

## Commands

| Command | Inputs | Behavior |
| --- | --- | --- |
| `queue:consume` | `--queue=default`, `--limit=1`, `--visibility=60.0` | Performs one bounded `ConsumerTask` call and reports received, succeeded, released, and failed counts |
| `schedule:dispatch-message` | required `factory` argument, for example `reports.daily` | Creates and dispatches one message through the registered Omnibus factory map |

Invalid queue names, limits, and visibility timeouts return Console's
invalid-usage exit code. A handled retry or terminal message failure remains an
Omnibus result rather than a Console process crash.

For production command caches, include both command classes in the compiled
manifest:

```php
use Infocyph\Console\Discovery\CommandManifestCompiler;
use Infocyph\Console\Omnibus\ConsumeCommand;
use Infocyph\Console\Omnibus\DispatchScheduledMessageCommand;

new CommandManifestCompiler()->write(
    [ConsumeCommand::class, DispatchScheduledMessageCommand::class],
    $cacheDirectory.'/commands.php',
);
```

## Dynamic workers

Use the selected receiver as Console's depth signal:

```php
use Infocyph\Console\Omnibus\ReceiverWorkloadProbe;
use Infocyph\Console\Worker\WorkerOptions;
use Infocyph\Console\Worker\WorkerSupervisor;

$summary = (new WorkerSupervisor())->run(
    [
        PHP_BINARY,
        'infbyte',
        'queue:consume',
        '--queue=emails',
        '--limit=10',
        '--visibility=90',
    ],
    new ReceiverWorkloadProbe($receiver, 'emails'),
    new WorkerOptions(
        maximumProcesses: 8,
        jobsPerProcess: 10,
        scaleStep: 2,
        stopWhenEmpty: true,
    ),
);
```

The probe delegates only `Receiver::size()`. Backend documentation determines
whether that depth is exact or approximate. Console clamps the value at the
supervisor boundary before calculating desired processes.

## Scheduled messages

`ScheduledMessages` converts a factory key into an ordinary Console schedule
entry:

```php
use Infocyph\Console\Omnibus\ScheduledMessages;

(new ScheduledMessages($schedule))
    ->message('reports.daily')
    ->hourly()
    ->onOneServer()
    ->withoutOverlap();
```

Compiled metadata stores the command name, factory key, cron expression,
timezone, and execution policies. It never stores closures or message objects.
At runtime `schedule:dispatch-message` resolves
`ScheduledMessageDispatcher`, invokes the named factory, and routes the result
through Omnibus.
