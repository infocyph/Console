# Capabilities and command scopes

Capabilities describe infrastructure required by a selected command:

- `cache`
- `configuration`
- `container`
- `cryptography`
- `database`
- `filesystem`
- `identity`
- `network`
- `otp`
- `process`
- `scheduler`
- `validation`

Declare only actual requirements:

```php
use Infocyph\Console\Command\Capability;

$command
    ->name('release:publish')
    ->capabilities([
        Capability::FILESYSTEM,
        Capability::NETWORK,
        Capability::IDENTITY,
    ])
    ->requiresOtp();
```

`requiresOtp()` adds the OTP capability automatically.

## Lazy capability configuration

```php
use Infocyph\Console\Command\Capability;
use Infocyph\Console\Communication\RemoteClient;
use Infocyph\InterMix\DI\Container;

$application = Application::configure()
    ->configureCapability(
        Capability::NETWORK,
        static function (Container $container) use ($httpClient): void {
            $container->definitions()->bind(
                RemoteClient::class,
                new RemoteClient($httpClient),
            );
        },
    )
    ->build();
```

Configurers for unselected capabilities are never executed. Registration
should bind caller-created clients; it should not perform eager network,
database, or filesystem I/O.

## Command scopes

Console uses one InterMix container graph and a fresh command scope per
execution. Parsed input, IO, configuration, identity, and command context are
scope seeds. Leaving the scope removes them even when command construction or
execution throws.

Frameworks may provide their existing container through `ContainerProvider`.
Console configures each returned container once and does not build a second
graph. `container()` is not called by version, help, list, or completion.

Persistent command hosts must not store command, tenant, principal, parsed
input, or secret state in static properties or singleton services. Put mutable
execution state in scoped bindings.
