Prompts and interaction
=======================

Prompt types
------------

``PromptManager`` provides text, confirmation, selection, multi-selection,
secret, and validated input. Commands access it through ``$this->io()->prompts()``.

.. code-block:: php

   $prompts = $this->io()->prompts();

   $name = $prompts->text(
       'Project name',
       default: 'acme',
       sanitize: ['trim', 'lowercase'],
       rules: ['required', 'string', 'min:3'],
   );

   $environment = $prompts->select('Environment', [
       'development' => 'Development',
       'staging' => 'Staging',
       'production' => 'Production',
   ]);

   if (!$prompts->confirm('Continue?', default: false)) {
       return ExitCode::INTERRUPTED;
   }

Interactive selection supports filtering and keyboard navigation on capable
terminals. Redirected streams use line input without raw terminal commands.

Non-interactive safety
----------------------

``--no-interaction`` disables interactive reads. A prompt may return an
explicit safe default; otherwise it fails rather than guessing. Destructive
operations should default to cancellation and require a deliberate affirmative
answer.

Secret input
------------

Secret prompts suppress terminal echo only when a safe platform implementation
is available. Raw mode is restored on normal completion, exceptions, and
shutdown. Applications must still avoid placing returned secrets in output,
exception messages, process argv, or logs.

Testing prompts
---------------

``BufferedIO`` and ``ApplicationTester`` accept queued answers:

.. code-block:: php

   (new ApplicationTester($application))
       ->command('project:create')
       ->answer('Project name', 'billing')
       ->answer('Environment', 'production')
       ->answer('Continue?', true)
       ->run()
       ->assertSuccessful()
       ->assertAsked('Continue?');
