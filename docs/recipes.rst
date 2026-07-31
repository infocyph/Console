Real-world recipes
==================

This page assembles the individual modules into complete application flows.

Command lifecycle
-----------------

::

   argv
    -> compiled descriptor
    -> typed parse
    -> profile selection
    -> scoped container
    -> declared capabilities
    -> authorization / OTP / validation
    -> inline handler OR controlled child
    -> semantic output
    -> scope cleanup

Production command with validation and presentation
---------------------------------------------------

.. code-block:: php

   final class BillingReconcileCommand extends Command
   {
       public function __construct(private readonly BillingService $billing) {}

       public static function define(CommandDefinition $command): void
       {
           $command
               ->name('billing:reconcile')
               ->description('Reconcile one bounded billing period.')
               ->argument(
                   Argument::required('period')
                       ->rules(['required', 'date_format:Y-m']),
               )
               ->option(
                   Option::value('chunk')
                       ->type(ValueType::INTEGER)
                       ->default(500)
                       ->rules(['integer', 'min:1', 'max:5000']),
               )
               ->capabilities([
                   Capability::DATABASE,
                   Capability::IDENTITY,
               ])
               ->withoutOverlap(
                   mutex: 'billing-reconcile',
                   leaseSeconds: 600,
               )
               ->timeout(540, terminationGraceSeconds: 15)
               ->memoryLimit(256);
       }

       protected function handle(): int
       {
           $progress = $this->io()->progress(
               $this->billing->count($this->arguments()->string('period')),
               'Reconciling',
           );

           foreach ($this->billing->chunks($this->options()->int('chunk')) as $chunk) {
               $this->billing->reconcile($chunk);
               $progress->advance(count($chunk));
           }

           $progress->finish();
           $this->io()->success('Billing reconciliation completed.');

           return ExitCode::SUCCESS;
       }
   }

The database capability and overlap lock are not resolved for list or help.
The handler processes bounded chunks and updates one live semantic component.

Omnibus email pipeline
----------------------

.. code-block:: php

   final readonly class SendWelcomeEmail
   {
       public function __construct(
           public string $userId,
           public string $email,
       ) {}
   }

   $serializer = new JsonEnvelopeSerializer(
       new MessageCodecRegistry([
           new CallbackMessageCodec(
               'users.send-welcome.v1',
               SendWelcomeEmail::class,
               static fn(SendWelcomeEmail $message): array => [
                   'user_id' => $message->userId,
                   'email' => $message->email,
               ],
               static fn(array $payload): SendWelcomeEmail => new SendWelcomeEmail(
                   (string) $payload['user_id'],
                   (string) $payload['email'],
               ),
           ),
       ]),
       new StampCodecRegistry(CoreStampCodecs::all()),
   );

   $queue = new DBLayerTransport($connection, $serializer, $clock);
   $consumer = new ConsumerTask(new Consumer(
       $queue,
       new HandlerMap([
           SendWelcomeEmail::class => static function (SendWelcomeEmail $message) use ($mailer): void {
               $mailer->welcome($message->userId, $message->email);
           },
       ]),
       new ExponentialRetryStrategy(
           maximumAttempts: 5,
           initialDelaySeconds: 2.0,
           multiplier: 2.0,
       ),
       new DBLayerFailureStore($connection, $serializer),
       $clock,
   ));

   $application = Application::configure()
       ->omnibus()
       ->configureContainer(
           static function (Container $container) use ($consumer): void {
               $container->definitions()->bind(ConsumerTask::class, $consumer);
           },
       )
       ->build();

Run one bounded child manually:

.. code-block:: console

   php infbyte queue:consume --queue=emails --limit=25 --visibility=90

Dynamic email worker
--------------------

.. code-block:: php

   final readonly class EmailWorkerProvider implements WorkerProvider
   {
       public function __construct(private Receiver $receiver) {}

       public function command(): array
       {
           return [
               PHP_BINARY,
               'infbyte',
               'queue:consume',
               '--queue=emails',
               '--limit=25',
               '--visibility=90',
           ];
       }

       public function workload(): WorkloadProbe
       {
           return new ReceiverWorkloadProbe($this->receiver, 'emails');
       }

       public function options(): WorkerOptions
       {
           return new WorkerOptions(
               maximumProcesses: 8,
               jobsPerProcess: 25,
               scaleStep: 2,
               scaleDownProcesses: true,
               failureBackoffSeconds: 1.0,
               maximumConsecutiveFailures: 10,
               pollIntervalSeconds: 0.25,
               scaleCooldownSeconds: 1.0,
               processMaxSeconds: 300,
               supervisorMaxSeconds: 3_600,
               terminationGraceSeconds: 15,
           );
       }
   }

The ``WorkerProvider`` interface in this example belongs to Foundation's
application integration. Standalone Console users pass the same three values
directly to ``WorkerSupervisor``.

Scheduled report message
------------------------

.. code-block:: php

   $messages = new ScheduledMessages($schedule);
   $messages
       ->message('reports.daily')
       ->dailyAt('02:00')
       ->timezone('UTC')
       ->onOneServer(leaseSeconds: 180)
       ->withoutOverlap(leaseSeconds: 180)
       ->timeout(120);

The scheduler executes a factory key, Omnibus constructs and routes the
message, and a separate worker consumes it. Scheduler leases, queue
reservations, and handler idempotency remain distinct controls.

JSON automation
---------------

.. code-block:: console

   php infbyte --format=json app:ready
   php infbyte --format=json queue:consume --queue=emails --limit=10

Each logical output line is independently decodable. Automation should inspect
the process exit code and structured stderr as well as stdout.

Release operation
-----------------

.. code-block:: php

   final class ReleasePublishCommand extends Command
   {
       public static function define(CommandDefinition $command): void
       {
           $command
               ->name('release:publish')
               ->capabilities([
                   Capability::FILESYSTEM,
                   Capability::NETWORK,
                   Capability::IDENTITY,
               ])
               ->requiresOtp()
               ->withoutOverlap('release-publish', leaseSeconds: 900)
               ->timeout(840, terminationGraceSeconds: 30);
       }

       protected function handle(): int
       {
           $this->io()->title('Publish release');
           $this->io()->details([
               'release' => $this->arguments()->string('release'),
               'actor' => $this->context()->execution->id,
           ]);

           if (!$this->io()->prompts()->confirm('Publish now?', default: false)) {
               return ExitCode::INTERRUPTED;
           }

           // Verify, publish, and report through injected services.

           $this->io()->success('Release published.');

           return ExitCode::SUCCESS;
       }
   }

Authorization, OTP, confirmation, overlap exclusion, timeout, and artifact
verification solve different risks; none should be removed merely because
another control exists.
