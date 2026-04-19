<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\CoquiToolkitMiro\Support\Json;

final readonly class MiroActivityTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_activity',
            description: 'Generate read-only board activity and inventory insights from accessible Miro REST data.',
            parameters: [
                new EnumParameter('action', 'Insight action to perform.', ['summary'], true),
                new StringParameter('board_id', 'Board ID to summarize.', required: false),
                new NumberParameter('limit', 'Maximum number of items to inspect.', required: false, integer: true, minimum: 1, maximum: 200),
            ],
            callback: fn(array $args): ToolResult => $this->execute($args),
        );
    }

    /** @param array<string, mixed> $args */
    private function execute(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('board_id is required.');
        }

        $limit = max(1, $this->int($args, 'limit', 100));
        $board = $this->client->get('/v2/boards/' . rawurlencode($boardId));
        if (!$board->success) {
            return ToolResult::error($board->message);
        }

        $items = $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/items', [], 'data', $limit);
        $members = $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/members', [], 'data', 100);
        $tags = $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/tags', [], 'data', 100);
        $webhooks = $this->client->get($this->webhookCollectionPath(), ['boardId' => $boardId, 'limit' => 100]);

        $summary = [
            'board' => [
                'id' => $board->data['id'] ?? $boardId,
                'name' => $board->data['name'] ?? $board->data['title'] ?? null,
                'description' => $board->data['description'] ?? null,
                'createdAt' => $board->data['createdAt'] ?? null,
                'modifiedAt' => $board->data['modifiedAt'] ?? null,
            ],
            'counts' => [
                'items' => 0,
                'members' => 0,
                'tags' => 0,
                'webhooks' => 0,
            ],
            'item_types' => [],
            'items_sample' => [],
            'warnings' => [],
        ];

        if ($items->success) {
            $itemData = is_array($items->data['data'] ?? null) ? $items->data['data'] : [];
            $summary['counts']['items'] = count($itemData);
            $summary['item_types'] = $this->countItemTypes($itemData);
            $summary['items_sample'] = array_map(fn(array $item): array => $this->summarizeItem($item), array_slice($itemData, 0, 10));
        } else {
            $summary['warnings'][] = $items->message;
        }

        if ($members->success) {
            $memberData = is_array($members->data['data'] ?? null) ? $members->data['data'] : [];
            $summary['counts']['members'] = count($memberData);
        } else {
            $summary['warnings'][] = $members->message;
        }

        if ($tags->success) {
            $tagData = is_array($tags->data['data'] ?? null) ? $tags->data['data'] : [];
            $summary['counts']['tags'] = count($tagData);
        } else {
            $summary['warnings'][] = $tags->message;
        }

        if ($webhooks->success) {
            $webhookData = $webhooks->data['data'] ?? $webhooks->data['items'] ?? [];
            if (is_array($webhookData)) {
                $summary['counts']['webhooks'] = count($webhookData);
            }
        } else {
            $summary['warnings'][] = $webhooks->message;
        }

        return ToolResult::success("Board summary:\n" . Json::encodePretty($summary));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function countItemTypes(array $items): array
    {
        $counts = [];

        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? 'unknown');
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function summarizeItem(array $item): array
    {
        $data = is_array($item['data'] ?? null) ? $item['data'] : [];

        return [
            'id' => $item['id'] ?? null,
            'type' => $item['type'] ?? null,
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
        ];
    }
}