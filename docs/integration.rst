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

Framework composition
---------------------

Frameworks should supply their existing boundaries:

.. code-block:: php

   $application = Application::configure()
       ->containerProvider($frameworkContainerProvider)
       ->configurationProvider($frameworkConfigurationProvider)
       ->commands($commands)
       ->commandGroup('System', ...$systemCommandNames)
       ->build();

``ContainerProvider::container()`` is called only for real command dispatch.
Console applies its bindings once per returned container and never creates a
second application graph.

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
