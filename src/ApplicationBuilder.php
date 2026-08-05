<?php

declare(strict_types=1);

namespace Infocyph\Console;

use Infocyph\CacheLayer\Cache\Lock\LockProviderInterface;
use Infocyph\Console\Cache\CommandMutex;
use Infocyph\Console\Command\Capability;
use Infocyph\Console\Command\CommandContract;
use Infocyph\Console\Command\CommandExecutionCoordinator;
use Infocyph\Console\Command\CommandRegistry;
use Infocyph\Console\Command\CommandResolver;
use Infocyph\Console\Command\CommandResolverProvider;
use Infocyph\Console\Configuration\Configuration;
use Infocyph\Console\Configuration\ConfigurationLoader;
use Infocyph\Console\Configuration\ConfigurationProvider;
use Infocyph\Console\Configuration\ConfigurationRepository;
use Infocyph\Console\Container\ContainerConfigurator;
use Infocyph\Console\Container\ContainerFactory;
use Infocyph\Console\Container\ContainerProvider;
use Infocyph\Console\Discovery\CommandDiscoverer;
use Infocyph\Console\Discovery\CommandManifest;
use Infocyph\Console\Identity\ExecutionIdGenerator;
use Infocyph\Console\Infrastructure\CapabilityLoader;
use Infocyph\Console\IO\ConsoleIO;
use Infocyph\Console\IO\IO;
use Infocyph\Console\Omnibus\ConsumeCommand as OmnibusConsumeCommand;
use Infocyph\Console\Omnibus\DispatchScheduledMessageCommand;
use Infocyph\Console\Otp\CommandOtpAuthorizer;
use Infocyph\Console\Otp\OtpVerifier;
use Infocyph\Console\Process\ProcessRunner;
use Infocyph\Console\Security\CommandAuthorizationPolicy;
use Infocyph\Console\Style\Theme;
use Infocyph\Console\Validation\DBLayerDatabaseProvider;
use Infocyph\Console\Validation\InputValidator;
use Infocyph\DBLayer\Connection\Connection;
use Infocyph\InterMix\DI\Container;
use Infocyph\InterMix\DI\Support\LifetimeEnum;
use Infocyph\InterMix\DI\Support\ServiceProviderInterface;
use Infocyph\ReqShield\Contracts\DatabaseProvider;

final class ApplicationBuilder
{
    private readonly ContainerConfigurator $container;

    private ?CommandAuthorizationPolicy $authorizationPolicy = null;

    /** @var array<string, list<\Closure(Container): void>> */
    private array $capabilityConfigurers = [];

    /** @var array<string, string> */
    private array $commandGroups = [];

    private ?string $commandManifest = null;

    /** @var array<array-key, class-string<CommandContract>> */
    private array $commands = [];

    private ?string $completionManifest = null;

    /** @var list<string> */
    private array $configurationFiles = [];

    /** @var list<array<array-key,mixed>> */
    private array $configurationLayers = [];

    /** @var array<string,array<array-key,mixed>> */
    private array $configurationProfiles = [];

    /** @var array<string,mixed> */
    private array $configurationRules = [];

    /** @var array<string, array<int, (callable(): mixed)|string>|(callable(): mixed)|string> */
    private array $configurationSanitizers = [];

    private ?string $executable = null;

    private ?ExecutionIdGenerator $executionIds = null;

    private ?ConfigurationProvider $externalConfiguration = null;

    private ?IO $io = null;

    /** @var null|\Closure(): LockProviderInterface */
    private ?\Closure $locks = null;

    private string $name = 'console';

    private ?OtpVerifier $otpVerifier = null;

    private bool $production = false;

    private bool $strictConfiguration = false;

    private ?Theme $theme = null;

    private ?DatabaseProvider $validationDatabase = null;

    private ?string $validationManifest = null;

    private string $version = 'dev';

    public function __construct()
    {
        $this->container = new ContainerConfigurator();
    }

    public function authorizationPolicy(CommandAuthorizationPolicy $policy): self
    {
        $this->authorizationPolicy = $policy;

        return $this;
    }

    public function build(): Application
    {
        if ($this->production && $this->commandManifest === null) {
            throw new \LogicException('Production applications require a compiled command manifest.');
        }
        if ($this->externalConfiguration !== null && (
            $this->configurationFiles !== []
            || $this->configurationLayers !== []
            || $this->configurationProfiles !== []
            || $this->configurationRules !== []
            || $this->configurationSanitizers !== []
            || $this->strictConfiguration
        )) {
            throw new \LogicException('An external configuration provider cannot be combined with Console configuration sources or validation.');
        }
        $io = $this->io ?? ConsoleIO::standard();
        $io->setTheme($this->theme);
        $container = $this->container;
        $externalConfiguration = $this->externalConfiguration;
        $configurationLayers = $this->configurationLayers;
        $configurationFiles = $this->configurationFiles;
        $configurationRules = $this->configurationRules;
        $configurationSanitizers = $this->configurationSanitizers;
        $strictConfiguration = $this->strictConfiguration;
        $configurationProfiles = $this->configurationProfiles;
        $validationDatabase = $this->validationDatabase;
        $validationManifest = $this->validationManifest;
        $capabilityConfigurers = $this->capabilityConfigurers;
        $executionIds = $this->executionIds;
        $locks = $this->locks;
        $otpVerifier = $this->otpVerifier;
        $authorizationPolicy = $this->authorizationPolicy;

        return new Application(
            new ApplicationMetadata($this->name, $this->version),
            $this->commandManifest === null ? new CommandRegistry($this->commands) : CommandManifest::registry($this->commandManifest),
            $this->commandGroups,
            new CommandResolverProvider(
                static function () use (
                    $authorizationPolicy,
                    $capabilityConfigurers,
                    $configurationFiles,
                    $configurationLayers,
                    $configurationProfiles,
                    $configurationRules,
                    $configurationSanitizers,
                    $container,
                    $executionIds,
                    $externalConfiguration,
                    $otpVerifier,
                    $strictConfiguration,
                    $validationDatabase,
                    $validationManifest,
                ): CommandResolver {
                    $configuration = $externalConfiguration ?? new ConfigurationRepository(
                        new ConfigurationLoader(),
                        $configurationLayers,
                        $configurationFiles,
                        $configurationRules,
                        $configurationSanitizers,
                        $strictConfiguration,
                        $configurationProfiles,
                    );
                    $container->configure(static function (Container $container) use ($configuration): void {
                        $container->definitions()->bind(ConfigurationProvider::class, $configuration);
                        $container->bindFactory(
                            Configuration::class,
                            static fn(): Configuration => $configuration->configuration(),
                            LifetimeEnum::Scoped,
                        );
                    });

                    return new CommandResolver(
                        new ContainerFactory(),
                        $container,
                        new InputValidator($validationDatabase, $validationManifest),
                        new CapabilityLoader($capabilityConfigurers, $executionIds),
                        new CommandOtpAuthorizer($otpVerifier),
                        $configuration,
                        $authorizationPolicy,
                    );
                },
            ),
            $io,
            $this->completionManifest,
            new CommandExecutionCoordinator(
                new ProcessRunner(),
                $locks === null
                    ? null
                    : static fn(): CommandMutex => new CommandMutex($locks()),
                $this->executable,
            ),
        );
    }

    /** @param class-string<CommandContract> $command */
    public function command(string $command): self
    {
        $this->commands[] = $command;

        return $this;
    }

    /**
     * Assign commands to a top-level group or a two-level Group/Subgroup path.
     *
     * @param string $group Display path used by the command list.
     * @param string ...$commands Registered command names owned by the path.
     */
    public function commandGroup(string $group, string ...$commands): self
    {
        $group = trim($group);
        if ($group === '') {
            throw new \InvalidArgumentException('A command group name cannot be empty.');
        }

        $segments = array_map(trim(...), explode('/', $group));
        if (count($segments) > 2 || in_array('', $segments, true)) {
            throw new \InvalidArgumentException(
                'A command group must be a non-empty name or a two-level "Group/Subgroup" path.',
            );
        }
        $group = implode('/', $segments);

        foreach ($commands as $command) {
            if ($command === '') {
                throw new \InvalidArgumentException('A grouped command name must be a non-empty string.');
            }
            if (isset($this->commandGroups[$command]) && $this->commandGroups[$command] !== $group) {
                throw new \InvalidArgumentException(sprintf(
                    'Command "%s" is already assigned to group "%s".',
                    $command,
                    $this->commandGroups[$command],
                ));
            }

            $this->commandGroups[$command] = $group;
        }

        return $this;
    }

    public function commandManifest(string $path): self
    {
        $this->commandManifest = $path;

        return $this;
    }

    /** @param array<array-key, class-string<CommandContract>> $commands */
    public function commands(array $commands): self
    {
        $this->commands = $commands;

        return $this;
    }

    public function compiledContainer(string $path): self
    {
        $this->container->compiledContainer($path);

        return $this;
    }

    public function completionManifest(string $path): self
    {
        $this->completionManifest = $path;

        return $this;
    }

    /** @param array<array-key,mixed> $configuration */
    public function configuration(array $configuration): self
    {
        $this->configurationLayers[] = $configuration;

        return $this;
    }

    public function configurationFile(string $path): self
    {
        $this->configurationFiles[] = $path;

        return $this;
    }

    public function configurationProvider(ConfigurationProvider $provider): self
    {
        $this->externalConfiguration = $provider;

        return $this;
    }

    /**
     * @param \Closure $configurer Capability-scoped container configuration.
     * @phpstan-param \Closure(Container): void $configurer
     * @psalm-param \Closure(Container): void $configurer
     */
    public function configureCapability(Capability $capability, \Closure $configurer): self
    {
        $this->capabilityConfigurers[$capability->value] ??= [];
        $this->capabilityConfigurers[$capability->value][] = $configurer;

        return $this;
    }

    /**
     * @param \Closure $configurer Application container configuration.
     * @phpstan-param \Closure(Container): void $configurer
     * @psalm-param \Closure(Container): void $configurer
     */
    public function configureContainer(\Closure $configurer): self
    {
        $this->container->configure($configurer);

        return $this;
    }

    public function containerProvider(ContainerProvider $provider): self
    {
        $this->container->useContainerProvider($provider);

        return $this;
    }

    public function dbLayerValidation(Connection $connection): self
    {
        return $this->validationDatabaseProvider(new DBLayerDatabaseProvider($connection));
    }

    /** @param list<string> $paths */
    public function discoverCommands(array $paths): self
    {
        $this->commands = [...$this->commands, ...new CommandDiscoverer()->discover($paths)->commands];

        return $this;
    }

    public function executable(string $path): self
    {
        if ($path === '') {
            throw new \InvalidArgumentException('The console executable path cannot be empty.');
        }
        $this->executable = $path;

        return $this;
    }

    public function executionIdGenerator(ExecutionIdGenerator $generator): self
    {
        $this->executionIds = $generator;

        return $this;
    }

    public function io(IO $io): self
    {
        $this->io = $io;

        return $this;
    }

    public function lockProvider(LockProviderInterface $provider): self
    {
        $this->locks = static fn(): LockProviderInterface => $provider;

        return $this;
    }

    /**
     * @param \Closure $provider Lazy lock-provider factory.
     * @phpstan-param \Closure(): LockProviderInterface $provider
     * @psalm-param \Closure(): LockProviderInterface $provider
     */
    public function lockProviderFactory(\Closure $provider): self
    {
        $this->locks = $provider;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function omnibus(): self
    {
        foreach ([OmnibusConsumeCommand::class, DispatchScheduledMessageCommand::class] as $command) {
            if (!in_array($command, $this->commands, true)) {
                $this->commands[] = $command;
            }
        }

        return $this->commandGroup(
            'Messaging',
            OmnibusConsumeCommand::NAME,
            DispatchScheduledMessageCommand::NAME,
        );
    }

    public function otpVerifier(OtpVerifier $verifier): self
    {
        $this->otpVerifier = $verifier;

        return $this;
    }

    /**
     * Use an InterMix artifact validated as part of the same immutable deployment.
     *
     * @param string $path Trusted application-owned artifact path.
     * @param string $fingerprint SHA-256 fingerprint from the deployment manifest.
     */
    public function prevalidatedCompiledContainer(string $path, string $fingerprint): self
    {
        $this->container->compiledContainer($path, $fingerprint);

        return $this;
    }

    public function production(bool $enabled = true): self
    {
        $this->production = $enabled;

        return $this;
    }

    /** @param array<array-key,mixed> $configuration */
    public function profile(string $name, array $configuration): self
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Configuration profile names cannot be empty.');
        }
        $this->configurationProfiles[$name] = $configuration;

        return $this;
    }

    /** @param class-string<ServiceProviderInterface>|ServiceProviderInterface $provider */
    public function provider(string|ServiceProviderInterface $provider): self
    {
        $this->container->provider($provider);

        return $this;
    }

    public function theme(Theme $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<string, array<int, (callable(): mixed)|string>|(callable(): mixed)|string> $sanitizers
     */
    public function validateConfiguration(array $rules, array $sanitizers = [], bool $strict = false): self
    {
        $this->configurationRules = $rules;
        $this->configurationSanitizers = $sanitizers;
        $this->strictConfiguration = $strict;

        return $this;
    }

    public function validationDatabaseProvider(DatabaseProvider $provider): self
    {
        $this->validationDatabase = $provider;

        return $this;
    }

    public function validationManifest(string $path): self
    {
        $this->validationManifest = $path;

        return $this;
    }

    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }
}
