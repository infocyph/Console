Dynamic worker supervision
==========================

Purpose and boundary
--------------------

``WorkerSupervisor`` is queue-neutral. It reads pending work from a
``WorkloadProbe``, calculates desired child concurrency, and supervises an argv
command. It does not reserve jobs, deserialize messages, retry handlers, or
store failures.

.. code-block:: php

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
           minimumProcesses: 0,
           maximumProcesses: 8,
           jobsPerProcess: 10,
           scaleStep: 2,
           scaleDownProcesses: true,
           failureBackoffSeconds: 1.0,
           maximumConsecutiveFailures: 10,
           pollIntervalSeconds: 0.25,
           scaleCooldownSeconds: 1.0,
           processMaxSeconds: 300,
           supervisorMaxSeconds: 3_600,
           maximumProcessesStarted: 10_000,
           terminationGraceSeconds: 10,
           stopWhenEmpty: true,
           workingDirectory: '/srv/app',
           environment: ['APP_PROCESS' => 'queue-emails'],
       ),
       heartbeat: static fn(): bool => deploymentLeaseIsOwned(),
   );

Scaling
-------

Desired concurrency is:

.. math::

   \operatorname{clamp}\left(
       \left\lceil \frac{\text{pending}}{\text{jobsPerProcess}} \right\rceil,
       \text{minimumProcesses},
       \text{maximumProcesses}
   \right)

Each decision starts at most ``scaleStep`` processes. ``scaleCooldownSeconds``
bounds decision frequency. ``scaleDownProcesses`` is opt-in because a generic
supervisor cannot know whether a child currently owns work.

Crash-loop protection
---------------------

A non-zero child exit increments the consecutive failure count. Console waits
``failureBackoffSeconds`` before replacing a failed process and stops with
``FAILURE_LIMIT`` after ``maximumConsecutiveFailures``. Set the limit to
``null`` only when an external supervisor provides an equivalent bounded
restart policy.

A successful child resets the consecutive failure count. Omnibus one-shot
consumers normally return success after recording message-level retries or
terminal failures; non-zero child exits therefore indicate process or
infrastructure failure.

Shutdown and cleanup
--------------------

SIGINT, SIGTERM, heartbeat loss, supervisor lifetime, process lifetime,
scale-down, and exceptions request graceful child termination. A child still
running after ``terminationGraceSeconds`` is force-killed. If workload probing,
heartbeats, or process startup throws, all already-started children are drained
before the exception leaves ``run()``.

``WorkerRunSummary`` reports ``started``, ``completed``, ``failed``, ``forced``,
``interrupted``, ``durationSeconds``, and ``stopReason``. Stop reasons are:

.. list-table::
   :header-rows: 1

   * - Reason
     - Meaning
   * - ``EMPTY``
     - No pending work remained and ``stopWhenEmpty`` was enabled.
   * - ``START_LIMIT``
     - The lifetime child-start budget was exhausted.
   * - ``SUPERVISOR_LIMIT``
     - The supervisor lifetime elapsed.
   * - ``INTERRUPTED``
     - SIGINT or SIGTERM requested shutdown.
   * - ``HEARTBEAT_LOST``
     - External lease or deployment ownership was lost.
   * - ``FAILURE_LIMIT``
     - Consecutive child failures reached the configured circuit limit.

``successful()`` is true only when the run was not interrupted and no child
failed or required force.

Safe child contract
-------------------

A scale-down-safe child must stop accepting work on termination, finish or
release the active reservation, exit inside the grace window, and make durable
side effects idempotent. Prefer one-shot bounded consumers when possible.
Let systemd, Supervisor, Kubernetes, or another process manager restart the
top-level Console process; Console does not daemonize or own PID files.
