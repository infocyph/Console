Commands, arguments, and options
================================

Definition and handler
----------------------

Command metadata belongs in the static ``define()`` method. Business execution
belongs in ``handle()``.

.. code-block:: php

   use Infocyph\Console\Command\Command;
   use Infocyph\Console\Command\CommandDefinition;
   use Infocyph\Console\Command\ExitCode;
   use Infocyph\Console\Input\Argument;
   use Infocyph\Console\Input\Option;
   use Infocyph\Console\Input\ValueType;

   final class UserInviteCommand extends Command
   {
       public static function define(CommandDefinition $command): void
       {
           $command
               ->name('user:invite')
               ->description('Invite a user to an account.')
               ->argument(
                   Argument::required('email')
                       ->description('Recipient email address.')
                       ->sanitize(['trim', 'lowercase'])
                       ->rules(['required', 'email']),
               )
               ->option(
                   Option::value('attempts')
                       ->short('a')
                       ->type(ValueType::INTEGER)
                       ->default(3)
                       ->description('Maximum delivery attempts.'),
               )
               ->option(
                   Option::flag('notify')
                       ->description('Send the invitation immediately.'),
               );
       }

       protected function handle(): int
       {
           $email = $this->arguments()->string('email');
           $attempts = $this->options()->int('attempts');
           $notify = $this->options()->bool('notify');

           $this->io()->success(sprintf(
               'Prepared %s with %d attempts%s.',
               $email,
               $attempts,
               $notify ? ' and immediate delivery' : '',
           ));

           return ExitCode::SUCCESS;
       }
   }

Input behavior
--------------

Arguments may be required, optional, or variadic. Options may be flags, scalar
values, repeatable values, or negatable booleans. ``ValueType`` converts input
to its declared scalar type at the argv boundary. Invalid values produce exit
code ``2``.

Environment fallbacks and defaults are resolved once by the parser. A supplied
CLI value wins over an environment fallback, which wins over the declared
default.

Names, aliases, and groups
--------------------------

Names follow ``namespace:command``. List output groups names by their first
segment. A framework can assign explicit ownership without renaming routes:

.. code-block:: php

   $application = Application::configure()
       ->commands($commands)
       ->commandGroup('System', 'config:cache', 'config:clear')
       ->build();

Unknown commands and options include bounded fuzzy suggestions. Hidden commands
remain dispatchable but are excluded from list and completion metadata.

Exit codes
----------

Use ``ExitCode::SUCCESS`` for completed work, ``INVALID_USAGE`` for caller
input, ``COMMAND_NOT_FOUND`` for unknown routes, ``INTERRUPTED`` for cancelled
execution, and ``FAILURE`` for operational or business failure. Exceptions that
implement ``ProvidesExitCode`` may expose a deliberate non-default code.
