<?php

declare(strict_types=1);

it('keeps optional adapters out of production requirements', function (): void {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(array_keys($composer['require']))->toBe([
        'php',
        'infocyph/arraykit',
        'infocyph/intermix',
        'infocyph/omnibus',
        'infocyph/uid',
    ])->and($composer['require']['infocyph/omnibus'] ?? null)->toBe('dev-main@dev')
        ->and($composer['require-dev']['infocyph/cachelayer'] ?? null)->toBe('^2.0')
        ->and($composer['require-dev']['infocyph/dblayer'] ?? null)->toBe('^3.0')
        ->and(array_keys($composer['suggest']))->toContain(
        'infocyph/cachelayer',
        'infocyph/dblayer',
        'infocyph/epicrypt',
        'infocyph/otp',
        'infocyph/pathwise',
        'infocyph/reqshield',
        'infocyph/talkingbytes',
    );
});
