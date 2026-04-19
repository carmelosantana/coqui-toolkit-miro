<?php

declare(strict_types=1);

use CarmeloSantana\CoquiToolkitMiro\Auth\MiroAuthResolver;
use CarmeloSantana\CoquiToolkitMiro\Backend\RestMiroBackend;
use CarmeloSantana\CoquiToolkitMiro\Client\MiroClient;
use CarmeloSantana\CoquiToolkitMiro\MiroToolkit;
use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;

function createMiroTestToolkit(): MiroToolkit
{
    $auth = new MiroAuthResolver(sys_get_temp_dir(), 'test-token');
    $backend = new RestMiroBackend($auth);
    $client = new MiroClient($backend);

    return new MiroToolkit($client);
}

test('toolkit implements ToolkitInterface', function () {
    expect(createMiroTestToolkit())->toBeInstanceOf(ToolkitInterface::class);
});

test('tools returns all expected tools', function () {
    $names = array_map(fn(ToolInterface $tool): string => $tool->name(), createMiroTestToolkit()->tools());

    expect($names)->toHaveCount(9)
        ->and($names)->toContain('miro_auth')
        ->and($names)->toContain('miro_board')
        ->and($names)->toContain('miro_item')
        ->and($names)->toContain('miro_frame')
        ->and($names)->toContain('miro_group')
        ->and($names)->toContain('miro_tag')
        ->and($names)->toContain('miro_collaborator')
        ->and($names)->toContain('miro_webhook')
        ->and($names)->toContain('miro_activity');
});

test('tool names are unique and prefixed with miro_', function () {
    $tools = createMiroTestToolkit()->tools();
    $names = array_map(fn(ToolInterface $tool): string => $tool->name(), $tools);

    expect($names)->toHaveCount(count(array_unique($names)));

    foreach ($names as $name) {
        expect($name)->toStartWith('miro_');
    }
});

test('all tools generate valid function schemas', function () {
    foreach (createMiroTestToolkit()->tools() as $tool) {
        $schema = $tool->toFunctionSchema();

        expect($schema['type'])->toBe('function')
            ->and($schema['function']['name'])->toBeString()->not->toBeEmpty()
            ->and($schema['function']['description'])->toBeString()->not->toBeEmpty()
            ->and($schema['function']['parameters'])->toBeArray();
    }
});

test('guidelines mention auth and item limitations', function () {
    $guidelines = createMiroTestToolkit()->guidelines();

    expect($guidelines)->toContain('<MIRO-GUIDELINES>')
        ->and($guidelines)->toContain('miro_auth')
        ->and($guidelines)->toContain('miro_item')
        ->and($guidelines)->toContain('comments');
});