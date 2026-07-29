# Commands and input

## Command definition

A command class defines immutable metadata in `define()` and performs work in
`handle()`. Constructor dependencies are resolved only after the command route
has been selected.

```php
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Input\Argument;
use Infocyph\Console\Input\Option;
use Infocyph\Console\Input\ValueType;

final class UserCreateCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command
            ->name('user:create')
            ->description('Create a user.')
            ->argument(
                Argument::required('email')
                    ->sanitize(['trim', 'lowercase'])
                    ->rules(['email', 'max:255']),
            )
            ->option(
                Option::value('age')
                    ->shortcut('a')
                    ->type(ValueType::INTEGER)
                    ->rules(['min:18']),
            )
            ->option(Option::flag('force')->shortcut('f'));
    }

    protected function handle(): int
    {
        $email = $this->arguments()->string('email');
        $age = $this->options()->nullableInt('age');
        $force = $this->options()->bool('force');

        // Application work.

        return ExitCode::SUCCESS;
    }
}
```

Command names and aliases use lowercase colon-separated segments, such as
`user:create`. A variadic argument must be last, and required arguments cannot
follow optional arguments. Argument and option names cannot collide.

## Registration and grouping

Register an ordered class list or an explicit route-to-class map:

```php
$application = Application::configure()
    ->commands([
        UserCreateCommand::class,
        'system:health' => HealthCommand::class,
    ])
    ->commandGroup('System', 'system:health')
    ->build();
```

The explicit map key is authoritative. Lists group commands by the first route
segment; commands without a segment appear under `Application`. Explicit
groups change list presentation without renaming routes.

## Parsing and validation

Console parses argv without shell evaluation. Pass each token separately when
calling the application or scheduling a command. The `--` delimiter preserves
all following tokens as command input.

Sanitization and semantic validation happen before command construction.
ReqShield is loaded only when validation metadata requires it. Validation
failure returns an invalid-usage exit code and renders all known field errors;
JSON output emits a structured error representation.

## Preflight lifecycle

Version, list, help, and completion operate from descriptors/manifests. They do
not:

- construct the selected command;
- create the framework container;
- load command capabilities;
- open database, cache, or network connections;
- include the validation manifest.

A real dispatch resolves one command, enters one InterMix command scope, seeds
the parsed input/IO/execution context, runs the command, and leaves the scope in
both success and failure paths.
