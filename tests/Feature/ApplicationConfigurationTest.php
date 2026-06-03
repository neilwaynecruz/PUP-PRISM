<?php

test('application timezone defaults to Asia Manila', function () {
    expect(config('app.timezone'))->toBe('Asia/Manila');
});
