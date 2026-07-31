Scheduling and leases
=====================

Defining schedules
------------------

.. code-block:: php

   use Infocyph\Console\Scheduling\Schedule;

   $schedule = new Schedule();
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
           // Record or publish a success signal.
       })
       ->onFailure(static function ($run): void {
           // Record or alert on failure.
       });

Frequency helpers include ``everyMinute()``, ``hourly()``, ``dailyAt()``, and a
five-field ``cron()`` expression. Timezone conversion happens before matching.
Arguments stay as separate argv values and never require shell parsing.

Running due entries
-------------------

``ScheduleRunner::runDue()`` evaluates the due set and invokes the supplied
executor:

.. code-block:: php

   $runs = $runner->runDue(
       $schedule,
       static function (string $name, $entry, $lease): int {
           while (hasMoreChunks()) {
               if ($lease !== null && !$lease->heartbeat()) {
                   return ExitCode::FAILURE;
               }

               processNextChunk();
           }

           return ExitCode::SUCCESS;
       },
   );

The executor should heartbeat between bounded chunks. A false result means the
distributed lease was lost; protected work must stop.

Overlap and single-server execution
-----------------------------------

``withoutOverlap()`` excludes concurrent copies of one entry.
``onOneServer()`` selects one node from a deployment. Both use the configured
``CommandMutex``. Lock contention produces an explicit ``skipped`` run and does
not prevent later due entries from running.

Persistence and compiled schedules
----------------------------------

``DBLayerScheduleStateRepository`` records run identifiers, command names,
scheduled and completion times, exit codes, and ``completed`` or ``skipped``
status. The application owns the table migration.

Compile schedule metadata during optimize/deployment:

.. code-block:: php

   (new ScheduleManifestCompiler())->write(
       $schedule,
       $cache . '/schedule.php',
   );

Application callbacks are intentionally absent from compiled metadata. Runtime
composition attaches behavior to the loaded schedule.
