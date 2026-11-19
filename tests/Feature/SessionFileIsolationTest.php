<?php

use Illuminate\Support\Env;

test('file sessions can be isolated without changing the normal application default', function () {
    $environment = Env::getRepository();
    $original = $environment->get('SESSION_FILES');

    try {
        $environment->clear('SESSION_FILES');
        expect((require config_path('session.php'))['files'])->toBe(storage_path('framework/sessions'));

        $isolatedDirectory = storage_path('framework/testing/e2e-sessions');
        $environment->set('SESSION_FILES', $isolatedDirectory);
        expect((require config_path('session.php'))['files'])->toBe($isolatedDirectory);
    } finally {
        if ($original === null) {
            $environment->clear('SESSION_FILES');
        } else {
            $environment->set('SESSION_FILES', $original);
        }
    }
});
