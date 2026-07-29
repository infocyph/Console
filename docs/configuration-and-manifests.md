# Configuration and compiled manifests

## Configuration sources

Standalone applications may add array layers and PHP configuration files:

```php
$application = Application::configure()
    ->configuration(['environment' => 'production'])
    ->configurationFile(__DIR__ . '/config/console.php')
    ->profile('production', ['debug' => false])
    ->validateConfiguration(
        ['environment' => ['required', 'string']],
        ['environment' => ['trim', 'lowercase']],
        strict: true,
    )
    ->build();
```

Configuration remains lazy until a selected command needs its scoped
`Configuration`. Profiles are selected by the application input. A framework
may instead provide `ConfigurationProvider`; external providers cannot be
combined with local layers, files, profiles, or local configuration validation,
because that would create ambiguous precedence.

## Production manifests

Compile stable metadata during build/deployment:

```php
use Infocyph\Console\Discovery\CommandManifestCompiler;

$commands = [
    'user:create' => UserCreateCommand::class,
];

(new CommandManifestCompiler)->write($commands, $cache . '/commands.php');
```

Wire the directly includable files:

```php
$application = Application::configure()
    ->production()
    ->commandManifest($cache . '/commands.php')
    ->build();
```

The command index stores lazy descriptor shard paths beside
`commands.php`. Dispatch includes only the selected descriptor. Console does
not create a secondary `commands.php.d` directory and does not scan command
folders on production requests.

`discoverCommands()` is an explicit development/build-time convenience. Do not
call it on a hot command path. Compile discovery results before deployment.

Framework integrations may additionally supply `validationManifest()`,
`completionManifest()`, and `compiledContainer()` artifacts produced by their
own optimize command. When those optional artifacts are omitted, Console
compiles only the selected command's validation at dispatch and builds
completion output from the command registry. Preflight version/help/list paths
still do not load validation infrastructure.

## Cache lifecycle

Applications and frameworks own cache paths and their optimize/clear commands.
Write new manifests to temporary files and replace targets atomically. Clear
only known Console artifacts; do not recursively remove a shared cache root.
