<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\CoquiToolkitMiro\Support\MiroItemTypeMap;

final readonly class MiroItemTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_item',
            description: 'Manage supported Miro board items — create, list, get, update, and delete sticky notes, cards, shapes, text, embeds, frames, and connectors.',
            parameters: [
                new EnumParameter('action', 'Item action to perform.', ['create', 'list', 'get', 'update', 'delete'], true),
                new StringParameter('board_id', 'Board ID.', required: false),
                new StringParameter('item_id', 'Item ID for get, update, and delete.', required: false),
                new EnumParameter('item_type', 'Miro item type.', MiroItemTypeMap::supportedTypes(), required: false),
                new StringParameter('content', 'Primary textual content for sticky notes, shapes, and text items.', required: false),
                new StringParameter('title', 'Title for card or frame items.', required: false),
                new StringParameter('description', 'Description for card items.', required: false),
                new StringParameter('shape', 'Shape name for sticky notes or shape items.', required: false),
                new StringParameter('url', 'URL for embed items.', required: false),
                new StringParameter('source_item_id', 'Connector start item ID.', required: false),
                new StringParameter('target_item_id', 'Connector end item ID.', required: false),
                new NumberParameter('x', 'X position.', required: false),
                new NumberParameter('y', 'Y position.', required: false),
                new NumberParameter('width', 'Width.', required: false),
                new NumberParameter('height', 'Height.', required: false),
                new NumberParameter('limit', 'List limit.', required: false, integer: true, minimum: 1, maximum: 100),
                new StringParameter('style_json', 'Optional style JSON.', required: false),
                new StringParameter('tag_ids_json', 'Optional tag ID array JSON.', required: false),
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
            default => ToolResult::error('Unknown item action: ' . $action),
        };
    }

    /**
     * @param array<string, mixed> $args
     */
    private function create(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $itemType = $this->text($args, 'item_type');

        if ($boardId === '' || $itemType === '') {
            return ToolResult::error('board_id and item_type are required for item creation.');
        }

        $payload = $this->buildPayload($itemType, $args, false);

        return $this->client->post('/v2/boards/' . rawurlencode($boardId) . '/' . $this->itemSegment($itemType), $payload)
            ->toToolResultWith('Item created:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function list(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('The board_id parameter is required.');
        }

        $query = [];
        $itemType = $this->text($args, 'item_type');
        if ($itemType !== '') {
            $query['type'] = $itemType;
        }

        return $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/items', $query, 'data', max(1, $this->int($args, 'limit', 50)))
            ->toToolResultWith('Items:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function get(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $itemId = $this->text($args, 'item_id');

        if ($boardId === '' || $itemId === '') {
            return ToolResult::error('board_id and item_id are required.');
        }

        $itemType = $this->text($args, 'item_type');
        $endpoint = $itemType !== ''
            ? '/v2/boards/' . rawurlencode($boardId) . '/' . $this->itemSegment($itemType) . '/' . rawurlencode($itemId)
            : '/v2/boards/' . rawurlencode($boardId) . '/items/' . rawurlencode($itemId);

        return $this->client->get($endpoint)
            ->toToolResultWith('Item details:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function update(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $itemId = $this->text($args, 'item_id');
        $itemType = $this->text($args, 'item_type');

        if ($boardId === '' || $itemId === '') {
            return ToolResult::error('board_id and item_id are required.');
        }

        $payload = $this->buildPayload($itemType, $args, true);
        if ($payload === []) {
            return ToolResult::error('Provide at least one field to update or use payload_json.');
        }

        $endpoint = $itemType !== ''
            ? '/v2/boards/' . rawurlencode($boardId) . '/' . $this->itemSegment($itemType) . '/' . rawurlencode($itemId)
            : '/v2/boards/' . rawurlencode($boardId) . '/items/' . rawurlencode($itemId);

        return $this->client->patch($endpoint, $payload)
            ->toToolResultWith('Item updated:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function delete(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        $itemId = $this->text($args, 'item_id');

        if ($boardId === '' || $itemId === '') {
            return ToolResult::error('board_id and item_id are required.');
        }

        return $this->client->delete('/v2/boards/' . rawurlencode($boardId) . '/items/' . rawurlencode($itemId))
            ->toToolResultWith('Item deleted.');
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function buildPayload(string $itemType, array $args, bool $forUpdate): array
    {
        $override = $this->payloadOverride($args);
        if ($override !== []) {
            return $override;
        }

        $payload = [];
        $data = [];
        $style = $this->decodeOptionalObject($args, 'style_json');
        $position = $this->buildPosition($this->float($args, 'x'), $this->float($args, 'y'));
        $geometry = $this->buildGeometry($this->float($args, 'width'), $this->float($args, 'height'));
        $tagIds = $this->stringList($args, 'tag_ids_json');

        if ($itemType === '' && $forUpdate) {
            $this->maybeSet($payload, 'position', $position);
            $this->maybeSet($payload, 'geometry', $geometry);

            return $payload;
        }

        switch ($itemType) {
            case 'sticky_note':
                $this->maybeSet($data, 'content', $this->text($args, 'content'));
                $this->maybeSet($data, 'shape', $this->text($args, 'shape'));
                break;
            case 'card':
                $this->maybeSet($data, 'title', $this->text($args, 'title'));
                $this->maybeSet($data, 'description', $this->text($args, 'description'));
                break;
            case 'shape':
                $this->maybeSet($data, 'content', $this->text($args, 'content'));
                $this->maybeSet($data, 'shape', $this->text($args, 'shape'));
                break;
            case 'text':
                $this->maybeSet($data, 'content', $this->text($args, 'content'));
                break;
            case 'embed':
                $this->maybeSet($data, 'url', $this->text($args, 'url'));
                break;
            case 'frame':
                $this->maybeSet($data, 'title', $this->text($args, 'title'));
                break;
            case 'connector':
                $sourceItemId = $this->text($args, 'source_item_id');
                $targetItemId = $this->text($args, 'target_item_id');
                if ($sourceItemId === '' || $targetItemId === '') {
                    throw new \InvalidArgumentException('Connector creation requires source_item_id and target_item_id.');
                }

                $payload['startItem'] = ['id' => $sourceItemId];
                $payload['endItem'] = ['id' => $targetItemId];
                break;
        }

        $this->maybeSet($payload, 'data', $data);
        $this->maybeSet($payload, 'style', $style);
        $this->maybeSet($payload, 'position', $position);
        $this->maybeSet($payload, 'geometry', $geometry);
        $this->maybeSet($payload, 'tagIds', $tagIds);

        return $payload;
    }
}