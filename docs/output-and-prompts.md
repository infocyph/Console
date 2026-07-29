# Output and prompts

Console renders semantic frames through plain, ANSI, or JSON renderers. Command
code should emit intent through `IO` instead of writing terminal escape
sequences directly.

```php
$this->io()->title('Deployment');
$this->io()->info('Preparing release.');
$this->io()->table(
    ['Service', 'State'],
    [['API', 'ready'], ['Worker', 'draining']],
);
$this->io()->success('Deployment complete.');
```

Available components include messages, titles, sections, paragraphs, tables,
trees, listings, definition lists, boxes, horizontal rules, details, status,
progress bars, spinners, tasks, and task groups.

## Formats and terminal behavior

- Plain output is safe for redirected streams and logs.
- ANSI output is selected only when terminal capabilities allow it.
- JSON output emits machine-readable lines for automation.
- `--quiet` suppresses ordinary output.
- `-v`, `-vv`, and `-vvv` progressively expose diagnostic detail.
- Terminal width and Unicode/color capabilities are detected but can be
  replaced in tests.

## Prompts

Prompt methods support text, confirmation, choice, multiselect, password, and
validated input. Non-interactive execution must never wait for input. A
required answer that is unavailable under `--no-interaction` fails with an
invalid-usage result.

```php
$email = $this->io()->prompts()->text(
    'Email',
    required: true,
    sanitize: ['trim', 'lowercase'],
    rules: ['email'],
);
```

Do not pass secrets to ordinary output or exception messages. Use secret prompt
input and mark sensitive subprocess values so process output redaction can
replace them.
