Architecture and ownership
==========================

Execution graph
---------------

::

   argv
     |
     +-- preflight: version / help / list / completion
     |      command registry + compiled metadata only
     |
     `-- command dispatch
            |
            +-- select descriptor and parse argv once
            +-- select configuration profile
            +-- create InterMix command scope
            +-- load declared capabilities
            +-- authorize and validate
            `-- inline handler or supervised child process

Console keeps metadata and runtime resolution separate. A descriptor contains
the stable command name, aliases, typed inputs, capability declarations, and
execution policy. The command object is not created until the selected route
runs.

Package boundaries
------------------

Console owns:

* argv parsing and command metadata;
* text, ANSI, JSON, terminal components, and prompts;
* process execution, timeouts, redaction, and signal handling;
* cron schedules, schedule leases, and worker process supervision;
* command testing helpers and compiled command metadata.

InterMix owns dependency injection and execution scopes. ArrayKit owns
configuration primitives. UID owns execution identifiers. CacheLayer owns lock
providers. DBLayer owns database access. Omnibus owns messages, transports,
reservations, retries, failures, and handlers.

Console does not select an application database, cache, queue, HTTP client, or
secret provider. Those resources are injected explicitly by an application or
framework adapter.

Lazy boundaries
---------------

Optional adapters remain outside the common path. Registering a capability or
calling ``ApplicationBuilder::omnibus()`` stores static metadata; it does not
connect to a backend. Container construction, validation manifests, queue
receivers, message serializers, and command services resolve only after a real
command has been selected.

Persistent processes
--------------------

Every command enters a fresh InterMix scope. Parsed input, command context, IO,
and execution identity are seeded into that scope and removed when execution
ends. Immutable wiring may be reused, while command-specific state cannot leak
into the next execution.
