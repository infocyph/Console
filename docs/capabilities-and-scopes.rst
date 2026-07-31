Capabilities, scopes, and infrastructure
========================================

Declaring capabilities
----------------------

A command declares only the infrastructure domains it needs:

.. code-block:: php

   use Infocyph\Console\Command\Capability;

   $command
       ->name('release:publish')
       ->capabilities([
           Capability::FILESYSTEM,
           Capability::IDENTITY,
           Capability::NETWORK,
       ])
       ->requiresOtp();

Configure each capability at the application composition root:

.. code-block:: php

   use Infocyph\InterMix\DI\Container;

   $application = Application::configure()
       ->configureCapability(
           Capability::NETWORK,
           static function (Container $container) use ($remote): void {
               $container->definitions()->bind(RemoteClient::class, $remote);
           },
       )
       ->build();

Configuration closures are retained as metadata and applied only when a
selected command declares the matching capability. Repeated executions reuse
immutable container wiring without rerunning the same capability configurer.

Command scopes
--------------

Every command receives a fresh InterMix scope containing:

* ``CommandContext`` and its execution identifier;
* parsed arguments and options;
* the active ``IO`` implementation;
* the selected configuration profile;
* the resolved command object and declared capabilities.

The scope is left in a ``finally`` path, including validation, authorization,
prompt, handler, and unexpected failures.

Optional adapters
-----------------

CacheLayer-backed mutexes and command state, DBLayer history and schedule
state, Epicrypt secret and signature services, OTP authorization, Pathwise
workspaces, ReqShield validation, and TalkingBytes communication remain
optional. Console accepts configured instances and never discovers or connects
to their backing resources automatically.

Authentication and authorization
--------------------------------

``CommandAuthorizationPolicy`` receives the selected descriptor and command
context before the handler runs. OTP is an additional declared control, not a
replacement for authorization. Denied commands do not execute their handler.
