Testing Console applications
============================

Application tester
------------------

.. code-block:: php

   use Infocyph\Console\Testing\ApplicationTester;

   it('invites a user', function (): void {
       $application = Application::configure()
           ->commands([UserInviteCommand::class])
           ->build();

       (new ApplicationTester($application))
           ->command('user:invite')
           ->argument('email', 'hasan@example.com')
           ->option('attempts', 3)
           ->option('notify')
           ->answer('Continue?', true)
           ->run()
           ->assertSuccessful()
           ->assertValidationPassed()
           ->assertOutputContains('hasan@example.com')
           ->assertAsked('Continue?');
   });

``CommandResult`` asserts exit codes, stdout, stderr, JSON values, validation
outcomes, and rendered tables. ``BufferedIO`` is useful when driving an
``Application`` directly.

Deterministic fixtures
----------------------

* ``FakeTerminal`` controls dimensions, interactivity, ANSI, Unicode, and color.
* ``FakeClock`` provides deterministic time.
* ``FakeKeyboard`` supplies navigation keys.
* ``FakeSignalManager`` triggers interrupts without operating-system signals.
* ``FakeCapabilityLoader`` records optional capability activation.
* ``FrameSnapshot`` compares semantic output independent of ANSI.
* ``SubprocessRunner`` exercises real process boundaries with captured streams.

Worker tests
------------

Worker tests should cover successful and failed children, incremental scaling,
scale-down, heartbeat loss, SIGINT/SIGTERM shutdown, grace escalation, crash
backoff, failure circuit opening, probe exceptions, exact accounting, and
memory stability.

Use deterministic fixture processes and bounded lifetimes. Never leave a test
child relying on the test-runner shutdown path.

Database matrix
---------------

DBLayer-backed validation, command-history, and schedule-state adapters run
against SQLite and every configured MySQL/PostgreSQL service. The PHPForge
workflow enables all three PDO drivers and both service databases for its PHP
8.4/8.5 and prefer-lowest/prefer-stable matrix.

Local runs always exercise SQLite. MySQL and PostgreSQL are added when the
shared ``IC_SERVICE_DATABASE``, ``IC_SERVICE_USERNAME``, and
``IC_SERVICE_PASSWORD`` variables are present. ``CONSOLE_MYSQL_*``,
``CONSOLE_PGSQL_*``, and the corresponding ``DBLAYER_*`` variables can override
the host, port, database, username, and password. A configured service that
cannot connect fails the suite instead of silently reducing coverage.

Package quality commands
------------------------

.. code-block:: console

   composer ic:process
   composer ic:tests:details
   composer ic:ci
   composer ic:release:guard
   composer benchmark
   composer soak:worker

The release guard validates Composer metadata, stable runtime constraints,
advisories, syntax, tests, style, public API, architecture, duplication, Psalm,
PHPStan, and Rector.
