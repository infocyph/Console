<?php

declare(strict_types=1);

use Infocyph\Console\Application;
use Infocyph\Console\Discovery\CommandManifestCompiler;
use Infocyph\Console\Omnibus\ConsumeCommand;
use Infocyph\Console\Omnibus\DispatchScheduledMessageCommand;
use Infocyph\Console\Omnibus\ReceiverWorkloadProbe;
use Infocyph\Console\Omnibus\ScheduledMessages;
use Infocyph\Console\Scheduling\Schedule;
use Infocyph\Console\Scheduling\ScheduleManifestCompiler;
use Infocyph\Console\Testing\ApplicationTester;
use Infocyph\InterMix\DI\Container;
use Infocyph\Omnibus\Clock\SystemClock;
use Infocyph\Omnibus\Consumer\Command\ConsumerTask;
use Infocyph\Omnibus\Consumer\Consumer;
use Infocyph\Omnibus\Envelope\Envelope;
use Infocyph\Omnibus\Failure\InMemoryFailureStore;
use Infocyph\Omnibus\Handler\HandlerMap;
use Infocyph\Omnibus\MessageBus;
use Infocyph\Omnibus\Retry\ExponentialRetryStrategy;
use Infocyph\Omnibus\Routing\RouteMap;
use Infocyph\Omnibus\Scheduling\MessageFactoryMap;
use Infocyph\Omnibus\Scheduling\ScheduledMessageDispatcher;
use Infocyph\Omnibus\Transport\InMemoryTransport;
use Infocyph\Omnibus\Transport\SyncTransport;
use Infocyph\Omnibus\Transport\TransportRegistry;

final readonly class ConsoleOmnibusMessage
{
    public function __construct(public string $value) {}
}

it('registers Omnibus commands without resolving command infrastructure on preflight paths', function (): void {
    $configured = 0;
    $application = Application::configure()
        ->omnibus()
        ->configureContainer(static function () use (&$configured): void {
            $configured++;
        })
        ->build();

    $result = (new ApplicationTester($application))->command('list')->run();

    $result
        ->assertSuccessful()
        ->assertOutputContains('Messaging')
        ->assertOutputContains(ConsumeCommand::NAME)
        ->assertOutputContains(DispatchScheduledMessageCommand::NAME);
    expect($configured)->toBe(0);
});

it('consumes one bounded Omnibus batch through the Console command', function (): void {
    $clock = new SystemClock();
    $transport = new InMemoryTransport($clock);
    $transport->send(Envelope::wrap(new ConsoleOmnibusMessage('queued')), 'emails');
    $handled = [];
    $task = new ConsumerTask(new Consumer(
        $transport,
        new HandlerMap([
            ConsoleOmnibusMessage::class => static function (ConsoleOmnibusMessage $message) use (&$handled): void {
                $handled[] = $message->value;
            },
        ]),
        new ExponentialRetryStrategy(maximumAttempts: 1),
        new InMemoryFailureStore(),
        $clock,
    ));
    $application = Application::configure()
        ->omnibus()
        ->configureContainer(static function (Container $container) use ($task): void {
            $container->definitions()->bind(ConsumerTask::class, $task);
        })
        ->build();

    $result = (new ApplicationTester($application))
        ->command(ConsumeCommand::NAME)
        ->option('queue', 'emails')
        ->option('limit', 10)
        ->option('visibility', 30.0)
        ->run();

    $result
        ->assertSuccessful()
        ->assertOutputContains('received')
        ->assertOutputContains('succeeded');
    expect($handled)->toBe(['queued'])
        ->and($transport->size('emails'))->toBe(0);
});

it('reports invalid Omnibus consumption bounds as command usage errors', function (): void {
    $clock = new SystemClock();
    $transport = new InMemoryTransport($clock);
    $task = new ConsumerTask(new Consumer(
        $transport,
        new HandlerMap([]),
        new ExponentialRetryStrategy(),
        new InMemoryFailureStore(),
        $clock,
    ));
    $application = Application::configure()
        ->omnibus()
        ->configureContainer(static function (Container $container) use ($task): void {
            $container->definitions()->bind(ConsumerTask::class, $task);
        })
        ->build();

    $result = (new ApplicationTester($application))
        ->command(ConsumeCommand::NAME)
        ->option('limit', 0)
        ->run();

    $result->assertValidationFailed();
    expect($result->exitCode())->toBe(2);
});

it('uses Omnibus queue depth as the dynamic worker workload', function (): void {
    $transport = new InMemoryTransport(new SystemClock());
    $transport->send(Envelope::wrap(new ConsoleOmnibusMessage('one')), 'reports');
    $transport->send(Envelope::wrap(new ConsoleOmnibusMessage('two')), 'reports');

    expect(new ReceiverWorkloadProbe($transport, 'reports')->pending())->toBe(2);
});

it('dispatches scheduled factory keys and compiles them as ordinary schedule metadata', function (): void {
    $handled = [];
    $handlers = new HandlerMap([
        ConsoleOmnibusMessage::class => static function (ConsoleOmnibusMessage $message) use (&$handled): void {
            $handled[] = $message->value;
        },
    ]);
    $dispatcher = new ScheduledMessageDispatcher(
        new MessageFactoryMap([
            'reports.daily' => static fn(): ConsoleOmnibusMessage => new ConsoleOmnibusMessage('scheduled'),
        ]),
        new MessageBus(
            new RouteMap(),
            new TransportRegistry(['sync' => new SyncTransport($handlers)]),
        ),
    );
    $schedule = new Schedule();
    (new ScheduledMessages($schedule))
        ->message('reports.daily')
        ->dailyAt('02:00')
        ->onOneServer();
    $manifest = new ScheduleManifestCompiler()->compile($schedule);

    expect($manifest[0]['command'])->toBe(DispatchScheduledMessageCommand::NAME)
        ->and($manifest[0]['arguments'])->toBe(['reports.daily'])
        ->and($manifest[0]['on_one_server'])->toBeTrue();

    $application = Application::configure()
        ->omnibus()
        ->configureContainer(static function (Container $container) use ($dispatcher): void {
            $container->definitions()->bind(ScheduledMessageDispatcher::class, $dispatcher);
        })
        ->build();

    (new ApplicationTester($application))
        ->command(DispatchScheduledMessageCommand::NAME)
        ->argument('factory', 'reports.daily')
        ->run()
        ->assertSuccessful();

    expect($handled)->toBe(['scheduled']);
});

it('compiles Omnibus command descriptors for production manifests', function (): void {
    $manifest = new CommandManifestCompiler()->compile([
        ConsumeCommand::class,
        DispatchScheduledMessageCommand::class,
    ]);

    expect(array_keys($manifest))->toBe([
        ConsumeCommand::NAME,
        DispatchScheduledMessageCommand::NAME,
    ]);
});
