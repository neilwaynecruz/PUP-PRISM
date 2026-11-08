<?php

use Symfony\Component\Yaml\Yaml;

test('browser CI builds assets with the isolated e2e environment', function () {
    $workflow = Yaml::parseFile(dirname(__DIR__, 2).'/.github/workflows/e2e.yml');
    $steps = collect($workflow['jobs']['playwright']['steps']);
    $build = $steps->firstWhere('name', 'Build frontend assets');
    $package = json_decode(file_get_contents(dirname(__DIR__, 2).'/package.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($build)->toBeArray()
        ->and($build['run'])->toBe('npm run build:e2e')
        ->and($package['scripts']['build:e2e'])->toBe('vite build --mode=e2e');
});
