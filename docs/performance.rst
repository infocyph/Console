Performance and capacity
========================

Execution paths
---------------

Measure preflight, inline commands, isolated commands, schedule ticks, queue
consumption, and dynamic supervision separately. A fast list command does not
prove worker throughput, and a process microbenchmark does not prove
application RPM.

The short path
--------------

Preflight uses static descriptors and compiled manifests. Inline commands avoid
``proc_open``. Optional capability, configuration, validation, Omnibus, and
container work remains lazy until a selected command needs it.

InterMix integration
--------------------

Console reuses immutable standalone wiring while entering a fresh scope for
each command. Imported service providers run once, and invokable command
classes are constructed without invoking ``__invoke()``.

Compiled container artifacts trade cache-time work for less runtime
reflection. ``compiledContainer()`` validates the complete artifact when the
command container is first needed. Immutable deployments may instead use
``prevalidatedCompiledContainer()`` with the fingerprint emitted during cache
generation. Do not enable either path solely because an artifact exists:
without OPcache/preloading, loading it can cost more than dynamic resolution
for a small command.

Worker capacity
---------------

Choose ``jobsPerProcess`` from the bounded amount of work one child handles.
Increase ``maximumProcesses`` only until successful processing rate stops
rising or database, cache, queue, CPU, memory, connection, error, timeout, or
tail-latency pressure becomes unsafe.

The failure backoff and consecutive failure circuit prevent a permanently
broken child from consuming process and infrastructure capacity in a tight
restart loop.

Benchmarks
----------

``composer benchmark`` measures ANSI and plain rendering, argv bootstrap,
command lookup, frame diffing, prompt filtering, and tables. Use it to catch
component regressions, not to claim application throughput.

``composer soak:worker`` repeats scale-up, scale-down, graceful shutdown, exact
child accounting, and memory-growth checks. Production-equivalent queue tests
must additionally record successful jobs per minute, failures, retries, queue
growth, p95/p99 handling time, CPU, memory, and backend pressure.
