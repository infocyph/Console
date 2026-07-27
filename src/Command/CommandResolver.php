<?php

declare(strict_types=1);

namespace Infocyph\Console\Command;

use Infocyph\Console\Configuration\ConfigurationProvider;
use Infocyph\Console\Container\ContainerConfigurator;
use Infocyph\Console\Container\ContainerFactory;
use Infocyph\Console\Exception\AuthorizationDeniedException;
use Infocyph\Console\Identity\CommandExecution;
use Infocyph\Console\Infrastructure\CapabilityLoader;
use Infocyph\Console\Input\ArgumentCollection;
use Infocyph\Console\Input\OptionCollection;
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
        private ContainerConfigurator $containerConfiguration,
        private InputValidator $validator,
        private CapabilityLoader $capabilities,
        private CommandOtpAuthorizer $otp,
        private ConfigurationProvider $configuration,
        private ?CommandAuthorizationPolicy $authorizationPolicy = null,
    ) {}

    public function run(CommandDescriptor $descriptor, ParsedInput $input, IO $io): int
    {
        $input = $this->validator->validate($descriptor, $input);
        $container = $this->factory->create($this->containerConfiguration);
        $execution = $this->capabilities->load($container, $descriptor);
        $context = new CommandContext($input, $io, $execution);
        $instances = [
            CommandContext::class => $context,
            ParsedInput::class => $input,
            ArgumentCollection::class => $input->arguments(),
            OptionCollection::class => $input->options(),
            IO::class => $io,
        ];
        if ($execution !== null) {
            $instances[CommandExecution::class] = $execution;
        }
        $container->enterScope('command.' . spl_object_id($context), $instances);

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
            $container->leaveScope();
        }
    }

    public function useProfile(?string $profile): void
    {
        $this->configuration->useProfile($profile);
    }
}
