<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\Configuration\ConfigurationRepository;
use Infocyph\Console\Container\CommandScope;
use Infocyph\Console\Container\ContainerConfigurator;
use Infocyph\Console\Container\ContainerFactory;
use Infocyph\Console\Exception\AuthorizationDeniedException;
use Infocyph\Console\Infrastructure\CapabilityLoader;
use Infocyph\Console\Input\ParsedInput;
use Infocyph\Console\IO\IO;
use Infocyph\Console\Otp\CommandOtpAuthorizer;
use Infocyph\Console\Security\CommandAuthorizationPolicy;
use Infocyph\Console\Validation\InputValidator;

/**
 * @internal
 */
final readonly class CommandResolver
{
    public function __construct(
        private ContainerFactory $factory,
        private ContainerConfigurator $configuration,
        private InputValidator $validator,
        private CapabilityLoader $capabilities,
        private CommandOtpAuthorizer $otp,
        private ConfigurationRepository $configurationRepository,
        private ?CommandAuthorizationPolicy $authorizationPolicy = null,
    ) {}

    public function run(CommandDescriptor $descriptor, ParsedInput $input, IO $io): int
    {
        $input = $this->validator->validate($descriptor, $input);
        $container = $this->factory->create($this->configuration);
        $execution = $this->capabilities->load($container, $descriptor);
        $scope = new CommandScope($container, 'command.' . bin2hex(random_bytes(8)));
        $context = new CommandContext($input, $io, $execution);
        $scope->enter($context);

        try {
            if ($this->authorizationPolicy !== null && !$this->authorizationPolicy->authorize($descriptor, $context)) {
                throw new AuthorizationDeniedException(sprintf('You are not authorized to run "%s".', $descriptor->name()));
            }
            $this->otp->authorize($descriptor, $io);
            $command = $container->make($descriptor->class());
            if (!$command instanceof CommandContract) {
                throw new \LogicException(sprintf('%s is not a command.', $descriptor->class()));
            }

            return $command->run($context);
        } finally {
            $scope->leave();
        }
    }

    public function useProfile(?string $profile): void
    {
        $this->configurationRepository->useProfile($profile);
    }
}
