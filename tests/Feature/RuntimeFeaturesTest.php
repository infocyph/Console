<?php

declare(strict_types=1);

use Infocyph\CacheLayer\Cache\Lock\LockHandle;
use Infocyph\CacheLayer\Cache\Lock\LockProviderInterface;
use Infocyph\Console\Application;
use Infocyph\Console\Cache\CommandMutex;
use Infocyph\Console\Command\Capability;
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandContext;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\CommandDescriptor;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Component\Details;
use Infocyph\Console\Component\HorizontalRule;
use Infocyph\Console\Component\Paragraph;
use Infocyph\Console\Configuration\Configuration;
use Infocyph\Console\Configuration\ConfigurationProvider;
use Infocyph\Console\Container\ContainerProvider;
use Infocyph\Console\Discovery\CommandManifestCompiler;
use Infocyph\Console\Discovery\CompletionManifestCompiler;
use Infocyph\Console\Discovery\ValidationManifestCompiler;
use Infocyph\Console\Filesystem\Workspace;
use Infocyph\Console\Identity\CommandExecution;
use Infocyph\Console\Input\Argument;
use Infocyph\Console\Input\Option;
use Infocyph\Console\Input\ParsedInput;
use Infocyph\Console\Input\ValueType;
use Infocyph\Console\IO\BufferedIO;
use Infocyph\Console\IO\ConsoleIO;
use Infocyph\Console\Otp\OtpVerifier;
use Infocyph\Console\Process\ProcessMode;
use Infocyph\Console\Process\ProcessOptions;
use Infocyph\Console\Process\ProcessRunner;
use Infocyph\Console\Prompt\AnswerQueue;
use Infocyph\Console\Prompt\PromptManager;
use Infocyph\Console\Render\AnsiRenderer;
use Infocyph\Console\Render\Frame;
use Infocyph\Console\Render\FrameDiffer;
use Infocyph\Console\Render\JsonRenderer;
use Infocyph\Console\Render\Line;
use Infocyph\Console\Render\PlainRenderer;
use Infocyph\Console\Scheduling\Schedule;
use Infocyph\Console\Scheduling\ScheduleManifest;
use Infocyph\Console\Scheduling\ScheduleManifestCompiler;
use Infocyph\Console\Scheduling\ScheduleRun;
use Infocyph\Console\Scheduling\ScheduleRunner;
use Infocyph\Console\Scheduling\ScheduleStateRepository;
use Infocyph\Console\Security\ArtifactVerifier;
use Infocyph\Console\Security\CommandAuthorizationPolicy;
use Infocyph\Console\Security\SecretStore;
use Infocyph\Console\Security\SecureConfiguration;
use Infocyph\Console\Style\Color;
use Infocyph\Console\Style\Style;
use Infocyph\Console\Style\Theme;
use Infocyph\Console\Terminal\CapabilityDetector;
use Infocyph\Console\Terminal\ColorDepth;
use Infocyph\Console\Terminal\SignalManager;
use Infocyph\Console\Terminal\TerminalCapabilities;
use Infocyph\Console\Testing\ApplicationTester;
use Infocyph\Console\Testing\CommandResult;
use Infocyph\Console\Testing\FakeCapabilityLoader;
use Infocyph\Console\Testing\FakeClock;
use Infocyph\Console\Testing\FakeSignalManager;
use Infocyph\Console\Testing\FakeTerminal;
use Infocyph\Console\Testing\FrameSnapshot;
use Infocyph\Console\Testing\SubprocessRunner;
use Infocyph\Epicrypt\Generate\KeyMaterial\KeyMaterialGenerator;
use Infocyph\InterMix\DI\Container;

final readonly class InjectedGreeting
{
    public function message(): string
    {
        return 'Injected service resolved.';
    }
}

final class InjectedCommand extends Command
{
    public function __construct(private readonly InjectedGreeting $greeting, private readonly CommandContext $context) {}

    public static function define(CommandDefinition $command): void
    {
        $command->name('injected');
    }

    protected function handle(): int
    {
        $this->io()->success($this->greeting->message());
        $this->io()->success($this->context->input()->tokens() === [] ? 'Scoped context available.' : 'Unexpected tokens.');

        return ExitCode::SUCCESS;
    }
}

final class PromptCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('prompt');
    }

    protected function handle(): int
    {
        $name = $this->io()->prompts()->text('Name', required: true, sanitize: ['trim', 'lowercase']);
        $this->io()->success('Hello '.$name.'.');

        return ExitCode::SUCCESS;
    }
}

final class ValidatedCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('user:create')
            ->argument(Argument::required('email')->sanitize(['trim', 'lowercase'])->rules(['email', 'max:255']))
            ->option(Option::value('age')->type(ValueType::INTEGER)->rules(['min:18', 'max:120']));
    }

    protected function handle(): int
    {
        $this->io()->success($this->arguments()->string('email').' '.$this->options()->nullableInt('age'));

        return ExitCode::SUCCESS;
    }
}

final class ConfigurationCommand extends Command
{
    public function __construct(private readonly Configuration $configuration) {}

    public static function define(CommandDefinition $command): void
    {
        $command->name('config:show');
    }

    protected function handle(): int
    {
        $this->io()->success((string) $this->configuration->string('name'));

        return ExitCode::SUCCESS;
    }
}

final class ExternalRuntimeCommand extends Command
{
    public static int $instances = 0;

    public function __construct(
        private readonly Configuration $configuration,
        private readonly InjectedGreeting $greeting,
    ) {
        self::$instances++;
    }

    public static function define(CommandDefinition $command): void
    {
        $command->name('external:run')->capabilities([Capability::NETWORK]);
    }

    protected function handle(): int
    {
        $this->io()->success($this->configuration->string('name') . ' ' . $this->greeting->message());

        return ExitCode::SUCCESS;
    }
}

final class ManifestCommand extends Command
{
    public static int $definitions = 0;

    public static function define(CommandDefinition $command): void
    {
        self::$definitions++;
        $command->name('manifest:run')->argument(Argument::required('name'));
    }

    protected function handle(): int
    {
        $this->io()->success($this->arguments()->string('name'));

        return ExitCode::SUCCESS;
    }
}

final class ProtectedIdentityCommand extends Command
{
    public function __construct(private readonly CommandExecution $execution) {}

    public static function define(CommandDefinition $command): void
    {
        $command->name('production:reset')->requiresOtp()->capabilities([Capability::IDENTITY, Capability::OTP]);
    }

    protected function handle(): int
    {
        $this->io()->success($this->execution->command.':'.$this->execution->id);

        return ExitCode::SUCCESS;
    }
}

final class NetworkCapabilityCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('remote:check')->capabilities([Capability::NETWORK]);
    }

    protected function handle(): int
    {
        return ExitCode::SUCCESS;
    }
}

final class TableFixtureCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('table:show');
    }

    protected function handle(): int
    {
        $this->io()->table(['Name', 'State'], [['Console', 'ready']]);

        return ExitCode::SUCCESS;
    }
}

final class ThrowingFixtureCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command->name('throwing');
    }

    protected function handle(): int
    {
        throw new RuntimeException('sensitive internal detail');
    }
}

it('constructs only the selected command through the InterMix command scope', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()
        ->commands([InjectedCommand::class])
        ->configureContainer(function (Container $container): void {
            $container->definitions()->bind(InjectedGreeting::class, new InjectedGreeting);
        })
        ->io($io)
        ->build();

    expect($application->run(['tool', 'injected']))->toBe(0)
        ->and($io->output())->toBe(['[OK] Injected service resolved.', '[OK] Scoped context available.']);
});

it('renders semantic frames in plain and JSON formats', function (): void {
    $frame = new Frame([new Line('Created.', 'success')]);
    expect((new PlainRenderer)->render($frame))->toBe('[OK] Created.'.PHP_EOL)
        ->and((new JsonRenderer)->render($frame))->toBe('{"type":"success","message":"Created."}'.PHP_EOL);
});

it('finds changed and stale frame lines for live updates', function (): void {
    $differ = new FrameDiffer;
    $before = new Frame([new Line('one'), new Line('two')]);
    $after = new Frame([new Line('one'), new Line('three')]);
    expect($differ->changedLineIndexes($before, $after))->toBe([1])
        ->and($differ->staleLineCount($before, $after))->toBe(0)
        ->and($differ->staleLineCount($before, new Frame([])))->toBe(2);
});

it('renders typed visual components through buffered IO', function (): void {
    $io = new BufferedIO;
    $io->title('Deploy');
    $io->table(['Name', 'State'], [['API', 'ready']]);
    $io->listing(['first', 'second'], ordered: true);
    expect($io->outputText())->toContain('Deploy')
        ->toContain('| Name | State |')
        ->toContain('1. first');
});

it('uses queued prompt answers and refuses missing input in non-interactive mode', function (): void {
    $io = new BufferedIO(['  ADA  ']);
    $application = Application::configure()->commands([PromptCommand::class])->io($io)->build();
    expect($application->run(['tool', 'prompt']))->toBe(0)
        ->and($io->output())->toContain('[OK] Hello ada.');

    $quietIo = new BufferedIO;
    $nonInteractive = Application::configure()->commands([PromptCommand::class])->io($quietIo)->build();
    expect($nonInteractive->run(['tool', '--no-interaction', 'prompt']))->toBe(ExitCode::INVALID_USAGE)
        ->and($quietIo->errors())->toBe(['[ERROR] Name requires input, but interaction is disabled.']);
});

it('sanitizes and semantically validates parsed command input once before resolving the command', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()->commands([ValidatedCommand::class])->io($io)->build();
    expect($application->run(['tool', 'user:create', '  ADA@EXAMPLE.COM  ', '--age', '30']))->toBe(0)
        ->and($io->output())->toBe(['[OK] ada@example.com 30']);
});

it('renders all ReqShield validation failures as a usage error', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()->commands([ValidatedCommand::class])->io($io)->build();
    expect($application->run(['tool', 'user:create', 'invalid', '--age', '14']))->toBe(ExitCode::INVALID_USAGE)
        ->and($io->errors())->toContain('[ERROR] Invalid command input')
        ->toContain('[ERROR] email: The Email must be a valid email address.')
        ->toContain('[ERROR] age: The Age must be at least 18.');
});

it('applies ReqShield rules to prompt values when they are declared', function (): void {
    $io = new BufferedIO(['  ADA@EXAMPLE.COM  ']);
    expect($io->prompts()->text('Email', sanitize: ['trim', 'lowercase'], rules: ['email']))->toBe('ada@example.com');
});

it('emits structured JSON validation failures', function (): void {
    $output = fopen('php://temp', 'w+');
    $errors = fopen('php://temp', 'w+');
    $io = new ConsoleIO($output, $errors, new TerminalCapabilities(false, false, true, ColorDepth::NONE, 80, 24));
    $io->setFormat('json');
    $io->validationFailures([['field' => 'email', 'rule' => 'email', 'message' => 'Invalid email.']]);
    rewind($errors);
    expect(stream_get_contents($errors))->toBe('{"error":"invalid_input","exit_code":2,"failures":[{"field":"email","rule":"email","message":"Invalid email."}]}'.PHP_EOL);
});

it('loads validated application configuration only for a resolved command', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()
        ->configuration(['name' => '  Console  ', 'nested' => ['one' => true]])
        ->configuration(['nested' => ['two' => true]])
        ->validateConfiguration(['name' => ['required', 'string', 'min:3']], ['name' => ['trim']])
        ->commands([ConfigurationCommand::class])
        ->io($io)
        ->build();
    expect($application->run(['tool', 'config:show']))->toBe(0)
        ->and($io->output())->toBe(['[OK] Console']);
});

it('lazily reuses external container and configuration providers across command scopes', function (): void {
    $container = new Container('testing.external');
    $containerProvider = new class($container) implements ContainerProvider
    {
        public int $calls = 0;

        public function __construct(private readonly Container $container) {}

        public function container(): Container
        {
            $this->calls++;

            return $this->container;
        }
    };
    $configurationProvider = new class implements ConfigurationProvider
    {
        public int $calls = 0;

        public int $profileChanges = 0;

        private ?string $profile = null;

        public function configuration(): Configuration
        {
            $this->calls++;

            return Configuration::fromArray(['name' => 'external-' . ($this->profile ?? 'default')]);
        }

        public function useProfile(?string $profile): void
        {
            $this->profileChanges++;
            $this->profile = $profile;
        }
    };
    $containerConfigurations = 0;
    $networkActivations = 0;
    $io = new BufferedIO;
    ExternalRuntimeCommand::$instances = 0;
    $application = Application::configure()
        ->commands([ExternalRuntimeCommand::class])
        ->containerProvider($containerProvider)
        ->configurationProvider($configurationProvider)
        ->configureContainer(function (Container $configured) use (&$containerConfigurations): void {
            $containerConfigurations++;
            $configured->definitions()->bind(InjectedGreeting::class, new InjectedGreeting);
        })
        ->configureCapability(Capability::NETWORK, function () use (&$networkActivations): void {
            $networkActivations++;
        })
        ->io($io)
        ->build();

    expect($application->run(['tool', '--version']))->toBe(ExitCode::SUCCESS)
        ->and($containerProvider->calls)->toBe(0)
        ->and($configurationProvider->calls)->toBe(0)
        ->and($configurationProvider->profileChanges)->toBe(0)
        ->and($containerConfigurations)->toBe(0)
        ->and($networkActivations)->toBe(0)
        ->and($application->run(['tool', '--profile=one', 'external:run']))->toBe(ExitCode::SUCCESS)
        ->and($application->run(['tool', '--profile=two', 'external:run']))->toBe(ExitCode::SUCCESS)
        ->and($containerProvider->calls)->toBe(2)
        ->and($configurationProvider->calls)->toBe(2)
        ->and($configurationProvider->profileChanges)->toBe(2)
        ->and($containerConfigurations)->toBe(1)
        ->and($networkActivations)->toBe(1)
        ->and(ExternalRuntimeCommand::$instances)->toBe(2)
        ->and($container->has(CommandContext::class))->toBeFalse()
        ->and($container->has(ParsedInput::class))->toBeFalse()
        ->and($io->output())->toContain('[OK] external-one Injected service resolved.')
        ->toContain('[OK] external-two Injected service resolved.');
});

it('lazily creates and reuses one standalone container across isolated command scopes', function (): void {
    $configurations = 0;
    $io = new BufferedIO;
    $application = Application::configure()
        ->commands([InjectedCommand::class])
        ->configureContainer(function (Container $container) use (&$configurations): void {
            $configurations++;
            $container->definitions()->bind(InjectedGreeting::class, new InjectedGreeting);
        })
        ->io($io)
        ->build();

    expect($configurations)->toBe(0)
        ->and($application->run(['tool', 'injected']))->toBe(ExitCode::SUCCESS)
        ->and($application->run(['tool', 'injected']))->toBe(ExitCode::SUCCESS)
        ->and($configurations)->toBe(1);
});

it('rejects ambiguous local and external configuration sources', function (): void {
    $provider = new class implements ConfigurationProvider
    {
        public function configuration(): Configuration
        {
            return Configuration::fromArray([]);
        }

        public function useProfile(?string $profile): void {}
    };

    expect(fn() => Application::configure()
        ->configuration(['name' => 'local'])
        ->configurationProvider($provider)
        ->build())
        ->toThrow(LogicException::class, 'cannot be combined');
});

it('loads compiled command metadata without executing definitions at runtime', function (): void {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'console-manifest-'.bin2hex(random_bytes(6)).'.php';
    try {
        ManifestCommand::$definitions = 0;
        (new CommandManifestCompiler)->write([ManifestCommand::class], $path);
        expect(ManifestCommand::$definitions)->toBe(1);
        ManifestCommand::$definitions = 0;
        $io = new BufferedIO;
        $application = Application::configure()->commandManifest($path)->io($io)->build();
        expect($application->run(['tool', 'manifest:run', 'Ada']))->toBe(0)
            ->and($io->output())->toBe(['[OK] Ada'])
            ->and(ManifestCommand::$definitions)->toBe(0);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
        if (is_dir($path.'.d')) {
            foreach (glob($path.'.d'.DIRECTORY_SEPARATOR.'*.php') ?: [] as $entry) {
                unlink($entry);
            }
            rmdir($path.'.d');
        }
    }
});

it('preserves authoritative command map names in compiled manifests', function (): void {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'console-mapped-manifest-'.bin2hex(random_bytes(6)).'.php';
    try {
        (new CommandManifestCompiler)->write(['manifest:mapped' => ManifestCommand::class], $path);
        $io = new BufferedIO;
        $application = Application::configure()->commandManifest($path)->io($io)->build();

        expect($application->run(['tool', 'manifest:mapped', 'Ada']))->toBe(ExitCode::SUCCESS)
            ->and($io->output())->toBe(['[OK] Ada']);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
        if (is_dir($path.'.d')) {
            foreach (glob($path.'.d'.DIRECTORY_SEPARATOR.'*.php') ?: [] as $entry) {
                unlink($entry);
            }
            rmdir($path.'.d');
        }
    }
});

it('activates declared infrastructure only for selected commands and authorizes OTP commands', function (): void {
    $io = new BufferedIO(['123456']);
    $networkActivations = 0;
    $application = Application::configure()
        ->commands([ProtectedIdentityCommand::class, NetworkCapabilityCommand::class])
        ->otpVerifier(new class implements OtpVerifier
        {
            public function verify(string $code): bool
            {
                return $code === '123456';
            }
        })
        ->configureCapability(Capability::NETWORK, function () use (&$networkActivations): void {
            $networkActivations++;
        })
        ->io($io)
        ->build();

    expect($application->run(['tool', '--version']))->toBe(0)
        ->and($networkActivations)->toBe(0)
        ->and($application->run(['tool', 'production:reset']))->toBe(0)
        ->and($io->outputText())->toContain('Verification code:')
        ->toContain('[OK] production:reset:')
        ->and($networkActivations)->toBe(0)
        ->and($application->run(['tool', 'remote:check']))->toBe(0)
        ->and($networkActivations)->toBe(1);
});

it('protects secure configuration and verifies artifact content through Epicrypt', function (): void {
    $key = (new KeyMaterialGenerator)->forSecretBox();
    $configuration = new SecureConfiguration($key);
    $encrypted = $configuration->encrypt('sensitive-value');

    expect($encrypted)->not->toBe('sensitive-value')
        ->and($configuration->decrypt($encrypted))->toBe('sensitive-value')
        ->and((new ArtifactVerifier)->verifyContents('release', hash('sha256', 'release')))->toBeTrue()
        ->and((new ArtifactVerifier)->verifyContents('release', hash('sha256', 'other')))->toBeFalse();
});

it('stores encrypted local secrets using atomic Pathwise workspace writes', function (): void {
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'console-workspace-'.bin2hex(random_bytes(6));
    try {
        $workspace = new Workspace($root);
        $key = (new KeyMaterialGenerator)->forSecretBox();
        $secrets = new SecretStore($workspace, $key);
        $secrets->put('api.token', 'local-secret');

        expect($secrets->get('api.token'))->toBe('local-secret')
            ->and(file_get_contents($workspace->path('.console/secrets/api.token.secret')))->not->toBe('local-secret')
            ->and($workspace->checksum('.console/secrets/api.token.secret'))->toBe(hash_file('sha256', $workspace->path('.console/secrets/api.token.secret')));
        $secrets->forget('api.token');
        expect(is_file($workspace->path('.console/secrets/api.token.secret')))->toBeFalse();
    } finally {
        if (is_dir($root)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($root);
        }
    }
});

it('drives real commands through the public testing API', function (): void {
    $application = Application::configure()->commands([PromptCommand::class, ValidatedCommand::class, TableFixtureCommand::class])->build();
    $tester = new ApplicationTester($application);

    $prompt = $tester->command('prompt')
        ->answer('Name', ' Ada ')
        ->run()
        ->assertSuccessful()
        ->assertAsked('Name')
        ->assertOutputContains('Hello ada.');

    $valid = $tester->command('user:create')
        ->argument('email', 'ada@example.com')
        ->option('age', 30)
        ->run()
        ->assertValidationPassed()
        ->assertOutput('[OK] ada@example.com 30');

    $invalid = $tester->command('user:create')
        ->argument('email', 'invalid')
        ->option('age', 12)
        ->run()
        ->assertValidationFailed()
        ->assertErrorOutput("[ERROR] Invalid command input\n[ERROR] email: The Email must be a valid email address.\n[ERROR] age: The Age must be at least 18.");

    $table = $tester->command('table:show')->run()->assertSuccessful()->assertTable(['Name', 'State'], [['Console', 'ready']]);
    expect([$prompt, $valid, $invalid, $table])->toHaveCount(4)
        ->and((new CommandResult(0, ['{"ok":true}'], []))->assertJson(['ok' => true]))->toBeInstanceOf(CommandResult::class);
});

it('provides deterministic terminal, frame, signal, clock, and subprocess fixtures', function (): void {
    $terminal = FakeTerminal::redirected(120, 40);
    $capabilities = new FakeCapabilityLoader;
    $clock = new FakeClock(100);
    $signals = new FakeSignalManager;
    $interrupted = false;
    $signals->onInterrupt(function () use (&$interrupted): void {
        $interrupted = true;
    });
    $signals->interrupt();
    $clock->advance(20);

    $snapshot = FrameSnapshot::capture(Frame::line('Ready', 'success'));
    $snapshot->assertMatches(Frame::line('Ready', 'success'));
    $process = (new SubprocessRunner)->run([PHP_BINARY, '-r', 'fwrite(STDERR, "warning"); echo getenv("CONSOLE_TEST_VALUE");'], ['CONSOLE_TEST_VALUE' => 'ok']);
    $capabilities->configurer(Capability::NETWORK)(new Container('testing.capability'));

    expect($terminal->capabilities()->interactive)->toBeFalse()
        ->and($terminal->capabilities()->width)->toBe(120)
        ->and($clock->now())->toBe(120)
        ->and($interrupted)->toBeTrue()
        ->and($snapshot->contents())->toBe('[OK] Ready'.PHP_EOL)
        ->and($process->exitCode)->toBe(0)
        ->and($process->output)->toBe('ok')
        ->and($process->errorOutput)->toBe('warning')
        ->and($capabilities->wasLoaded(Capability::NETWORK))->toBeTrue();
});

it('hardens global options, JSON errors, and unexpected failure output', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()->name('tool')->version('1.0.0')->commands([ThrowingFixtureCommand::class])->io($io)->build();
    expect($application->run(['tool', '--profile', 'production', '--version']))->toBe(0)
        ->and($io->output())->toBe(['tool 1.0.0'])
        ->and($application->run(['tool', '--width=19', '--version']))->toBe(ExitCode::INVALID_USAGE)
        ->and($io->errors())->toContain('[ERROR] Terminal width must be an integer of at least 20.')
        ->and($application->run(['tool', 'throwing']))->toBe(ExitCode::FAILURE)
        ->and($io->errors())->toContain('[ERROR] An unexpected error occurred.');

    $output = fopen('php://temp', 'w+');
    $errors = fopen('php://temp', 'w+');
    $jsonIo = new ConsoleIO($output, $errors, new TerminalCapabilities(false, false, false, ColorDepth::NONE, 80, 24));
    $jsonApplication = Application::configure()->io($jsonIo)->build();
    expect($jsonApplication->run(['tool', '--format=json', 'missing']))->toBe(ExitCode::COMMAND_NOT_FOUND);
    rewind($errors);
    expect(stream_get_contents($errors))->toBe('{"type":"error","message":"Command \\"missing\\" was not found."}'.PHP_EOL);
});

it('keeps subprocess environments, validates workspace roots, and exposes safe terminal fallbacks', function (): void {
    $process = (new SubprocessRunner)->run([PHP_BINARY, '-r', 'echo getenv("PATH") === false ? "missing" : "present";'], ['CONSOLE_TEST_VALUE' => 'ok']);
    $capabilities = (new CapabilityDetector)->detect(fopen('php://temp', 'r'), fopen('php://temp', 'w'), ['LANG' => 'C', 'COLUMNS' => '120', 'LINES' => '40']);
    $signals = new SignalManager;

    expect($process->output)->toBe('present')
        ->and($capabilities->unicode)->toBeFalse()
        ->and($capabilities->width)->toBe(120)
        ->and($capabilities->height)->toBe(40)
        ->and($signals->register())->toBeBool();
    expect(fn (): Workspace => new Workspace(''))->toThrow(InvalidArgumentException::class);
});

it('runs argv processes with streamed redaction and timeout protection', function (): void {
    $streamed = [];
    $result = (new ProcessRunner)->run(
        [PHP_BINARY, '-r', 'fwrite(STDERR, "error SECRET"); echo getenv("CONSOLE_VALUE")." SECRET";'],
        new ProcessOptions(workingDirectory: sys_get_temp_dir(), environment: ['CONSOLE_VALUE' => 'ready'], sensitiveValues: ['SECRET'], onOutput: function (string $chunk) use (&$streamed): void {
            $streamed[] = $chunk;
        }),
    );
    $timeout = (new ProcessRunner)->run([PHP_BINARY, '-r', 'usleep(500000);'], new ProcessOptions(timeoutSeconds: 0.05));

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe('ready [REDACTED]')
        ->and($result->errorOutput)->toBe('error [REDACTED]')
        ->and(implode('', $streamed))->toBe('ready [REDACTED]')
        ->and($timeout->timedOut)->toBeTrue()
        ->and($timeout->successful())->toBeFalse();
});

it('redacts secrets split between process chunks and supports stream-only mode', function (): void {
    $chunks = [];
    $result = (new ProcessRunner)->run(
        [PHP_BINARY, '-r', 'fwrite(STDOUT, "SE"); usleep(1000); fwrite(STDOUT, "CRET");'],
        new ProcessOptions(
            mode: ProcessMode::STREAM,
            sensitiveValues: ['SECRET'],
            onOutput: function (string $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            },
        ),
    );

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe('')
        ->and(implode('', $chunks))->toBe('[REDACTED]');
});

it('runs due schedules with callbacks, state recording, cron matching, and overlap locks', function (): void {
    $schedule = new Schedule;
    $success = false;
    $schedule->command('users:sync')->hourly()->withoutOverlap()->onSuccess(function (ScheduleRun $run) use (&$success): void {
        $success = $run->successful();
    });
    $schedule->command('nightly:cleanup')->dailyAt('02:30');
    $state = new class implements ScheduleStateRepository
    {
        /** @var list<ScheduleRun> */
        public array $runs = [];

        public function record(ScheduleRun $run): void
        {
            $this->runs[] = $run;
        }
    };
    $locks = new class implements LockProviderInterface
    {
        public int $acquired = 0;

        public function acquire(string $key, float $waitSeconds): ?LockHandle
        {
            unset($waitSeconds);
            $this->acquired++;

            return new LockHandle($key, 'token');
        }

        public function release(?LockHandle $handle): void {}
    };
    $runner = new ScheduleRunner(new CommandMutex($locks), $state);
    $runs = $runner->runDue($schedule, static fn (string $command): int => $command === 'users:sync' ? 0 : 1, new DateTimeImmutable('2026-01-01 10:00:00 UTC'));

    expect($runs)->toHaveCount(1)
        ->and($runs[0]->command)->toBe('users:sync')
        ->and($success)->toBeTrue()
        ->and($state->runs)->toHaveCount(1)
        ->and($locks->acquired)->toBe(1);
});

it('applies named configuration profiles and supports schedule manifests and single-server locks', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()->configuration(['name' => 'base'])->profile('production', ['name' => 'production'])->commands([ConfigurationCommand::class])->io($io)->build();
    expect($application->run(['tool', '--profile=production', 'config:show']))->toBe(ExitCode::SUCCESS)
        ->and($io->outputText())->toContain('production');

    $schedule = new Schedule;
    $schedule->command('sync')->hourly()->onOneServer();
    $path = tempnam(sys_get_temp_dir(), 'schedule-');
    (new ScheduleManifestCompiler)->write($schedule, $path);
    $loaded = ScheduleManifest::load($path);
    expect($loaded->entries()[0]->requiresSingleServer())->toBeTrue();
    unlink($path);
});

it('requires a compiled command manifest for explicitly production applications', function (): void {
    expect(fn (): Application => Application::configure()->production()->build())->toThrow(LogicException::class);
});

it('enforces authorization, supports keyboard selection, and forwards process input', function (): void {
    $io = new BufferedIO;
    $application = Application::configure()->commands([ThrowingFixtureCommand::class])->authorizationPolicy(new class implements CommandAuthorizationPolicy
    {
        public function authorize(CommandDescriptor $command, CommandContext $context): bool
        {
            unset($command, $context);

            return false;
        }
    })->io($io)->build();
    $prompts = new PromptManager(new AnswerQueue(['down', '']), static function (): void {});
    $input = (new ProcessRunner)->run([PHP_BINARY, '-r', 'echo stream_get_contents(STDIN);'], new ProcessOptions(input: 'forwarded'));

    expect($application->run(['tool', 'throwing']))->toBe(ExitCode::FAILURE)
        ->and($io->errorText())->toContain('not authorized')
        ->and($prompts->select('Choose', ['one' => 'One', 'two' => 'Two']))->toBe('two')
        ->and($input->output)->toBe('forwarded');
});

it('loads compiled validation metadata and emits shell completion without command execution', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'console-manifest-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $validation = $directory.DIRECTORY_SEPARATOR.'validation.php';
    $completion = $directory.DIRECTORY_SEPARATOR.'completion.php';
    $descriptors = [CommandDescriptor::fromClass(ValidatedCommand::class)];
    (new ValidationManifestCompiler)->write($descriptors, $validation);
    (new CompletionManifestCompiler)->write($descriptors, $completion);
    $manifest = require $validation;
    $manifest['user:create']['email']['rules'] = ['max:3'];
    file_put_contents($validation, '<?php return '.var_export($manifest, true).';');

    $io = new BufferedIO;
    $application = Application::configure()->commands([ValidatedCommand::class])->validationManifest($validation)->completionManifest($completion)->io($io)->build();
    expect($application->run(['tool', 'user:create', 'four', '--age=20']))->toBe(ExitCode::INVALID_USAGE)
        ->and($io->errorText())->toContain('email:')
        ->and($application->run(['tool', 'completion', 'fish']))->toBe(ExitCode::SUCCESS)
        ->and($io->outputText())->toContain('complete -c console -f -a');

    unlink($validation);
    unlink($completion);
    rmdir($directory);
});

it('loads a validation manifest only when a real command is dispatched', function (): void {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'console-lazy-validation-'.bin2hex(random_bytes(4)).'.php';
    file_put_contents(
        $path,
        '<?php $GLOBALS["console_validation_manifest_loads"]++; return [];',
    );
    $GLOBALS['console_validation_manifest_loads'] = 0;

    try {
        $application = Application::configure()
            ->commands([NetworkCapabilityCommand::class])
            ->validationManifest($path)
            ->io(new BufferedIO)
            ->build();

        expect($GLOBALS['console_validation_manifest_loads'])->toBe(0)
            ->and($application->run(['tool', '--version']))->toBe(ExitCode::SUCCESS)
            ->and($GLOBALS['console_validation_manifest_loads'])->toBe(0)
            ->and($application->run(['tool', 'remote:check']))->toBe(ExitCode::SUCCESS)
            ->and($GLOBALS['console_validation_manifest_loads'])->toBe(1);
    } finally {
        unlink($path);
        unset($GLOBALS['console_validation_manifest_loads']);
    }
});

it('renders custom themes, semantic components, prompt filtering, and actionable suggestions', function (): void {
    $theme = new class implements Theme
    {
        public function style(string $role): Style
        {
            return $role === 'info' ? new Style(Color::BLUE, true) : new Style;
        }
    };
    $ansi = (new AnsiRenderer($theme))->render(Frame::line('Themed', 'info'));
    $prompt = new PromptManager(new AnswerQueue([]), static function (): void {});
    $io = new BufferedIO;
    $liveIo = new BufferedIO;
    $application = Application::configure()->commands([ValidatedCommand::class])->io($io)->build();
    $liveIo->progress(100, 'Upload')->advance();

    expect($ansi)->toContain("\033[1;34m[INFO] Themed")
        ->and((new Paragraph('one two three'))->frame(5)->lines)->toHaveCount(3)
        ->and((new HorizontalRule('-'))->frame(4)->lines[0]->text)->toBe('----')
        ->and((new Details(['Name' => 'Console']))->frame()->lines[0]->text)->toContain('Name')
        ->and($prompt->matchingOptions(['one' => 'First', 'two' => 'Second'], 'sec'))->toBe(['two' => 'Second'])
        ->and($liveIo->outputText())->toContain('Upload')
        ->and($application->run(['tool', 'user:creat']))->toBe(ExitCode::COMMAND_NOT_FOUND)
        ->and($io->outputText())->toContain('Did you mean user:create?')
        ->and($application->run(['tool', 'user:create', 'a@b.c', '--ag=20']))->toBe(ExitCode::INVALID_USAGE)
        ->and($io->errorText())->toContain('Did you mean "--age"?');
});
