Omnibus messages and queues
===========================

Ownership
---------

Omnibus is Console's message, event, workflow, and queue contract. Console owns
CLI metadata, schedules, child processes, scaling, and shutdown. Omnibus owns
envelopes, routing, transports, reservations, serialization, retries, failure
stores, idempotency policies, and handler execution.

Enable the integration explicitly:

.. code-block:: php

   use Infocyph\Console\Application;
   use Infocyph\InterMix\DI\Container;
   use Infocyph\Omnibus\Consumer\Command\ConsumerTask;
   use Infocyph\Omnibus\Scheduling\ScheduledMessageDispatcher;

   $application = Application::configure()
       ->omnibus()
       ->configureContainer(
           static function (Container $container) use ($consumer, $scheduled): void {
               $container->definitions()->bind(ConsumerTask::class, $consumer);
               $container->definitions()->bind(
                   ScheduledMessageDispatcher::class,
                   $scheduled,
               );
           },
       )
       ->build();

``omnibus()`` registers static command descriptors only. Help, list, version,
completion, and unrelated commands do not resolve the consumer, receiver,
transport, handlers, serializers, or failure store.

Commands
--------

``queue:consume`` performs one bounded ``ConsumerTask`` call:

.. code-block:: console

   php infbyte queue:consume
   php infbyte queue:consume --queue=emails --limit=100 --visibility=90

``schedule:dispatch-message`` creates and dispatches a registered factory key:

.. code-block:: console

   php infbyte schedule:dispatch-message reports.daily

Message factories keep closures and message objects out of compiled schedule
metadata.

Durable DBLayer example
-----------------------

The application constructs Omnibus with a DBLayer transport, then binds only
the completed ``ConsumerTask`` into Console:

.. code-block:: php

   use Infocyph\Omnibus\Clock\SystemClock;
   use Infocyph\Omnibus\Consumer\Command\ConsumerTask;
   use Infocyph\Omnibus\Consumer\Consumer;
   use Infocyph\Omnibus\Handler\HandlerMap;
   use Infocyph\Omnibus\Integration\DBLayer\DBLayerFailureStore;
   use Infocyph\Omnibus\Integration\DBLayer\DBLayerTransport;
   use Infocyph\Omnibus\Retry\ExponentialRetryStrategy;

   $clock = new SystemClock();
   $queue = new DBLayerTransport($connection, $serializer, $clock);
   $failures = new DBLayerFailureStore($connection, $serializer);
   $consumer = new ConsumerTask(new Consumer(
       $queue,
       new HandlerMap([
           SendReceipt::class => $sendReceipt(...),
       ]),
       new ExponentialRetryStrategy(
           maximumAttempts: 5,
           initialDelaySeconds: 2.0,
       ),
       $failures,
       $clock,
   ));

The DB connection, schema, serializer allow-list, and handler map are application
composition. Console does not duplicate them.

Redis or Valkey worker example
------------------------------

.. code-block:: php

   use Infocyph\Console\Omnibus\ReceiverWorkloadProbe;
   use Infocyph\Console\Worker\WorkerOptions;
   use Infocyph\Console\Worker\WorkerSupervisor;
   use Infocyph\Omnibus\Integration\Redis\CallbackRedisClient;
   use Infocyph\Omnibus\Integration\Redis\RedisTransport;

   $redis->connect($host, $port, 1.0);
   $client = new CallbackRedisClient(
       static fn(string $name, string ...$arguments): mixed =>
           $redis->rawCommand($name, ...$arguments),
   );
   $receiver = new RedisTransport(
       $client,
       $serializer,
       new SystemClock(),
   );

   $summary = (new WorkerSupervisor())->run(
       [PHP_BINARY, 'infbyte', 'queue:consume', '--queue=emails', '--limit=25'],
       new ReceiverWorkloadProbe($receiver, 'emails'),
       new WorkerOptions(
           maximumProcesses: 8,
           jobsPerProcess: 25,
           scaleStep: 2,
           failureBackoffSeconds: 1.0,
           maximumConsecutiveFailures: 10,
           stopWhenEmpty: false,
       ),
   );

``Receiver::size()`` may be exact or approximate depending on the backend.
Console clamps negative values and treats the result only as a scaling signal.

Scheduled messages
------------------

.. code-block:: php

   use Infocyph\Console\Omnibus\ScheduledMessages;

   (new ScheduledMessages($schedule))
       ->message('reports.daily')
       ->dailyAt('02:00')
       ->timezone('UTC')
       ->onOneServer(leaseSeconds: 180)
       ->withoutOverlap(leaseSeconds: 180)
       ->timeout(120);

The compiled entry is the ordinary command
``schedule:dispatch-message reports.daily``. Omnibus creates the message only
when that due entry executes.

Failure semantics
-----------------

Message retry or terminal failure is an Omnibus result. A Console process crash
is different and contributes to the supervisor failure circuit. Durable
handlers must remain idempotent because queue delivery is at least once.
