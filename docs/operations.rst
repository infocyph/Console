Operations and deployment
=========================

Build
-----

During build:

#. install exact dependencies;
#. compile command, validation, completion, schedule, and configuration
   manifests;
#. generate an optimized authoritative Composer classmap;
#. run CI, strict documentation, release, benchmark, and soak gates;
#. package an immutable artifact.

Do not compile or discover commands during normal application execution.

Run process types
-----------------

Keep scheduler, queue worker, one-off administration, and web processes
separate. Scale and limit each according to its own database, cache, queue,
memory, and downstream capacity.

Use an external process manager:

.. code-block:: ini

   [program:emails]
   command=/usr/bin/php /srv/app/infbyte worker:run emails
   directory=/srv/app
   autostart=true
   autorestart=unexpected
   stopsignal=TERM
   stopwaitsecs=30
   stdout_logfile=/dev/stdout
   stderr_logfile=/dev/stderr

Console writes normal output to stdout and errors to stderr. The environment
owns log routing, retention, restart, PID management, and deployment rollout.

Shutdown
--------

Send SIGTERM, stop accepting new work, allow bounded in-flight completion, and
force only after the configured grace. Omnibus reservations must be
acknowledged only after successful handling. Unfinished work must remain
recoverable by visibility expiry or explicit release.

Readiness
---------

Validate mandatory configuration and attached-resource connectivity before
starting traffic or workers. Optional command providers should not be connected
merely to render list or help output.

Observability
-------------

Record worker stop reason, child starts/completions/failures/forces, duration,
queue depth and growth, oldest-job age, processing rate, retry/failure rate,
memory growth, and downstream saturation. Alert on ``FAILURE_LIMIT``,
``HEARTBEAT_LOST``, repeated forced shutdown, or continuously growing depth.
