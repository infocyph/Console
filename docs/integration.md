# Framework and queue boundaries

## Framework integration

A framework should provide its existing container and configuration graph:

```php
$application = Application::configure()
    ->containerProvider($frameworkContainerProvider)
    ->configurationProvider($frameworkConfigurationProvider)
    ->commands($commands)
    ->build();
```

Console calls these providers only for a real command dispatch. Preflight
version/help/list/completion paths remain independent. Container wiring is
applied once per supplied container; execution state lives in a fresh InterMix
scope.

Framework optimize commands should compile:

- command descriptors;
- validation metadata;
- completion metadata;
- schedule metadata;
- container mappings when supported.

Framework clear commands remove only these known artifacts. Neither path should
scan packages during ordinary command dispatch.

## Queue boundary

Console owns:

- argv process creation;
- bounded dynamic concurrency;
- polling and scaling cadence;
- process/supervisor lifetimes;
- termination and force escalation;
- generic execution accounting.

Omnibus owns:

- the job envelope and serializer;
- queue/connection routing;
- reservation and visibility timeouts;
- retry, release, delay, and backoff;
- failed/dead-letter storage;
- unique-job and overlap semantics;
- dispatch-after-commit;
- delivery guarantees and idempotency hooks;
- queue depth/age telemetry.

Console requires Omnibus as its unified message contract and supplies
`ReceiverWorkloadProbe`, `queue:consume`, and
`schedule:dispatch-message` adapters. Applications enable those descriptors
explicitly with `ApplicationBuilder::omnibus()`.

This Composer dependency does not imply eager runtime composition. Preflight
paths read only Console command metadata. A receiver, consumer, serializer,
handler map, failure store, or transport is resolved only when its selected
command runs. Omnibus does not require Console, so producer-only and embedded
Omnibus applications remain independent.

## Scheduler boundary

Console owns schedule timing and generic leases. DBLayer may store schedule
outcomes, CacheLayer may provide leases, and a framework may expose system
commands. Console does not migrate application tables or create cache
connections automatically.

`ScheduledMessages` maps an explicit Omnibus factory key to the compiled
`schedule:dispatch-message` command. The manifest contains only the key and
ordinary schedule policy; it never serializes a closure or constructed message.
