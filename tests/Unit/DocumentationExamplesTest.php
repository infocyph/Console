<?php

declare(strict_types=1);

it('keeps documented first-party imports loadable', function (): void {
    $documentation = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'docs';
    $files = glob($documentation . DIRECTORY_SEPARATOR . '*.rst') ?: [];
    $imports = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();
        preg_match_all('/^\s+use\s+(Infocyph\\\\[^;]+);$/m', (string) $contents, $matches);
        foreach ($matches[1] as $import) {
            $imports[$import] = true;
        }
    }

    expect($imports)->not->toBe([]);
    foreach (array_keys($imports) as $import) {
        expect(
            class_exists($import)
            || interface_exists($import)
            || enum_exists($import),
            sprintf('Documented type %s must be loadable.', $import),
        )->toBeTrue();
    }
});

it('publishes one RST documentation tree with inline PHP highlighting', function (): void {
    $documentation = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'docs';
    $markdown = glob($documentation . DIRECTORY_SEPARATOR . '*.md') ?: [];
    $configuration = file_get_contents($documentation . DIRECTORY_SEPARATOR . 'conf.py');
    $index = file_get_contents($documentation . DIRECTORY_SEPARATOR . 'index.rst');

    expect($markdown)->toBe([])
        ->and($configuration)->not->toBeFalse()
        ->and($configuration)->toContain('"startinline": True')
        ->and($index)->not->toBeFalse()
        ->and($index)->toContain('output-and-themes')
        ->toContain('workers')
        ->toContain('omnibus')
        ->toContain('recipes');
});
