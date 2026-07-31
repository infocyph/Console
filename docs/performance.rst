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
