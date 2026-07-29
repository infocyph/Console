# Worker supervision and shutdown

`WorkerSupervisor` is a queue-neutral process supervisor. It asks a
`WorkloadProbe` for pending work, calculates desired child concurrency, and
starts or drains an argv command. It does not reserve jobs, deserialize
payloads, implement retries, or know any queue backend.

```php
use Infocyph\Console\Worker\WorkerOptions;
use Infocyph\Console\Worker\WorkerSupervisor;
use Infocyph\Console\Omnibus\ReceiverWorkloadProbe;

$summary = (new WorkerSupervisor)->run(
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
        pollIntervalSeconds: 0.25,
        scaleCooldownSeconds: 1.0,
        processMaxSeconds: 300,
        supervisorMaxSeconds: 3_600,
        terminationGraceSeconds: 10,
        stopWhenEmpty: true,
    ),
    heartbeat: static fn(): bool => deploymentLeaseIsOwned(),
);
```

`queue:consume` is inherently one-shot: it performs one bounded
`ConsumerTask::run()` and exits. This keeps each supervised child restartable
and leaves reservation, retry, failure, and idempotency behavior in Omnibus.

Desired concurrency is:

`ceil(pending / jobsPerProcess)`, bounded by `minimumProcesses` and
`maximumProcesses`. Each scale pass starts at most `scaleStep` children.

## Worker options

| Option | Type | Default | Valid values and meaning |
| --- | --- | --- | --- |
| `minimumProcesses` | `int` | `0` | Non-negative warm floor |
| `maximumProcesses` | `int` | `1` | At least `1` and not below the minimum |
| `jobsPerProcess` | `int` | `1` | Positive pending-work capacity represented by one process |
| `scaleStep` | `int` | `1` | Positive maximum processes started per scale pass |
| `scaleDownProcesses` | `bool` | `false` | Enable signal-based draining when desired concurrency falls |
| `pollIntervalSeconds` | `float` | `1.0` | Positive supervisor polling interval |
| `scaleCooldownSeconds` | `float` | `1.0` | Non-negative interval between scale decisions |
| `processMaxSeconds` | `?float` | `null` | Positive child lifetime, for example `300.0` |
| `supervisorMaxSeconds` | `?float` | `null` | Positive supervisor lifetime, for example `3600.0` |
| `maximumProcessesStarted` | `?int` | `null` | Positive lifetime start budget, for example `1000` |
| `terminationGraceSeconds` | `float` | `5.0` | Non-negative drain time before forced termination |
| `stopWhenEmpty` | `bool` | `false` | Finish after no pending work and no live children |
| `workingDirectory` | `?string` | `null` | Child working directory, for example `/srv/app` |
| `environment` | `array<string,string>` | `[]` | Child environment additions/overrides |

## Safe scale-down

Scale-down is opt-in because a generic supervisor cannot know whether a child
currently owns a job. Enable it only when the child:

- handles the termination signal;
- stops accepting new jobs;
- finishes or safely releases its active reservation;
- exits within the grace period;
- leaves retry/idempotency decisions to the queue implementation.

One-shot consumers are the simplest integration: each process reserves and
handles a bounded amount of work, then exits. Long-running consumers need an
explicit drain protocol.

## Shutdown and accounting

An interrupt, failed heartbeat, supervisor lifetime, process lifetime, or
scale-down decision sends graceful termination first. A child still alive
after `terminationGraceSeconds` is force-killed. `WorkerRunSummary` reports:

- `started`: child processes successfully created;
- `completed`: children reaped exactly once;
- `failed`: reaped children with a non-zero exit code;
- `forced`: children requiring force termination;
- `interrupted`: supervisor interrupt or heartbeat ownership loss;
- `durationSeconds`: total supervisor runtime.

`successful()` is true only when the run was not interrupted and no child
failed or required force.

## Lease and deployment behavior

Use the supervisor heartbeat for an external ownership/deployment lease. Once
it returns `false`, Console marks the run interrupted and drains every child.
Do not continue processing while attempting to reacquire ownership inside the
same run.

For deployments, stop dispatching new work, signal the supervisor, wait for the
bounded grace period, and let the queue package release or retry unfinished
reservations according to its documented delivery semantics.
