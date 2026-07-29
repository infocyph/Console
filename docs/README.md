# Console documentation

Console is a framework-neutral command runtime. It owns command parsing,
rendering, command scopes, process execution, scheduling, and generic worker
supervision. It does not own application bootstrapping, queue delivery,
database schema, cache backends, or HTTP behavior.

## Guides

- [Installation](installation.md)
- [Commands and input](commands.md)
- [Output and prompts](output-and-prompts.md)
- [Configuration and compiled manifests](configuration-and-manifests.md)
- [Capabilities and command scopes](capabilities-and-scopes.md)
- [Process controls](process-controls.md)
- [Scheduling and leases](scheduling.md)
- [Worker supervision and shutdown](workers.md)
- [Omnibus messages and queues](omnibus.md)
- [Framework and queue boundaries](integration.md)
- [Testing](testing.md)
- [Operations and performance](operations.md)

Start with installation and commands for a standalone application. Framework
authors should also read capabilities, compiled manifests, and integration.
Operators running schedules or supervised workers should read process controls,
scheduling, workers, and operations.
