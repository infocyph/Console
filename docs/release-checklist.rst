Release checklist
=================

Code and contracts
------------------

* Runtime dependencies use stable compatible constraints.
* Optional providers remain outside production requirements.
* Public API changes are intentional and reflected in tests and documentation.
* No command discovery, reflection, filesystem scan, or backend connection was
  added to preflight or unrelated command paths.
* Plain, ANSI, JSON, quiet, redirected, and non-interactive behavior is tested.

Workers and processes
---------------------

* SIGINT and SIGTERM drain children.
* Probe, heartbeat, startup, and output-callback exceptions do not orphan them.
* Process, supervisor, start, memory, timeout, idle, concurrency, backoff,
  failure, and grace limits remain bounded.
* The worker soak reports no failures, forced exits, or unbounded memory growth.

Documentation
-------------

* Sphinx builds with warnings treated as errors.
* PHP snippets use inline PHP highlighting.
* Examples match current constructor arguments, enum cases, commands, and
  package boundaries.
* README links to the RST guide and does not duplicate the full manual.

Verification
------------

.. code-block:: console

   composer normalize --dry-run
   composer ic:ci
   composer ic:release:guard
   composer benchmark
   composer soak:worker
   python -m sphinx -W --keep-going -b html docs build/docs

Review the benchmark and soak output rather than relying only on exit codes.
