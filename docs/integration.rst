Framework and standalone integration
====================================

Standalone composition
----------------------

Standalone applications may let Console lazily create one InterMix container:

.. code-block:: php

   $application = Application::configure()
       ->commands($commands)
       ->configurationFile(__DIR__ . '/config/console.php')
       ->configureContainer(
           static function (Container $container) use ($mailer): void {
               $container->definitions()->bind(Mailer::class, $mailer);
           },
       )
       ->build();

The container wiring is reused; each command still receives a fresh scope.
Service providers are imported once into that standalone container, even when
the application executes several commands in one long-lived process.

Framework composition
---------------------

Frameworks should supply their existing boundaries:

.. code-block:: php

   $application = Application::configure()
       ->containerProvider($frameworkContainerProvider)
       ->configurationProvider($frameworkConfigurationProvider)
       ->commands($commands)
       ->commandGroup('System/Database', ...$databaseCommandNames)
       ->commandGroup('System/Routing', ...$routingCommandNames)
       ->build();

The first path segment is the owning section and the optional second segment
is its module heading. Application commands without explicit ownership remain
grouped by their ``namespace:command`` prefix.

``ContainerProvider::container()`` is called only for real command dispatch.
Console applies its bindings once per returned container and never creates a
second application graph.

Compiled InterMix containers
----------------------------

``compiledContainer($path)`` uses InterMix's fully validated loader. This is
the safe default for standalone applications and mutable deployments.

An immutable deployment may validate the artifact during optimization and
publish the returned compilation fingerprint in the same deployment manifest.
That deployment can avoid repeating full identity validation at process boot:

.. code-block:: php

   $application = Application::configure()
       ->prevalidatedCompiledContainer(
           __DIR__ . '/bootstrap/cache/container.php',
           $optimizeManifest['container_fingerprint'],
       )
       ->build();

The path and fingerprint must be trusted deployment outputs, never request or
command input. Rebuild both after changing PHP, InterMix, providers, container
options, definitions, or application code. Use the prevalidated path only when
the artifact is warmed by OPcache/preloading or loaded once by a persistent
worker; otherwise the ordinary dynamic container can be faster for small
commands.

Command discovery
-----------------

``discoverCommands()`` is a development/build-time convenience using explicit
directories and reflection. Do not call it on production dispatch paths.
Compile the resulting class map into the command manifest during optimize.

Dependency boundaries
---------------------

The production runtime requires ArrayKit, InterMix, Omnibus, and UID. Optional
adapters are declared through Composer ``suggest`` and remain development
dependencies in Console itself. An application installs only the providers it
actually composes.

Console accepts abstractions at genuine integration boundaries: InterMix
containers, CacheLayer locks, DBLayer connections, TalkingBytes communication,
and Omnibus consumers. It does not add wrapper packages or duplicate provider
configuration.
