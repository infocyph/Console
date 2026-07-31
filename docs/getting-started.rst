Getting started
===============

Requirements
------------

Console requires PHP 8.4 or newer. ``proc_open`` is required only for isolated
commands, process controls, and worker supervision. Interactive signals use
``pcntl`` when available and retain safe non-interactive fallbacks otherwise.

Install the package:

.. code-block:: console

   composer require infocyph/console

Minimal application
-------------------

.. code-block:: php

   use Infocyph\Console\Application;
   use Infocyph\Console\Command\Command;
   use Infocyph\Console\Command\CommandDefinition;
   use Infocyph\Console\Command\ExitCode;

   final class HelloCommand extends Command
   {
       public static function define(CommandDefinition $command): void
       {
           $command
               ->name('hello')
               ->description('Print a greeting.');
       }

       protected function handle(): int
       {
           $this->io()->success('Hello from Console.');

           return ExitCode::SUCCESS;
       }
   }

   $application = Application::configure()
       ->name('acme')
       ->version('1.0.0')
       ->commands([HelloCommand::class])
       ->build();

   exit($application->run());

Run it:

.. code-block:: console

   php bin/acme
   php bin/acme list
   php bin/acme help hello
   php bin/acme hello
   php bin/acme completion zsh

Global options
--------------

``--help`` and ``--version`` are preflight operations. ``--quiet`` suppresses
normal output while preserving warnings and errors. ``--no-interaction``
rejects prompts that do not declare safe defaults.

Use ``--ansi`` or ``--no-ansi``/``--no-color`` to override terminal detection,
``--format=json`` for JSON Lines, ``--width=120`` for deterministic layouts,
and ``--profile=production`` to select an application configuration profile.
``-v``, ``-vv``, and ``-vvv`` progressively expose failure diagnostics.

Production metadata
-------------------

Development may register command classes directly. Production applications
should compile command descriptors and point the application at the generated
index:

.. code-block:: php

   use Infocyph\Console\Discovery\CommandManifestCompiler;

   new CommandManifestCompiler()->write(
       [HelloCommand::class],
       __DIR__ . '/cache/commands.php',
   );

   $application = Application::configure()
       ->production()
       ->commandManifest(__DIR__ . '/cache/commands.php')
       ->build();

The index and descriptor shards are directly includable PHP. No directory scan,
reflection discovery, or descriptor execution occurs on production command
dispatch.
