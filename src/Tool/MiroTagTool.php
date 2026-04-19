<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class MiroTagTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_tag',
            description: 'Manage Miro tags — create, list, inspect, update, delete, and attach or remove tags from supported items.',
            parameters: [
                new EnumParameter('action', 'Tag action to perform.', ['create', 'list', 'get', 'update', 'delete', 'attach_to_item', 'remove_from_item', 'list_item_tags', 'list_items'], true),
                new StringParameter('board_id', 'Board ID.', required: false),
                new StringParameter('tag_id', 'Tag ID.', required: false),
                new StringParameter('item_id', 'Item ID.', required: false),
                new StringParameter('title', 'Tag title.', required: false),
                new StringParameter('color', 'Tag color.', required: false),
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
            'get' => $this->get($args),
            'update' => $this->update($args),
            'delete' => $this->delete($args),
            'attach_to_item' => $this->attachToItem($args),
            'remove_from_item' => $this->removeFromItem($args),
            'list_item_tags' => $this->listItemTags($args),
            'list_items' => $this->listItems($args),
            default => ToolResult::error('Unknown tag action: ' . $action),
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
            $title = $this->text($args, 'title');
            if ($title === '') {
                return ToolResult::error('title is required to create a tag.');
            }

            $payload = ['title' => $title];
            $this->maybeSet($payload, 'color', $this->text($args, 'color'));
        }

        return $this->client->post('/v2/boards/' . rawurlencode($boardId) . '/tags', $payload)
            ->toToolResultWith('Tag created:');
    }

    /** @param array<string, mixed> $args */
    private function list(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('board_id is required.');
        }

        return $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/tags', [], 'data', max(1, $this->int($args, 'limit', 50)))
            ->toToolResultWith('Tags:');
    }

    /** @param array<string, mixed> $args */
    private function get(array $args): ToolResult
    {
        [$boardId, $tagId, $error] = $this->requireBoardAndTag($args);
        if ($error !== null) {
            return $error;
        }

        return $this->client->get('/v2/boards/' . rawurlencode($boardId) . '/tags/' . rawurlencode($tagId))
            ->toToolResultWith('Tag details:');
    }

    /** @param array<string, mixed> $args */
    private function update(array $args): ToolResult
    {
        [$boardId, $tagId, $error] = $this->requireBoardAndTag($args);
        if ($error !== null) {
            return $error;
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $payload = [];
            $this->maybeSet($payload, 'title', $this->text($args, 'title'));
            $this->maybeSet($payload, 'color', $this->text($args, 'color'));
        }

        if ($payload === []) {
            return ToolResult::error('Provide title, color, or payload_json to update a tag.');
        }

        return $this->client->patch('/v2/boards/' . rawurlencode($boardId) . '/tags/' . rawurlencode($tagId), $payload)
            ->toToolResultWith('Tag updated:');
    }

    /** @param array<string, mixed> $args */
    private function delete(array $args): ToolResult
    {
        [$boardId, $tagId, $error] = $this->requireBoardAndTag($args);
        if ($error !== null) {
            return $error;
        }

        return $this->client->delete('/v2/boards/' . rawurlencode($boardId) . '/tags/' . rawurlencode($tagId))
            ->toToolResultWith('Tag deleted.');
    }

    /** @param array<string, mixed> $args */
    private function attachToItem(array $args): ToolResult
    {
        [$boardId, $tagId, $itemId, $error] = $this->requireBoardTagItem($args);
        if ($error !== null) {
            return $error;
        }

        return $this->client->post('/v2/boards/' . rawurlencode($boardId) . '/items/' . rawurlencode($itemId) . '/tags/' . rawurlencode($tagId))
            ->toToolResultWith('Tag attached to item.');
    }

    /** @param array<string, mixed> $args */
    private function removeFromItem(array $args): ToolResult
    {
        [$boardId, $tagId, $itemId, $error] = $this->requireBoardTagItem($args);
        if ($error !== null) {
            return $error;
        }

        return $this->client->delete('/v2/boards/' . rawurlencode($boardId) . '/items/' . rawurlencode($itemId) . '/tags/' . rawurlencode($tagId))
            ->toToolResultWith('Tag removed from item.');
    }

    /** @param array<string, mixed> $args */
    private function listItemTags(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $itemId = $this->text($args, 'item_id');
        if ($boardId === '' || $itemId === '') {
            return ToolResult::error('board_id and item_id are required.');
        }

        return $this->client->get('/v2/boards/' . rawurlencode($boardId) . '/items/' . rawurlencode($itemId) . '/tags')
            ->toToolResultWith('Item tags:');
    }

    /** @param array<string, mixed> $args */
    private function listItems(array $args): ToolResult
    {
        [$boardId, $tagId, $error] = $this->requireBoardAndTag($args);
        if ($error !== null) {
            return $error;
        }

        return $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/tags/' . rawurlencode($tagId) . '/items', [], 'data', max(1, $this->int($args, 'limit', 50)))
            ->toToolResultWith('Items with tag:');
    }

    /**
     * @param array<string, mixed> $args
     * @return array{0:string,1:string,2:?ToolResult}
     */
    private function requireBoardAndTag(array $args): array
    {
        $boardId = $this->text($args, 'board_id');
        $tagId = $this->text($args, 'tag_id');

        if ($boardId === '' || $tagId === '') {
            return ['', '', ToolResult::error('board_id and tag_id are required.')];
        }

        return [$boardId, $tagId, null];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{0:string,1:string,2:string,3:?ToolResult}
     */
    private function requireBoardTagItem(array $args): array
    {
        [$boardId, $tagId, $error] = $this->requireBoardAndTag($args);
        if ($error !== null) {
            return ['', '', '', $error];
        }

        $itemId = $this->text($args, 'item_id');
        if ($itemId === '') {
            return ['', '', '', ToolResult::error('item_id is required.')];
        }

        return [$boardId, $tagId, $itemId, null];
    }
}