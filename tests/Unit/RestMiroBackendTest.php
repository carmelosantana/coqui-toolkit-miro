<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitMiro\Auth\MiroAuthResolver;
use CarmeloSantana\CoquiToolkitMiro\Backend\RestMiroBackend;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('request sends bearer token and query parameters', function () {
    $capturedUrl = '';
    $capturedOptions = [];

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedOptions): MockResponse {
        $capturedUrl = $url;
        $capturedOptions = $options;

        return new MockResponse(json_encode(['id' => 'board-123']));
    });

    $backend = new RestMiroBackend(new MiroAuthResolver(sys_get_temp_dir(), 'token-123'), $httpClient);
    $result = $backend->request('GET', '/v2/boards/test-board', ['limit' => 25]);

    $parts = parse_url($capturedUrl);
    parse_str($parts['query'] ?? '', $queryFromUrl);

    expect($result->success)->toBeTrue()
        ->and($parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? ''))->toBe('https://api.miro.com/v2/boards/test-board')
        ->and((int) ($queryFromUrl['limit'] ?? 0))->toBe(25)
        ->and($capturedOptions['normalized_headers']['authorization'][0] ?? '')->toContain('Bearer token-123');
});

test('paginateOffset merges paged results', function () {
    $responses = [
        new MockResponse(json_encode([
            'data' => [['id' => '1'], ['id' => '2']],
            'total' => 3,
        ])),
        new MockResponse(json_encode([
            'data' => [['id' => '3']],
            'total' => 3,
        ])),
    ];

    $backend = new RestMiroBackend(
        new MiroAuthResolver(sys_get_temp_dir(), 'token-123'),
        new MockHttpClient($responses),
    );

    $result = $backend->paginateOffset('/v2/boards/test-board/items', [], 'data', 2);

    expect($result->success)->toBeTrue()
        ->and($result->data['data'])->toHaveCount(3);
});

test('request returns auth error when no token source exists', function () {
    $originalAccessToken = getenv('MIRO_ACCESS_TOKEN');
    $originalApiToken = getenv('MIRO_API_TOKEN');

    putenv('MIRO_ACCESS_TOKEN');
    putenv('MIRO_API_TOKEN');

    $backend = new RestMiroBackend(new MiroAuthResolver(sys_get_temp_dir()));
    $result = $backend->request('GET', '/v2/boards/test-board');

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('Not authenticated');

    if ($originalAccessToken !== false) {
        putenv('MIRO_ACCESS_TOKEN=' . $originalAccessToken);
    }
    if ($originalApiToken !== false) {
        putenv('MIRO_API_TOKEN=' . $originalApiToken);
    }
});