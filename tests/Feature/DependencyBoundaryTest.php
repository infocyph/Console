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
        'infocyph/uid',
    ])->and($composer['require-dev']['infocyph/cachelayer'] ?? null)->toBe('^2.0')
        ->and($composer['require-dev']['infocyph/dblayer'] ?? null)->toBe('^2.2')
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
