<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitMiro\Auth\MiroOAuth;

test('token storage and loading roundtrip works', function () {
    $tmpDir = sys_get_temp_dir() . '/miro-test-' . uniqid('', true);
    mkdir($tmpDir, 0o700, true);

    $oauth = new MiroOAuth($tmpDir, 'client-id', 'client-secret');

    $storeRef = new ReflectionMethod($oauth, 'storeTokens');
    $loadRef = new ReflectionMethod($oauth, 'loadTokens');

    $storeRef->invoke($oauth, [
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => time() + 3600,
    ]);

    $loaded = $loadRef->invoke($oauth);

    expect($loaded)->not->toBeNull()
        ->and($loaded['access_token'])->toBe('access-token')
        ->and($loaded['refresh_token'])->toBe('refresh-token');

    $path = $tmpDir . '/.miro-tokens.json';
    expect(file_exists($path))->toBeTrue()
        ->and(fileperms($path) & 0o777)->toBe(0o600);

    unlink($path);
    rmdir($tmpDir);
});

test('hasTokens returns false when no token file exists', function () {
    $oauth = new MiroOAuth(sys_get_temp_dir() . '/miro-empty-' . uniqid('', true), 'client-id', 'client-secret');

    expect($oauth->hasTokens())->toBeFalse()
        ->and($oauth->getAccessToken())->toBeNull();
});

test('getStatus reflects authentication state', function () {
    $tmpDir = sys_get_temp_dir() . '/miro-status-' . uniqid('', true);
    mkdir($tmpDir, 0o700, true);

    $oauth = new MiroOAuth($tmpDir, 'client-id', 'client-secret');
    expect($oauth->getStatus())->toContain('Not authenticated');

    $storeRef = new ReflectionMethod($oauth, 'storeTokens');
    $storeRef->invoke($oauth, [
        'access_token' => 'access-token',
        'expires_at' => time() + 7200,
    ]);

    expect($oauth->getStatus())->toContain('Authenticated');

    unlink($tmpDir . '/.miro-tokens.json');
    rmdir($tmpDir);
});

test('constructor credentials are preferred by login configuration checks', function () {
    $oauth = new MiroOAuth(sys_get_temp_dir(), 'client-id', 'client-secret');

    expect($oauth->hasLoginConfig())->toBeTrue();
});