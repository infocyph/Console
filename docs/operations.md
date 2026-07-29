# Operations and performance

## Build and deployment

1. Install from the committed lock file.
2. Compile command, validation, completion, schedule, and container metadata.
3. Validate that production mode boots from those artifacts.
4. Warm only metadata and commands that production will actually reuse.
5. Start scheduler and worker processes separately from web runtimes.

Never perform command discovery, reflection-based descriptor generation, or
package scanning on a production dispatch path.

## Benchmarks

Run the command-path microbenchmarks:

```bash
composer benchmark
composer benchmark -- --enforce
```

These results compare Console operations only; they do not prove
application-level requests per minute. Measure complete framework commands and
workers with their real database/cache/queue services before making production
throughput claims.

## Bounded worker soak

```bash
composer soak:worker
```

Environment controls:

| Variable | Default | Meaning |
| --- | ---: | --- |
| `CONSOLE_WORKER_SOAK_CYCLES` | `25` | Repeated 3-to-1-to-0 scale/shutdown cycles; valid range `1..1000` |
| `CONSOLE_WORKER_SOAK_MAX_GROWTH_BYTES` | `4194304` | Maximum allowed process memory growth |

The soak fails on incorrect start/completion/failure/force accounting or excess
memory growth. For a real long-running queue consumer, also use PHPForge's
generic monitor:

```bash
composer ic:soak:worker -- --duration=300 -- php infbyte queue:work
```

Run that only with a consumer command intended to remain alive for the selected
duration.

## Operational signals

Record at minimum:

- execution or supervisor ID;
- child starts, completions, failures, and forced stops;
- pending workload and desired/live concurrency;
- interrupt/heartbeat-loss reason;
- duration and exit code;
- steady-state and peak memory;
- queue depth/age and reservation state from the queue owner.

Keep metric label cardinality bounded. Do not include command secrets, tokens,
raw arguments, or tenant identifiers unless an explicit redaction and privacy
policy permits them.
