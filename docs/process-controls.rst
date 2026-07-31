Process execution and command controls
======================================

Direct process execution
------------------------

``ProcessRunner`` accepts an argv list, never a shell command string:

.. code-block:: php

   use Infocyph\Console\Process\ProcessMode;
   use Infocyph\Console\Process\ProcessOptions;
   use Infocyph\Console\Process\ProcessRunner;

   $result = (new ProcessRunner())->run(
       [PHP_BINARY, 'bin/import', '--tenant', $tenant],
       new ProcessOptions(
           workingDirectory: '/srv/app',
           environment: ['APP_RELEASE' => '2026.07.31'],
           timeoutSeconds: 300,
           idleTimeoutSeconds: 30,
           sensitiveValues: [$token],
           mode: ProcessMode::CAPTURE,
           terminationGraceSeconds: 5,
       ),
   );

Captured and streamed output is redacted across chunk boundaries. Timeouts,
idle timeouts, heartbeat loss, cancellation, SIGINT, and SIGTERM request
graceful termination before a forced kill. If a callback throws, the child is
drained before the exception is propagated.

Command execution policy
------------------------

Inline execution is the default and shortest path. A command is promoted to an
isolated child only when it declares a control that requires process
enforcement:

.. code-block:: php

   $command
       ->name('report:build')
       ->withoutOverlap(
           mutex: 'reports',
           leaseSeconds: 180,
           waitSeconds: 2,
       )
       ->timeout(120, terminationGraceSeconds: 5)
       ->idleTimeout(30)
       ->memoryLimit(256);

The policy is compiled with the descriptor. Parent and child execution are
distinguished through an internal environment marker so controls do not
recursively spawn another child.

Lock behavior
-------------

``CommandMutex`` uses a CacheLayer ``LockProviderInterface``. File, Redis,
Valkey, Memcached, and PDO providers share the same Console boundary. A lock
factory remains lazy for help, list, completion, version, and commands that do
not declare overlap control.

Use a shared backend whenever exclusion must work across hosts. A local file
lock is only host-wide.

Operational guidance
--------------------

Pass secrets through protected input or environment where possible, not argv.
Set bounded timeout and memory policies for administrative jobs. Heavy
one-off work should use batches, checkpoints, resumability, and an external
process manager rather than unbounded Console subprocesses.
