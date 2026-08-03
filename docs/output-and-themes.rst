Output, components, and themes
==============================

Adaptive rendering
------------------

Console renders one semantic ``Frame`` through one of three renderers:

* ``AnsiRenderer`` for interactive terminals and explicitly forced color;
* ``PlainRenderer`` for redirection, ``NO_COLOR``, and unsupported terminals;
* ``JsonRenderer`` for newline-delimited structured output.

The ANSI renderer uses the detected color depth. Basic terminals receive
16-color codes, ``TERM=*-256color`` receives indexed colors, and
``COLORTERM=truecolor`` receives 24-bit RGB values. ``--ansi`` on a redirected
stream safely selects the basic palette. ``FORCE_COLOR=1`` also enables color
for redirected streams while ``NO_COLOR`` always wins when that variable is
present, including when it has an empty value.

Default palette
---------------

The default semantic theme uses bright cyan for titles and accents, blue for
sections and information, green for success and progress, yellow for warnings,
red for errors, magenta for notes, and dim slate for borders or muted text.
Definition lists use bright-cyan labels, dim-slate separators, and white values
so command names and descriptions remain visually distinct. Styles are created
once per theme and reused across rendered lines.

Components
----------

.. code-block:: php

   $io->title('Deployment');
   $io->section('Preflight');
   $io->info('Reading release metadata.');
   $io->success('Configuration is valid.');
   $io->warning('Two workers still use the previous release.');
   $io->note('Traffic remains on the active release.');

   $io->definitions([
       'Release' => '2026.07.31',
       'Commit' => '4b6f1ab',
       'Region' => 'ap-south-1',
   ]);

   $io->table(
       ['Worker', 'State', 'Jobs'],
       [
           ['emails-1', 'running', 148],
           ['emails-2', 'draining', 11],
       ],
   );

   $io->tree([
       'deploy' => [
           'build' => 'ready',
           'release' => ['migrate' => 'ready', 'warm' => 'ready'],
       ],
   ]);

The component set includes boxes, paragraphs, definition lists, detail lists,
tables, trees, ordered and unordered lists, rules, status lines, progress bars,
spinners, tasks, and task groups. Width calculations use ``mb_strwidth`` when
available.

Custom themes
-------------

Implement ``Theme`` and return a ``Style`` for semantic roles:

.. code-block:: php

   use Infocyph\Console\Style\Color;
   use Infocyph\Console\Style\Style;
   use Infocyph\Console\Style\Theme;

   final class OceanTheme implements Theme
   {
       public function style(string $role): Style
       {
           return match ($role) {
               'title', 'accent' => new Style(
                   Color::BRIGHT_CYAN,
                   bold: true,
               ),
               'success' => new Style(Color::BRIGHT_GREEN, bold: true),
               'warning' => new Style(Color::BLACK, background: Color::BRIGHT_YELLOW),
               'error' => new Style(Color::BRIGHT_WHITE, background: Color::RED),
               'definition-label' => new Style(Color::BRIGHT_CYAN, bold: true),
               'definition-separator' => new Style(Color::BRIGHT_BLACK, dim: true),
               'definition-value' => new Style(Color::WHITE),
               'muted', 'border' => new Style(Color::BRIGHT_BLACK, dim: true),
               default => new Style(),
           };
       }
   }

   $application = Application::configure()
       ->theme(new OceanTheme())
       ->build();

``Style`` supports foreground and background colors plus bold, dim, italic,
and underline decorations. Unknown roles must return a safe default style.

Automation guarantees
---------------------

Plain and JSON output never contain ANSI escape sequences. JSON uses one object
per logical line with ``type`` and ``message`` fields. Errors go to stderr.
``--quiet`` suppresses normal frames but retains warnings, errors, validation
failures, and prompt safety.
