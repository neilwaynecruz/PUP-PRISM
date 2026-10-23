<?php

test('the application publishes one installable web app manifest', function () {
    $manifestPath = public_path('manifest.webmanifest');
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest)
        ->toMatchArray([
            'id' => '/',
            'name' => 'PUP PRISM',
            'short_name' => 'PRISM',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'prefer_related_applications' => false,
        ]);

    $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

    expect(substr_count($blade, 'rel="manifest"'))->toBe(1)
        ->and($blade)->toContain('href="/manifest.webmanifest"');
});

test('the manifest declares real prism icons with their exact dimensions', function () {
    $manifest = json_decode(
        (string) file_get_contents(public_path('manifest.webmanifest')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $icons = collect($manifest['icons'] ?? [])->keyBy('sizes');

    expect($icons)->toHaveKeys(['192x192', '512x512']);

    foreach (['192x192', '512x512'] as $declaredSize) {
        $icon = $icons->get($declaredSize);
        $iconPath = public_path(ltrim((string) $icon['src'], '/'));
        $dimensions = getimagesize($iconPath);

        expect($iconPath)->toBeFile()
            ->and($icon['type'])->toBe('image/png')
            ->and(preg_split('/\s+/', trim((string) ($icon['purpose'] ?? ''))))
            ->toContain('any', 'maskable')
            ->and($dimensions)->not->toBeFalse()
            ->and($dimensions[0].'x'.$dimensions[1])->toBe($declaredSize);
    }
});

test('the service worker only caches the public shell and preserves unrelated caches', function () {
    $serviceWorker = (string) file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain("const CACHE_PREFIX = 'pup-prism-shell-';")
        ->toContain("event.request.method !== 'GET'")
        ->toContain("event.request.mode === 'navigate'")
        ->toContain('key.startsWith(CACHE_PREFIX)')
        ->toContain('requestUrl.origin === self.location.origin')
        ->not->toContain("'/dashboard'")
        ->not->toContain("'/login'")
        ->not->toContain("'/api/")
        ->not->toContain("'/manifest.json'");
});

test('the service worker completes activation work before the event ends', function () {
    $serviceWorker = (string) file_get_contents(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain("self.addEventListener('activate', (event) => {")
        ->toMatch('/event\.waitUntil\(\s*\(async \(\) => \{.*await self\.clients\.claim\(\);.*\}\)\(\),\s*\);/s')
        ->not->toMatch('/\);\s*\n\s*self\.clients\.claim\(\);\s*\n\}\);/');
});

test('the offline page clearly says that operational changes are not saved', function () {
    $offlinePage = (string) file_get_contents(public_path('offline.html'));

    expect($offlinePage)
        ->toContain('You are offline')
        ->toContain('No new inventory changes have been saved or submitted')
        ->not->toContain('Use manual records');
});
