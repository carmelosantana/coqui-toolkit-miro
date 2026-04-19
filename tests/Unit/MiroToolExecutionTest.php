<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitMiro\Auth\MiroAuthResolver;
use CarmeloSantana\CoquiToolkitMiro\Backend\RestMiroBackend;
use CarmeloSantana\CoquiToolkitMiro\Client\MiroClient;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroBoardTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroItemTool;
use CarmeloSantana\PHPAgents\Enum\ToolResultStatus;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

test('miro_board create validates team id requirement', function () {
    $tool = (new MiroBoardTool(new MiroClient(new RestMiroBackend(new MiroAuthResolver(sys_get_temp_dir(), 'token-123')))))->build();

    $result = $tool->execute([
        'action' => 'create',
        'name' => 'Test Board',
    ]);

    expect($result->status)->toBe(ToolResultStatus::Error)
        ->and($result->content)->toContain('team_id');
});

test('miro_item create sends request for sticky note', function () {
    $capturedUrl = '';
    $capturedOptions = [];

    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedOptions): MockResponse {
        $capturedUrl = $url;
        $capturedOptions = $options;

        return new MockResponse(json_encode(['id' => 'item-123', 'type' => 'sticky_note']));
    });

    $client = new MiroClient(new RestMiroBackend(new MiroAuthResolver(sys_get_temp_dir(), 'token-123'), $httpClient));
    $tool = (new MiroItemTool($client))->build();

    $result = $tool->execute([
        'action' => 'create',
        'board_id' => 'board-1',
        'item_type' => 'sticky_note',
        'content' => 'hello',
    ]);

    $payload = [];
    if (isset($capturedOptions['json']) && is_array($capturedOptions['json'])) {
        $payload = $capturedOptions['json'];
    } elseif (isset($capturedOptions['body']) && is_string($capturedOptions['body'])) {
        $decoded = json_decode($capturedOptions['body'], true);
        $payload = is_array($decoded) ? $decoded : [];
    }

    expect($result->status)->toBe(ToolResultStatus::Success)
        ->and($capturedUrl)->toContain('/v2/boards/board-1/sticky_notes')
        ->and($payload['data']['content'] ?? null)->toBe('hello');
});