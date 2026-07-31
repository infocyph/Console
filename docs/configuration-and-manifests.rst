Configuration and compiled manifests
====================================

Configuration sources
---------------------

Standalone applications may layer arrays and PHP configuration files:

.. code-block:: php

   $application = Application::configure()
       ->configuration([
           'api' => ['timeout' => 5.0],
           'workers' => ['maximum' => 4],
       ])
       ->configurationFile(__DIR__ . '/config/console.php')
       ->profile('production', [
           'workers' => ['maximum' => 16],
       ])
       ->validateConfiguration([
           'api.timeout' => ['required', 'numeric', 'min:0.1'],
           'workers.maximum' => ['required', 'integer', 'min:1'],
       ])
       ->build();

ArrayKit owns merge and retrieval behavior. Validation and sanitization occur
once when the command graph first requests configuration. Preflight operations
do not load configuration.

Framework integration should supply one ``ConfigurationProvider``. An external
provider cannot be combined with local layers, files, profiles, or validation,
because two precedence systems would be ambiguous.

Command manifests
-----------------

``CommandManifestCompiler`` produces a version-2 index and one deterministic
descriptor shard per command. Rebuilding prunes only stale shards sharing the
index prefix; unrelated cache files remain untouched.

.. code-block:: php

   $compiler = new CommandManifestCompiler();
   $compiler->write(
       [
           UserInviteCommand::class,
           'billing:reconcile' => ReconcileCommand::class,
       ],
       $cache . '/commands.php',
   );

The explicit map key is authoritative. It can assign a route without changing
the command class metadata.

Validation, completion, schedule, and configuration manifests
-------------------------------------------------------------

The dedicated compilers write directly includable PHP using a shared atomic
publisher:

.. code-block:: php

   (new ValidationManifestCompiler())->write($descriptors, $cache . '/validation.php');
   (new CompletionManifestCompiler())->write($descriptors, $cache . '/completion.php');
   (new ScheduleManifestCompiler())->write($schedule, $cache . '/schedule.php');
   (new ConfigurationCompiler())->compile($configuration, $cache . '/config.php');

Each writer creates a temporary file beside the target and renames it into
place. A reader therefore observes either the previous complete artifact or the
new complete artifact, never a partially written PHP file.

Deployment rules
----------------

Compile manifests during build or deployment, validate them before activation,
and deploy them with the same immutable release as the application code.
Rebuild the authoritative Composer classmap after adding command classes.
Console rejects obsolete command-manifest formats instead of doing compatibility
work on request paths.
