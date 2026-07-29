# Testing

## Command tests

`ApplicationTester` runs commands in memory while preserving the production
parse, validation, resolution, and command-scope lifecycle:

```php
use Infocyph\Console\Testing\ApplicationTester;

$result = (new ApplicationTester($application))
    ->command('user:create')
    ->argument('email', 'hasan@example.com')
    ->option('age', 30)
    ->answer('Continue?', true)
    ->run()
    ->assertSuccessful()
    ->assertOutputContains('Created');
```

`CommandResult` supports exit-code, output, error output, JSON, table, prompt,
and validation assertions.

## Fakes and fixtures

- `FakeTerminal` controls interactivity, dimensions, colors, and Unicode.
- `FakeKeyboard` supplies deterministic key input.
- `FakeClock` controls application test time.
- `FakeSignalManager` dispatches deterministic interrupts to consumer code.
- `FakeCapabilityLoader` records selected capability loading.
- `FrameSnapshot` verifies semantic rendering.
- `SubprocessRunner` executes argv fixtures with explicit environment/input.

Console's own supervisor suite uses deterministic child fixtures to verify:

- mixed success/failure accounting;
- heartbeat/lease loss;
- supervisor interruption;
- scale-down;
- grace-period force escalation;
- repeated bounded scaling and shutdown.

## Project checks

```bash
composer test
composer ic:test:code
composer ic:tests
composer ic:release:guard
```

`composer test` runs the command tests with PHPForge's shared Pest
configuration. Use the `ic:*` commands for the complete shared quality and
release gates.
