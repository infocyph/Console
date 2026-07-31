Console documentation
=====================

Console is a typed, framework-agnostic command runtime for PHP 8.4 and newer.
It provides fast preflight metadata, lazy command resolution, structured
terminal output, prompts, process controls, scheduling, dynamic worker
supervision, and opt-in Omnibus message commands.

Its visual layer is dependency-free: semantic frames render through a colorful
adaptive ANSI theme on capable terminals, plain text when redirected or
``NO_COLOR`` is set, and newline-delimited JSON for automation.

.. grid:: 1 2 2 3
   :gutter: 2

   .. grid-item-card:: Build commands
      :link: commands
      :link-type: doc

      Typed arguments, options, validation, capabilities, and command scopes.

   .. grid-item-card:: Design terminal output
      :link: output-and-themes
      :link-type: doc

      Semantic components, adaptive color, prompts, progress, and custom themes.

   .. grid-item-card:: Operate workers
      :link: workers
      :link-type: doc

      Dynamic scaling, crash-loop protection, graceful shutdown, and stop reasons.

.. toctree::
   :maxdepth: 2
   :caption: Guide

   getting-started
   recipes
   architecture
   commands
   output-and-themes
   prompts
   configuration-and-manifests
   capabilities-and-scopes
   process-controls
   scheduling
   workers
   omnibus
   integration
   testing
   operations
   performance
   release-checklist

Core guarantees
---------------

* Help, list, version, and completion do not construct the command container.
* Optional capabilities initialize only for commands that declare them.
* Commands receive a fresh InterMix execution scope.
* Subprocesses use argv arrays; Console never requires shell command parsing.
* Worker concurrency, lifetimes, restart rates, and shutdown grace are bounded.
* Omnibus owns messages and queue delivery; Console owns CLI and child processes.
* Redirected output contains no ANSI sequences unless explicitly forced.
