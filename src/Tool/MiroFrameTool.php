<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class MiroFrameTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_frame',
            description: 'Manage Miro frames — create, list, inspect, update, delete, and inspect items within a frame.',
            parameters: [
                new EnumParameter('action', 'Frame action to perform.', ['create', 'list', 'get', 'update', 'delete', 'list_items'], true),
                new StringParameter('board_id', 'Board ID.', required: false),
                new StringParameter('frame_id', 'Frame ID for get, update, delete, and list_items.', required: false),
                new StringParameter('title', 'Frame title.', required: false),
                new NumberParameter('x', 'X position.', required: false),
                new NumberParameter('y', 'Y position.', required: false),
                new NumberParameter('width', 'Width.', required: false),
                new NumberParameter('height', 'Height.', required: false),
                new NumberParameter('limit', 'List limit.', required: false, integer: true, minimum: 1, maximum: 100),
                new StringParameter('style_json', 'Optional frame style JSON.', required: false),
                new StringParameter('payload_json', 'Optional raw JSON payload override.', required: false),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    /**
     * @param array<string, mixed> $args
     */
    private function execute(array $args): ToolResult
    {
        $action = $this->text($args, 'action');

        return match ($action) {
            'create' => $this->create($args),
            'list' => $this->list($args),
            'get' => $this->get($args),
            'update' => $this->update($args),
            'delete' => $this->delete($args),
            'list_items' => $this->listItems($args),
            default => ToolResult::error('Unknown frame action: ' . $action),
        };
    }

    /** @param array<string, mixed> $args */
    private function create(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('board_id is required.');
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $data = [];
            $this->maybeSet($data, 'title', $this->text($args, 'title'));
            $payload = [];
            $this->maybeSet($payload, 'data', $data);
            $this->maybeSet($payload, 'position', $this->buildPosition($this->float($args, 'x'), $this->float($args, 'y')));
            $this->maybeSet($payload, 'geometry', $this->buildGeometry($this->float($args, 'width'), $this->float($args, 'height')));
            $this->maybeSet($payload, 'style', $this->decodeOptionalObject($args, 'style_json'));
        }

        return $this->client->post('/v2/boards/' . rawurlencode($boardId) . '/frames', $payload)
            ->toToolResultWith('Frame created:');
    }

    /** @param array<string, mixed> $args */
    private function list(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('board_id is required.');
        }

        return $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/items', ['type' => 'frame'], 'data', max(1, $this->int($args, 'limit', 50)))
            ->toToolResultWith('Frames:');
    }

    /** @param array<string, mixed> $args */
    private function get(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $frameId = $this->text($args, 'frame_id');
        if ($boardId === '' || $frameId === '') {
            return ToolResult::error('board_id and frame_id are required.');
        }

        return $this->client->get('/v2/boards/' . rawurlencode($boardId) . '/frames/' . rawurlencode($frameId))
            ->toToolResultWith('Frame details:');
    }

    /** @param array<string, mixed> $args */
    private function update(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $frameId = $this->text($args, 'frame_id');
        if ($boardId === '' || $frameId === '') {
            return ToolResult::error('board_id and frame_id are required.');
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $data = [];
            $this->maybeSet($data, 'title', $this->text($args, 'title'));
            $payload = [];
            $this->maybeSet($payload, 'data', $data);
            $this->maybeSet($payload, 'position', $this->buildPosition($this->float($args, 'x'), $this->float($args, 'y')));
            $this->maybeSet($payload, 'geometry', $this->buildGeometry($this->float($args, 'width'), $this->float($args, 'height')));
            $this->maybeSet($payload, 'style', $this->decodeOptionalObject($args, 'style_json'));
        }

        if ($payload === []) {
            return ToolResult::error('Provide at least one field to update or use payload_json.');
        }

        return $this->client->patch('/v2/boards/' . rawurlencode($boardId) . '/frames/' . rawurlencode($frameId), $payload)
            ->toToolResultWith('Frame updated:');
    }

    /** @param array<string, mixed> $args */
    private function delete(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $frameId = $this->text($args, 'frame_id');
        if ($boardId === '' || $frameId === '') {
            return ToolResult::error('board_id and frame_id are required.');
        }

        return $this->client->delete('/v2/boards/' . rawurlencode($boardId) . '/frames/' . rawurlencode($frameId))
            ->toToolResultWith('Frame deleted.');
    }

    /** @param array<string, mixed> $args */
    private function listItems(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $frameId = $this->text($args, 'frame_id');
        if ($boardId === '' || $frameId === '') {
            return ToolResult::error('board_id and frame_id are required.');
        }

        return $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/frames/' . rawurlencode($frameId) . '/items', [], 'data', max(1, $this->int($args, 'limit', 50)))
            ->toToolResultWith('Frame items:');
    }
}