# Infocyph Console — Final Architecture Draft

## 1. Project identity

```text
Project:    Infocyph Console
Package:    infocyph/console
Namespace:  Infocyph\Console
Repository: Console
PHP:        8.4+
```

Installation:

```bash
composer require infocyph/console
```

Infocyph Console is a standalone command-line application engine and the foundational package for a future opinionated CLI framework.

It is not the final CLI framework itself.

```text
Infocyph libraries
        ↓
Infocyph Console
        ↓
Future CLI framework
        ↓
CLI applications
```

The relationship is comparable to a framework depending on a lower-level integration foundation:

```text
Low-level capabilities
        ↓
Console foundation
        ↓
Opinionated CLI framework
```

`infocyph/foundation` is not required by Console.

---

# 2. Positioning

Infocyph Console should be positioned as:

> A fast, expressive foundation for building modern PHP command-line applications and frameworks.

It provides:

```text
Command definitions
Argument and option parsing
Command dispatch
Dependency injection
Configuration
Semantic validation
Terminal capability detection
ANSI and plain rendering
Interactive prompts
Tables, trees and boxes
Progress bars and spinners
Task displays
Scheduling primitives
Command manifests
Shell completion
Testing utilities
Security integrations
Persistent CLI infrastructure
```

The future CLI framework will add:

```text
Opinionated project layout
Application bootstrap conventions
Framework service providers
Default configuration structure
Command auto-discovery conventions
Application generators
Starter templates
Presets
Framework-specific commands
Default scheduler registration
Default release workflow
```

Console must remain independently usable without the future framework.

---

# 3. Core architectural principles

Infocyph Console must optimize for:

1. Fast cold startup.
2. Low command-dispatch overhead.
3. High-quality terminal UX.
4. Typed and statically analysable APIs.
5. Lazy service initialization.
6. Compiled production metadata.
7. Cross-platform fallbacks.
8. Structured text and JSON output.
9. Secure command execution.
10. Reuse of existing Infocyph libraries.
12. A deliberately small public API.

The intended quality target is:

```text
Minicli-like runtime simplicity
+
Laravel Prompts-like interaction
+
High-quality semantic rendering
+
Infocyph-native infrastructure
```

Console should not depend on:

```text
symfony/console
minicli/minicli
league/climate
laravel/prompts
nunomaduro/termwind
```

These projects may be used as design references and benchmark competitors only.

---

# 4. Dependency direction

```text
ArrayKit ────────────┐
CacheLayer ──────────┤
DBLayer ─────────────┤
EpiCrypt ────────────┤
InterMix ────────────┤
OTP ─────────────────┤
Pathwise ────────────┼──→ Infocyph Console
ReqShield ───────────┤              ↓
TalkingBytes ────────┤      Future CLI Framework
UID ─────────────────┘              ↓
                               CLI applications
```

Excluded:

```text
infocyph/foundation
    → not required
    → not suggested
    → not referenced
```

---

# 5. Composer dependencies

## Runtime dependencies

Packages used by every installation belong in `require`. Capability adapters
may remain in `suggest` when their classes are loaded only after the
corresponding feature is selected.

```json
{
    "require": {
        "php": "^8.4",
        "infocyph/arraykit": "<compatible-version>",
        "infocyph/intermix": "<compatible-version>",
        "infocyph/uid": "<compatible-version>"
    },
    "suggest": {
        "infocyph/cachelayer": "<capability adapter>",
        "infocyph/dblayer": "<capability adapter>",
        "infocyph/epicrypt": "<capability adapter>",
        "infocyph/otp": "<capability adapter>",
        "infocyph/pathwise": "<capability adapter>",
        "infocyph/reqshield": "<capability adapter>",
        "infocyph/talkingbytes": "<capability adapter>"
    }
}
```

UID is a core command-execution dependency but its generator remains lazy until
an identity-capable command is selected. Capability adapters remain optional
and must fail clearly only when their corresponding feature is requested.

## Development dependencies

```json
{
    "require-dev": {
        "infocyph/phpforge": "dev-main"
    }
}
```

---

# 6. Dependency responsibilities

## 6.1 InterMix

InterMix provides:

```text
PSR-11 container
Constructor injection
Lazy services
Scoped services
Service invocation
Compiled definitions
Resolver caching
Command-scoped dependencies
```

Console must not implement another dependency injection container.

Console should provide only its integration layer:

```text
ContainerFactory
ContainerConfigurator
CommandResolver
CommandResolverProvider
```

Command state is supplied through InterMix scope seeds. Console must not mutate
container definitions for context, input, arguments, options, IO, or execution
identity on every dispatch.

Example:

```php
<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\DeploymentService;
use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\ExitCode;

final class DeployCommand extends Command
{
    public function __construct(
        private readonly DeploymentService $deployment,
    ) {
    }

    protected function handle(): int
    {
        $this->deployment->deploy();

        return ExitCode::SUCCESS;
    }
}
```

The container must not be exposed as a service locator through command context or IO objects.

---

## 6.2 ArrayKit

ArrayKit provides:

```text
Configuration loading
Configuration merging
Nested key access
Typed configuration values
Command metadata normalization
Theme configuration
Build configuration
Schedule configuration
Structured option normalization
```

Use ArrayKit for application and infrastructure configuration.

Prefer native arrays inside parser, registry and rendering hot paths where they are faster and simpler.

---

## 6.3 CacheLayer

CacheLayer provides:

```text
Persistent command state
Completion caches
Remote metadata caches
Scheduler mutexes
Distributed scheduling locks
Self-update metadata
Application caches
InterMix definition caches
Rate-limit state
```

The production command manifest must not go through CacheLayer.

Use directly includable PHP files:

```php
<?php

return [
    'project:create' => [
        'class' => App\Commands\ProjectCreateCommand::class,
        'description' => 'Create a new project',
        'aliases' => [],
        'hidden' => false,
    ],
];
```

This avoids initializing a generic cache layer during command preflight.

---

## 6.4 DBLayer

DBLayer provides optional persistent Console infrastructure:

```text
Command execution history
Audit records
Persistent scheduler state
Distributed scheduler coordination
Long-running task state
Framework migration state
Application command data
Database-backed configuration
```

DBLayer must never connect automatically.

It should initialize only when a resolved command or scheduler capability actually requires database access.

Example:

```php
final readonly class CommandHistoryRepository
{
    public function record(CommandExecution $execution): void
    {
        // Persist through DBLayer.
    }
}
```

Console should not create wrappers that merely rename DBLayer APIs.

Only Console-specific persistence abstractions should be added.

---

## 6.5 EpiCrypt

EpiCrypt provides:

```text
Encrypted local secrets
Integrity verification
Signed release metadata
Secure tokens
Key management
Artifact signature verification
Sensitive configuration protection
```

Console can expose secure application-level services such as:

```text
SecretStore
ReleaseSignatureVerifier
SecureConfiguration
ArtifactVerifier
```

Potential future framework commands:

```text
secret:set
secret:get
secret:remove
secret:list
```

Console itself should expose the underlying reusable services, not prescribe the final framework command names.

---

## 6.6 OTP

OTP provides:

```text
TOTP verification
HOTP verification
OCRA verification
Recovery codes
Step-up command authorization
Sensitive-operation verification
```

A command may declare an authentication policy:

```php
public static function define(CommandDefinition $command): void
{
    $command
        ->name('production:reset')
        ->requiresOtp();
}
```

OTP must remain dormant for ordinary commands.

The execution flow for a protected command becomes:

```text
Resolve command
    ↓
Inspect authentication policy
    ↓
Prompt for verification code
    ↓
Verify through OTP
    ↓
Execute command
```

---

## 6.7 Pathwise

Pathwise provides:

```text
Path normalization
Safe file reads and writes
Atomic replacements
Directory operations
Temporary directories
Build staging
Artifact movement
Cleanup
Checksums
Skeleton copying
Runtime path resolution
```

Pathwise prepares, manages and publishes application files. Archive compilation is outside Console's scope.

---

## 6.8 ReqShield

ReqShield provides semantic validation for:

```text
Command values
Prompt values
Interactive forms
Configuration
Build configuration
Scheduler definitions
Imported structured data
Database-backed uniqueness and existence rules
Sanitization
Typed validated output
```

ReqShield must not replace the native argv parser.

The responsibility boundary is:

```text
Console parser
    → command grammar
    → tokens
    → required values
    → flags
    → option syntax
    → scalar conversion

ReqShield
    → semantic rules
    → formats
    → ranges
    → relationships
    → sanitization
    → typed validated data
```

Example:

```php
public static function define(CommandDefinition $command): void
{
    $command
        ->name('user:create')
        ->argument(
            Argument::required('email')
                ->type(ValueType::STRING)
                ->sanitize(['trim', 'lowercase'])
                ->rules(['email', 'max:255']),
        )
        ->option(
            Option::value('age')
                ->type(ValueType::INTEGER)
                ->rules(['min:18', 'max:120']),
        );
}
```

The parser performs scalar conversion.

ReqShield validates the resulting values once before command execution.

Validation must not run repeatedly through argument accessors.

---

## 6.9 TalkingBytes

TalkingBytes provides:

```text
HTTP communication
Downloads
Concurrent requests
Remote release checks
Update downloads
Webhooks
Email notifications
Remote APIs
gRPC integrations
```

TalkingBytes must initialize only when required by the selected command.

Commands such as these must not boot networking services:

```text
--version
list
help
local file operations
basic generators
```

---

## 6.10 UID

UID provides identifiers for:

```text
Command executions
Scheduler runs
Task groups
Background tasks
Mutex ownership
Audit entries
Trace correlation
Build artifacts
Update transactions
```

Example:

```php
final readonly class CommandExecution
{
    public function __construct(
        public string $id,
        public string $command,
        public int $startedAt,
    ) {
    }
}
```

The same execution ID can correlate:

```text
Terminal diagnostics
Logs
Database history
Remote requests
Scheduler records
Locks
Errors
Audit events
```

---

# 7. Capability-based lazy loading

Adding packages to Composer `require` does not mean all packages participate in every command execution.

Console should implement capability-aware service activation.

```php
public static function define(CommandDefinition $command): void
{
    $command
        ->name('release:publish')
        ->capabilities([
            Capability::FILESYSTEM,
            Capability::NETWORK,
            Capability::CRYPTOGRAPHY,
            Capability::IDENTITY,
        ]);
}
```

Compiled descriptor:

```php
<?php

return [
    'release:publish' => [
        'class' => App\Commands\ReleasePublishCommand::class,
        'capabilities' => [
            'filesystem',
            'network',
            'cryptography',
            'identity',
        ],
    ],
];
```

Suggested capabilities:

```text
configuration
container
filesystem
cache
database
network
cryptography
otp
identity
validation
scheduler
process
```

## Preflight tier

Used for:

```text
--version
list
basic help
unknown command lookup
completion metadata
```

Load only:

```text
Composer autoloader
Application metadata
Command manifest
Minimal argv parser
Minimal renderer
```

Do not initialize:

```text
InterMix container
ArrayKit configuration
CacheLayer
DBLayer
EpiCrypt
OTP
Pathwise
ReqShield
TalkingBytes
UID
```

## Standard command tier

Load:

```text
InterMix
Selected command
Command-required configuration
Command-required services
```

## Infrastructure tier

Load only when requested:

```text
ArrayKit
Pathwise
CacheLayer
UID
ReqShield
```

## Specialized tier

Load only for declared capabilities:

```text
DBLayer
EpiCrypt
OTP
TalkingBytes
```

---

# 8. Repository structure

```text
console/
├── bin/
│   └── console
│
├── src/
│   ├── Application/
│   │   ├── Application.php
│   │   ├── ApplicationBuilder.php
│   │   ├── ApplicationConfiguration.php
│   │   ├── ApplicationMetadata.php
│   │   ├── Kernel.php
│   │   └── Runtime.php
│   │
│   ├── Bootstrap/
│   │   ├── Bootstrapper.php
│   │   ├── BootstrapPipeline.php
│   │   ├── LoadConfiguration.php
│   │   ├── LoadContainer.php
│   │   ├── LoadCommands.php
│   │   └── LoadCapabilities.php
│   │
│   ├── Command/
│   │   ├── Command.php
│   │   ├── CommandContract.php
│   │   ├── CommandContext.php
│   │   ├── CommandDefinition.php
│   │   ├── CommandDescriptor.php
│   │   ├── CommandRegistry.php
│   │   ├── CommandResolver.php
│   │   ├── CommandManifest.php
│   │   ├── Capability.php
│   │   └── ExitCode.php
│   │
│   ├── Input/
│   │   ├── ArgvParser.php
│   │   ├── ParsedInput.php
│   │   ├── Argument.php
│   │   ├── ArgumentCollection.php
│   │   ├── Option.php
│   │   ├── OptionCollection.php
│   │   ├── ValueType.php
│   │   └── Token.php
│   │
│   ├── Validation/
│   │   ├── InputValidator.php
│   │   ├── ValidationCompiler.php
│   │   ├── CompiledValidation.php
│   │   ├── ValidationResult.php
│   │   ├── ValidationFailure.php
│   │   └── DBLayerDatabaseProvider.php
│   │
│   ├── Output/
│   │   ├── Output.php
│   │   ├── ConsoleOutput.php
│   │   ├── BufferedOutput.php
│   │   ├── NullOutput.php
│   │   ├── JsonOutput.php
│   │   ├── Stream.php
│   │   └── Verbosity.php
│   │
│   ├── IO/
│   │   ├── IO.php
│   │   ├── ConsoleIO.php
│   │   └── BufferedIO.php
│   │
│   ├── Terminal/
│   │   ├── Terminal.php
│   │   ├── TerminalCapabilities.php
│   │   ├── CapabilityDetector.php
│   │   ├── Cursor.php
│   │   ├── Keyboard.php
│   │   ├── Screen.php
│   │   ├── RawMode.php
│   │   └── SignalManager.php
│   │
│   ├── Render/
│   │   ├── Renderer.php
│   │   ├── AnsiRenderer.php
│   │   ├── PlainRenderer.php
│   │   ├── JsonRenderer.php
│   │   ├── Frame.php
│   │   ├── Line.php
│   │   └── FrameDiffer.php
│   │
│   ├── Style/
│   │   ├── Theme.php
│   │   ├── DefaultTheme.php
│   │   ├── Style.php
│   │   ├── Color.php
│   │   ├── Border.php
│   │   └── Glyph.php
│   │
│   ├── Component/
│   │   ├── Title.php
│   │   ├── Section.php
│   │   ├── Message.php
│   │   ├── Box.php
│   │   ├── Table.php
│   │   ├── Tree.php
│   │   ├── Listing.php
│   │   ├── DefinitionList.php
│   │   ├── ProgressBar.php
│   │   ├── Spinner.php
│   │   ├── Task.php
│   │   └── Status.php
│   │
│   ├── Prompt/
│   │   ├── Prompt.php
│   │   ├── PromptManager.php
│   │   ├── Form.php
│   │   ├── TextPrompt.php
│   │   ├── TextAreaPrompt.php
│   │   ├── NumberPrompt.php
│   │   ├── PasswordPrompt.php
│   │   ├── ConfirmPrompt.php
│   │   ├── SelectPrompt.php
│   │   ├── MultiSelectPrompt.php
│   │   ├── SearchPrompt.php
│   │   ├── AutocompletePrompt.php
│   │   └── PathPrompt.php
│   │
│   ├── Container/
│   │   ├── ContainerFactory.php
│   │   └── ContainerConfigurator.php
│   │
│   ├── Configuration/
│   │   ├── Configuration.php
│   │   ├── ConfigurationLoader.php
│   │   ├── ConfigurationRepository.php
│   │   ├── ConfigurationCompiler.php
│   │   └── ConfigurationValidator.php
│   │
│   ├── Discovery/
│   │   ├── CommandDiscoverer.php
│   │   ├── CommandManifestCompiler.php
│   │   ├── PackageDiscoverer.php
│   │   └── DiscoveryResult.php
│   │
│   ├── Cache/
│   ├── Data/
│   ├── Security/
│   │   ├── Encryption/
│   │   ├── Secrets/
│   │   ├── Signing/
│   │   └── Otp/
│   │
│   ├── Identity/
│   ├── Filesystem/
│   ├── Communication/
│   ├── Completion/
│   ├── Scheduling/
│   ├── Build/
│   ├── Update/
│   ├── Testing/
│   └── Exception/
│
├── tests/
├── benchmarks/
├── composer.json
└── phpunit.xml
```

These are internal modules within one package.

Console should not include an opinionated framework application skeleton.

---

# 9. Public API

The initial public surface should remain small:

```text
Infocyph\Console\Application
Infocyph\Console\Command\Command
Infocyph\Console\Command\CommandContract
Infocyph\Console\Command\CommandContext
Infocyph\Console\Command\CommandDefinition
Infocyph\Console\Command\Capability
Infocyph\Console\Command\ExitCode
Infocyph\Console\Input\Argument
Infocyph\Console\Input\Option
Infocyph\Console\Input\ValueType
Infocyph\Console\IO\IO
Infocyph\Console\Style\Theme
Infocyph\Console\Scheduling\Schedule
Infocyph\Console\Testing\CommandTestCase
```

Most adapters and implementation classes should be marked internal.

The public API should not unnecessarily expose underlying Infocyph package internals.

---

# 10. Basic application usage

Console should work directly:

```php
<?php

declare(strict_types=1);

use App\Commands\HelloCommand;
use Infocyph\Console\Application;

$application = Application::configure()
    ->name('example')
    ->version('1.0.0')
    ->commands([
        HelloCommand::class,
    ])
    ->build();

exit($application->run());
```

The future CLI framework may later wrap this:

```text
Future framework configuration
            ↓
Console ApplicationBuilder
            ↓
Console runtime
```

Console must not depend on that wrapper.

---

# 11. Command API

Use typed command definitions.

```php
<?php

declare(strict_types=1);

namespace App\Commands;

use Infocyph\Console\Command\Command;
use Infocyph\Console\Command\CommandDefinition;
use Infocyph\Console\Command\ExitCode;
use Infocyph\Console\Input\Argument;
use Infocyph\Console\Input\Option;
use Infocyph\Console\Input\ValueType;

final class UserCreateCommand extends Command
{
    public static function define(CommandDefinition $command): void
    {
        $command
            ->name('user:create')
            ->description('Create a user account')
            ->argument(
                Argument::required('email')
                    ->type(ValueType::STRING)
                    ->sanitize(['trim', 'lowercase'])
                    ->rules(['email', 'max:255'])
                    ->description('User email address'),
            )
            ->option(
                Option::value('age')
                    ->type(ValueType::INTEGER)
                    ->rules(['min:18', 'max:120']),
            )
            ->option(
                Option::flag('force')
                    ->shortcut('f')
                    ->description('Skip confirmation'),
            );
    }

    protected function handle(): int
    {
        $email = $this->arguments()->string('email');
        $age = $this->options()->nullableInt('age');

        $this->io()->success(
            sprintf('Created user %s.', $email),
        );

        return ExitCode::SUCCESS;
    }
}
```

Avoid runtime signature strings:

```php
'user:create {email} {--age=} {--force}'
```

Typed definitions provide:

```text
Static analysis
IDE completion
Predictable parsing
Semantic validation
Manifest compilation
Completion generation
Typed input accessors
No runtime signature parser
```

---

# 12. Parsing and validation lifecycle

```text
Read argv
    ↓
Resolve command
    ↓
Parse command grammar
    ↓
Convert basic scalar values
    ↓
Load compiled ReqShield schema when present
    ↓
Sanitize and validate once
    ↓
Create typed command input
    ↓
Execute command
```

The native parser is responsible for:

```text
Unknown options
Unknown arguments
Missing required arguments
Missing option values
Short-option parsing
Long-option parsing
Negatable flags
Variadic argument placement
Duplicate option policy
Basic scalar conversion
```

ReqShield is responsible for:

```text
Email formats
URLs
Ranges
String lengths
Enums
Regular expressions
Dates
Time zones
Nested values
Conditional rules
Cross-field validation
Database existence
Database uniqueness
Sanitization
Typed validated output
```

Commands without semantic rules should bypass ReqShield entirely.

---

# 13. Command execution lifecycle

```text
Read argv
    ↓
Resolve global options
    ↓
Resolve command descriptor
    ↓
Parse command-specific input
    ↓
Validate semantic input
    ↓
Resolve declared capabilities
    ↓
Enter command scope with ready-instance seeds
    ↓
Load required services
    ↓
Construct selected command
    ↓
Execute
    ↓
Run termination callbacks
    ↓
Return exit code
```

Only the selected command should be constructed.

Unrelated commands, integrations and services must remain unloaded.

---

# 14. Preflight execution

The following must bypass full application startup:

```text
--version
list
basic help
unknown command detection
completion metadata
command resolution
```

Preflight loads only:

```text
Composer autoloader
Application metadata
Command manifest
Native argv parser
Minimal output renderer
```

The following must not initialize during preflight:

```text
InterMix container
ArrayKit configuration
CacheLayer
DBLayer
EpiCrypt
OTP
Pathwise
ReqShield
TalkingBytes
UID
```

---

# 15. Argument parser

Build a native single-pass parser.

Target:

```text
Time:   O(argv token count)
Memory: O(parsed value count)
```

Supported forms:

```bash
tool command value
tool command --option=value
tool command --option value
tool command --flag
tool command --no-flag
tool command -v
tool command -vvv
tool command -abc
tool command -- value-starting-with-dashes
```

Supported definitions:

```text
Required arguments
Optional arguments
Variadic arguments
Boolean flags
Negatable flags
Single-value options
Multi-value options
Short aliases
Defaults
Environment-backed values
Scalar types
Normalizers
Semantic rules
```

Fuzzy command matching should run only after exact lookup fails.

---

# 16. IO API

Commands should use one cohesive IO interface:

```php
$this->io()->title('Create project');

$this->io()->note(
    'Configuration will be written to the target directory.',
);

$this->io()->table(
    headers: ['Package', 'Version', 'Status'],
    rows: $packages,
);

$this->io()->success('Project created successfully.');
```

Semantic output:

```php
$io->text('Message');
$io->muted('Additional detail');
$io->info('Informational message');
$io->note('Important note');
$io->success('Completed');
$io->warning('Potential problem');
$io->error('Operation failed');
```

Avoid making colors the main API:

```php
$io->green('Success');
$io->red('Failure');
```

Semantic roles support:

```text
Themes
Plain output
JSON output
Accessibility
Color-disabled terminals
Consistent framework styling
```

---

# 17. Visual components

Version 1.0 should provide:

```text
Title
Section
Paragraph
Horizontal rule
Info message
Success message
Warning message
Error message
Note
Box
Table
Tree
Bullet list
Numbered list
Definition list
Two-column details
Progress bar
Spinner
Task
Status line
Live task group
```

Example:

```php
$this->io()->box(
    title: 'Deployment',
    content: [
        'Environment' => 'production',
        'Region' => 'ap-south-1',
        'Version' => '1.4.0',
    ],
);
```

Do not implement HTML-like or Tailwind-like markup.

Avoid:

```php
render('<div class="px-2 bg-green-500">Done</div>');
```

Typed components are faster, easier to analyze and easier to render in different formats.

---

# 18. Rendering architecture

```text
Semantic component
        ↓
Frame
        ↓
Renderer
        ↓
Output stream
```

Renderers:

```text
AnsiRenderer
PlainRenderer
JsonRenderer
BufferedRenderer
```

Example:

```php
final readonly class Frame
{
    /**
     * @param list<Line> $lines
     */
    public function __construct(
        public array $lines,
    ) {
    }
}
```

The frame model enables:

```text
Batch writes
Snapshot tests
Terminal-width adaptation
Plain fallbacks
Structured rendering
Live frame diffing
```

Static components should normally render into one string and use one stream write.

---

# 19. Live rendering

One live-rendering engine should power:

```text
Progress bars
Spinners
Search prompts
Select prompts
Multi-select prompts
Live task groups
Status displays
```

Rendering flow:

```text
Previous frame
      ↓
Compare with current frame
      ↓
Find changed lines
      ↓
Rewrite only changed content
```

Required behavior:

```text
Cache terminal dimensions
Rate-limit redraws
Avoid full-screen clearing
Clear stale lines
Restore cursor state on failure
Disable animation when redirected
```

Recommended maximum refresh rate:

```text
20–30 frames per second
```

---

# 20. Interactive prompts

Required prompt types:

```text
Text
Text area
Number
Password
Confirmation
Select
Multi-select
Search
Autocomplete
Path
Form
```

Example:

```php
$name = $this->io()->text(
    label: 'Project name',
    placeholder: 'my-project',
    required: true,
    sanitize: ['trim', 'lowercase'],
    rules: ['alpha_dash', 'max:80'],
);

$preset = $this->io()->select(
    label: 'Application preset',
    options: [
        'minimal' => 'Minimal',
        'api' => 'API',
        'full' => 'Full',
    ],
    default: 'minimal',
);
```

Prompt capabilities:

```text
Placeholder
Hint
Default value
Required value
ReqShield rules
Sanitization
Callback validation
Cancellation
Keyboard navigation
Filtering
Scrolling
Maximum visible options
Non-interactive fallback
Testing hooks
```

Simple callback validation should remain available:

```php
$name = $this->io()->text(
    label: 'Project name',
    validate: static fn (string $value): ?string =>
        $value !== ''
            ? null
            : 'Project name is required.',
);
```

Use:

```text
Simple one-off rule
    → callback

Reusable or multi-rule validation
    → ReqShield

Multiple related values
    → ReqShield-backed form
```

No prompt may wait for input under `--no-interaction`.

It must:

1. use a supplied command value;
2. use a declared default;
3. or fail with a usage error.

---

# 21. Configuration lifecycle

```text
Pathwise
    locate and read files
        ↓
ArrayKit
    merge configuration layers
        ↓
ReqShield
    sanitize and validate
        ↓
ArrayKit
    expose validated configuration
```

Configuration validation may cover:

```text
Application metadata
Terminal behavior
Theme settings
Build settings
Scheduler settings
Update channels
Cache configuration
Database configuration
Security policies
```

Unknown configuration fields may be rejected in strict production mode.

Validation should happen once during configuration compilation or application bootstrap, not through every getter call.

---

# 22. Terminal capability detection

Detect:

```text
TTY state
Interactive input
Interactive output
ANSI support
Unicode support
Color depth
Terminal width
Terminal height
NO_COLOR
TERM=dumb
CI environment
Windows terminal support
Input redirection
Output redirection
```

```php
final readonly class TerminalCapabilities
{
    public function __construct(
        public bool $interactive,
        public bool $ansi,
        public bool $unicode,
        public ColorDepth $colorDepth,
        public int $width,
        public int $height,
    ) {
    }
}
```

Automatic degradation:

```text
Interactive:
  ✔ Project created successfully.

Plain:
  [OK] Project created successfully.

JSON:
  {"type":"success","message":"Project created successfully."}
```

Unsupported terminals should fall back to:

```text
Line-oriented input
ASCII glyphs
No animation
No cursor manipulation
Numbered choices
```

---

# 23. Themes

```php
interface Theme
{
    public function style(string $role): Style;
}
```

Default semantic roles:

```text
Primary
Accent
Success
Warning
Danger
Info
Muted
Border
Heading
Selected
Disabled
```

Applications and future frameworks may provide complete themes.

Theme lookup should occur at component-render level, not for each character.

---

# 24. Global options

Console applications automatically support:

```text
-h, --help
-V, --version
-q, --quiet
-v, -vv, -vvv
-n, --no-interaction
--ansi
--no-ansi
--no-color
--format=text|json
--width=<columns>
--profile
```

Respect:

```text
NO_COLOR
TERM
COLORTERM
CI
COLUMNS
LINES
```

Structured output must include failures as well as successes.

---

# 25. Error and validation UX

Unknown command:

```text
Command "make:contorller" was not found.

Did you mean:
  make:controller
  make:command
```

Invalid option:

```text
Option "--enviroment" is not defined.

Did you mean "--environment"?
```

Validation failure:

```text
ERROR  Invalid command input

  email
  └─ Enter a valid email address.

  age
  └─ Must be at least 18.

Run "user:create --help" for command usage.
```

JSON output:

```json
{
    "error": "invalid_input",
    "exit_code": 2,
    "failures": [
        {
            "field": "email",
            "rule": "email",
            "message": "Enter a valid email address."
        },
        {
            "field": "age",
            "rule": "min",
            "message": "Must be at least 18."
        }
    ]
}
```

Exception output:

```text
ERROR  Unable to connect to the remote service.

Connection refused at api.example.com:443

Run with -vvv to display the complete stack trace.
```

Verbosity:

```text
Normal  concise message and corrective action
-v      exception type and source location
-vv     compact stack trace
-vvv    complete diagnostic output
```

Exit codes:

```text
0    success
1    general failure
2    invalid usage
126  command cannot execute
127  command not found
130  interrupted
```

Custom exceptions may expose exit codes:

```php
interface ProvidesExitCode
{
    public function exitCode(): int;
}
```

---

# 26. Discovery and compiled manifests

Console provides generic command discovery and compilation.

Development mode may scan configured command paths.

Production must use compiled manifests.

Possible outputs:

```text
commands.php
configuration.php
container.php
packages.php
completion.php
validation.php
schedules.php
```

Command manifest:

```php
<?php

return [
    'user:create' => [
        'class' => App\Commands\UserCreateCommand::class,
        'description' => 'Create a user account',
        'aliases' => [],
        'hidden' => false,
        'capabilities' => [
            'validation',
            'database',
        ],
        'validation' => 'validation.user-create',
    ],
];
```

Benefits:

```text
No production directory scanning
No command construction for list
No command construction for help
No reflection during execution
Fast command resolution
Selective capability loading
Fast completion generation
Reusable compiled validation
```

Attributes and reflection may be used during compilation, but not during every production command execution.

The future CLI framework will define default manifest locations and optimize commands.

---

# 27. Scheduling

Console should provide reusable scheduling primitives:

```php
use Infocyph\Console\Scheduling\Schedule;

$schedule
    ->command('users:sync')
    ->hourly()
    ->withoutOverlap();
```

Required capabilities:

```text
Cron expressions
Fluent frequencies
Time zones
Overlap prevention
CacheLayer mutexes
DBLayer persistent state
Single-server execution
Success callbacks
Failure callbacks
Schedule inspection
Validated schedule definitions
```

Schedule definitions should be validated through ReqShield during registration or compilation.

The future CLI framework will decide how schedules are registered and which built-in commands are exposed.

---

# 28. Security model

Console should provide reusable security primitives rather than opinionated application policies.

Potential services:

```text
SecretStore
SecureConfiguration
CommandAuthorizationPolicy
OtpChallenge
ReleaseSignatureVerifier
ArtifactVerifier
SensitiveValueRedactor
```

Security rules:

```text
Never print secrets
Redact sensitive arguments
Avoid storing raw OTP values
Use atomic secret writes
Verify signatures before replacement
Verify checksums after downloads
Use explicit command protection policies
Restore terminal state after secret prompts
```

Protected command example:

```php
public static function define(CommandDefinition $command): void
{
    $command
        ->name('production:reset')
        ->requiresOtp()
        ->capabilities([
            Capability::OTP,
            Capability::DATABASE,
            Capability::IDENTITY,
        ]);
}
```

---

# 29. Process execution

Use an existing Infocyph process abstraction when one is available.

Otherwise Console should provide a minimal argv-based process runner.

Correct:

```php
$process->run([
    'git',
    'clone',
    '--depth=1',
    $repository,
    $directory,
]);
```

Avoid:

```php
shell_exec("git clone {$repository} {$directory}");
```

Required process features:

```text
argv-based execution
Working directory
Environment variables
Timeout
Idle timeout
stdout/stderr separation
Streaming output
TTY passthrough
Signal forwarding
Cancellation
Sensitive-value redaction
```

---

# 30. Distribution tooling

Console does not provide archive compilation or a distribution builder. Applications and future frameworks may choose their own distribution tooling without introducing a Console dependency.

Pathwise remains available for application-level safe file preparation, staging, checksums, and atomic publishing when an application needs those capabilities.

---

# 32. Testing utilities

Console should include command-testing support in the same package.

```php
final class UserCreateCommandTest extends CommandTestCase
{
    public function test_user_can_be_created(): void
    {
        $this->command('user:create')
            ->argument('email', 'hasan@example.com')
            ->option('age', 30)
            ->answer('Continue?', true)
            ->run()
            ->assertSuccessful()
            ->assertOutputContains('Created user hasan@example.com.');
    }
}
```

Required assertions:

```text
assertSuccessful
assertFailed
assertExitCode
assertOutput
assertOutputContains
assertErrorOutput
assertAsked
assertNotAsked
assertTable
assertJson
assertValidationFailed
assertValidationPassed
```

Testing infrastructure:

```text
Buffered IO
Fake keyboard
Fake terminal
Fake clock
Fake signal manager
Prompt answer queue
Frame snapshots
Fake capability loader
Subprocess runner
```

Subprocess tests are required for:

```text
Signals
TTY detection
Environment variables
stdout/stderr separation
Executable permissions
Platform behavior
Secret prompts
OTP prompts
```

---

# 33. Performance requirements

Performance is a release requirement.

Console must guarantee:

```text
No Symfony Console dependency
No runtime signature parsing
No production command scanning
No unrelated command construction
No full container boot for --version
No full container boot for list
No terminal markup parser
No per-character style allocation
No uncontrolled animation redraws
No networking for local commands
No database connection without database capability
No ReqShield boot without semantic rules
No OTP or cryptography boot without command policy
```

## Validation performance rules

```text
Do not use ReqShield for argv token parsing
Compile schemas during optimization
Validate parsed input once
Reuse compiled validators
Do not validate through every accessor
Do not initialize DB validation without DB-backed rules
Skip validation entirely when a command has no rules
```

## Benchmarks

Microbenchmarks:

```text
argv parsing
command lookup
manifest loading
container initialization
capability resolution
validation schema loading
semantic validation
command construction
ANSI rendering
plain rendering
table rendering
frame diffing
prompt filtering
```

Process benchmarks:

```text
--version
list
help
unknown command
no-op command
validated command
prompt initialization
```

Compare against:

```text
Raw PHP
Minicli
Ahc CLI
Symfony Console
Infocyph Console
```

Test with CLI OPcache both enabled and disabled.

CI should enforce relative performance regression limits.

---

# 34. Implementation sequence

## Phase 1 — command kernel

```text
Application
ApplicationBuilder
Command
CommandDefinition
CommandRegistry
ArgvParser
ParsedInput
ExitCode
Plain output
Help
List
Version
```

Acceptance:

```text
No third-party console framework
Typed arguments and options
Fast preflight execution
Only selected command constructed
```

## Phase 2 — InterMix integration

```text
Container factory
Constructor injection
Command scope
Service definitions
Compiled container loading
Command resolver
```

## Phase 3 — terminal and rendering

```text
Capability detection
ANSI renderer
Plain renderer
JSON renderer
Cursor control
Screen restoration
Frame model
Frame diffing
```

## Phase 4 — visual components

```text
Messages
Titles
Sections
Boxes
Tables
Trees
Lists
Progress bars
Spinners
Tasks
Live task groups
```

## Phase 5 — prompts

```text
Text
Password
Confirm
Select
Multi-select
Search
Autocomplete
Forms
Cancellation
Non-interactive behavior
Testing hooks
```

## Phase 6 — ReqShield integration

```text
Command semantic validation
Prompt validation
Form schemas
Configuration validation
Build validation
Schedule validation
Compiled validation schemas
DBLayer validation provider
Validation error rendering
```

## Phase 7 — configuration and manifests

```text
ArrayKit configuration
Command discovery
Command manifests
Configuration compilation
Validation manifests
InterMix compilation
Completion manifests
Schedule manifests
```

## Phase 8 — infrastructure integrations

```text
Pathwise filesystem workflows
CacheLayer state and mutexes
UID command identities
DBLayer persistence
EpiCrypt secrets and signing
OTP command authorization
TalkingBytes downloads and HTTP
```

## Phase 9 — testing framework

```text
In-memory command tests
Prompt simulation
Validation assertions
Frame assertions
Subprocess tests
Terminal fixtures
Security fixtures
```

## Phase 11 — hardening

```text
Linux
macOS
Windows
WSL
Signals
Read-only installations
Composer global installations
CI output
JSON mode
Performance gates
Security review
```

---

# 35. Future CLI framework relationship

It will add:

```text
Framework application class
Project layout
Bootstrap conventions
Configuration directories
Provider conventions
Command auto-discovery
Code generators
Application initialization
Default scheduling setup
Default build configuration
Starter templates
Framework-specific commands
Framework release workflow
```

Dependency flow:

```text
Infocyph low-level libraries
              ↓
      Infocyph Console
              ↓
      Future CLI Framework
              ↓
        User CLI Project
```

Console remains independently usable while serving as the primary command-line foundation for the future framework.

---

# 36. Final architectural decisions

```text
Name:
  Infocyph Console

Composer package:
  infocyph/console

Namespace:
  Infocyph\Console

Role:
  Standalone command-line engine and future CLI framework foundation

Foundation dependency:
  None

Required Infocyph libraries:
  ArrayKit
  CacheLayer
  DBLayer
  EpiCrypt
  InterMix
  OTP
  Pathwise
  ReqShield
  TalkingBytes
  UID

Native Console implementation:
  Command kernel
  Argument parser
  Command registry
  Terminal renderer
  Visual components
  Interactive prompts
  Completion primitives
  Capability loading
  Validation integration
  Error rendering
  Testing API

Validation boundary:
  Console parser handles command grammar
  ReqShield handles semantic validation

Not included:
  Opinionated framework project structure
  Framework starter application
  Framework-specific bootstrap conventions
  Framework-specific generators

Core principles:
  Fast startup
  Lazy integration loading
  Typed APIs
  Semantic output
  Excellent terminal UX
  Compiled production metadata
  Graceful terminal fallback
  Secure command execution
  No duplicated Infocyph infrastructure
```

Final dependency map:

```text
ArrayKit ────────────┐
CacheLayer ──────────┤
DBLayer ─────────────┤
EpiCrypt ────────────┤
InterMix ────────────┤
OTP ─────────────────┤
Pathwise ────────────┼──→ Infocyph Console
ReqShield ───────────┤              ↓
TalkingBytes ────────┤      Future CLI Framework
UID ─────────────────┘              ↓
                               CLI Applications

infocyph/foundation ────→ Not involved
```
