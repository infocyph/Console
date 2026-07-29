# Scheduling and leases

Console owns schedule definitions and execution coordination. Applications own
the command routes, clock invocation, persistence schema, and the process that
calls `ScheduleRunner`.

```php
use Infocyph\Console\Scheduling\Schedule;

$schedule = new Schedule;
$schedule
    ->command('reports:build')
    ->arguments(['--tenant=acme'])
    ->dailyAt('02:00')
    ->timezone('UTC')
    ->onOneServer(leaseSeconds: 180)
    ->withoutOverlap(leaseSeconds: 180)
    ->timeout(120, terminationGraceSeconds: 5)
    ->idleTimeout(30)
    ->memoryLimit(256)
    ->onSuccess(static function ($run): void {
        // Record or report success.
    })
    ->onFailure(static function ($run): void {
        // Record or report failure.
    });
```

Available frequency helpers are `everyMinute()`, `hourly()`, `dailyAt()`, and
an explicit five-field `cron()` expression.

Omnibus messages use the same schedule policy through an explicit factory key:

```php
use Infocyph\Console\Omnibus\ScheduledMessages;

(new ScheduledMessages($schedule))
    ->message('reports.daily')
    ->dailyAt('02:00')
    ->timezone('UTC')
    ->onOneServer(leaseSeconds: 180)
    ->withoutOverlap(leaseSeconds: 180);
```

This compiles to `schedule:dispatch-message reports.daily`. At execution time,
the command asks Omnibus's `ScheduledMessageDispatcher` to create and dispatch
the message. The schedule manifest never contains the factory closure or a
constructed message.

## Lease behavior

`withoutOverlap()` prevents two copies of the same schedule entry from running
at once. `onOneServer()` makes one node in a deployment eligible. Both use the
configured `CommandMutex` and CacheLayer lease contract.

When a lease cannot be acquired, the runner records a `skipped` result and
continues other due entries. A skipped run is intentionally not successful.

The executor receives an optional `ScheduleLease`:

```php
$runs = $runner->runDue(
    $schedule,
    static function (string $name, $entry, $lease): int {
        while (hasMoreWork()) {
            if ($lease !== null && !$lease->heartbeat()) {
                return 1;
            }

            processNextChunk();
        }

        return 0;
    },
);
```

Call `heartbeat()` between bounded work chunks. A `false` result means lease
ownership was lost; stop before performing more protected work.

## Persistence and manifests

Framework optimize integration can produce directly includable schedule
metadata from the explicit `Schedule`. Compile it during optimize/deployment,
not each scheduler tick.

`DBLayerScheduleStateRepository` stores completed and skipped outcomes but does
not create tables. The application migration must provide the documented
columns, including a textual `status` capable of storing `completed` and
`skipped`.
