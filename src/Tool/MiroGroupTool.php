<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class MiroGroupTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_group',
            description: 'Manage Miro item groups — create groups from item IDs, inspect groups, update group membership, list grouped items, and ungroup.',
            parameters: [
                new EnumParameter('action', 'Group action to perform.', ['create', 'list', 'get_items', 'update', 'ungroup', 'delete'], true),
                new StringParameter('board_id', 'Board ID.', required: false),
                new StringParameter('group_id', 'Group ID.', required: false),
                new StringParameter('items_json', 'JSON array of item IDs.', required: false),
                new NumberParameter('limit', 'List limit.', required: false, integer: true, minimum: 1, maximum: 100),
                new StringParameter('payload_json', 'Optional raw JSON payload override.', required: false),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    /** @param array<string, mixed> $args */
    private function execute(array $args): ToolResult
    {
        $action = $this->text($args, 'action');

        return match ($action) {
            'create' => $this->create($args),
            'list' => $this->list($args),
            'get_items' => $this->getItems($args),
            'update' => $this->update($args),
            'ungroup', 'delete' => $this->ungroup($args),
            default => ToolResult::error('Unknown group action: ' . $action),
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
            $items = $this->stringList($args, 'items_json');
            if (count($items) < 2) {
                return ToolResult::error('Grouping requires at least two item IDs in items_json.');
            }

            $payload = ['data' => ['items' => $items]];
        }

        return $this->client->post('/v2/boards/' . rawurlencode($boardId) . '/groups', $payload)
            ->toToolResultWith('Group created:');
    }

    /** @param array<string, mixed> $args */
    private function list(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('board_id is required.');
        }

        return $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/groups', [], 'data', max(1, $this->int($args, 'limit', 50)))
            ->toToolResultWith('Groups:');
    }

    /** @param array<string, mixed> $args */
    private function getItems(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $groupId = $this->text($args, 'group_id');
        if ($boardId === '' || $groupId === '') {
            return ToolResult::error('board_id and group_id are required.');
        }

        return $this->client->get('/v2/boards/' . rawurlencode($boardId) . '/groups/' . rawurlencode($groupId) . '/items')
            ->toToolResultWith('Grouped items:');
    }

    /** @param array<string, mixed> $args */
    private function update(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $groupId = $this->text($args, 'group_id');
        if ($boardId === '' || $groupId === '') {
            return ToolResult::error('board_id and group_id are required.');
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $items = $this->stringList($args, 'items_json');
            if ($items === []) {
                return ToolResult::error('Provide items_json or payload_json to update a group.');
            }

            $payload = ['data' => ['items' => $items]];
        }

        return $this->client->patch('/v2/boards/' . rawurlencode($boardId) . '/groups/' . rawurlencode($groupId), $payload)
            ->toToolResultWith('Group updated:');
    }

    /** @param array<string, mixed> $args */
    private function ungroup(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $groupId = $this->text($args, 'group_id');
        if ($boardId === '' || $groupId === '') {
            return ToolResult::error('board_id and group_id are required.');
        }

        return $this->client->post('/v2/boards/' . rawurlencode($boardId) . '/groups/' . rawurlencode($groupId) . '/ungroup')
            ->toToolResultWith('Group removed.');
    }
}