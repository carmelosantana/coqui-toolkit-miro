<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class MiroBoardTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_board',
            description: 'Manage Miro boards — create, list, inspect, update, delete, and copy boards.',
            parameters: [
                new EnumParameter('action', 'Board action to perform.', ['create', 'list', 'get', 'update', 'delete', 'copy'], true),
                new StringParameter('board_id', 'Board ID for get, update, delete, and copy.', required: false),
                new StringParameter('team_id', 'Team ID used for board creation.', required: false),
                new StringParameter('name', 'Board name.', required: false),
                new StringParameter('description', 'Board description.', required: false),
                new StringParameter('query', 'Board search query for list.', required: false),
                new StringParameter('owner', 'Owner user ID filter for list.', required: false),
                new StringParameter('sort', 'Sort expression for list.', required: false),
                new NumberParameter('limit', 'List limit.', required: false, integer: true, minimum: 1, maximum: 100),
                new StringParameter('policy_json', 'Optional board policy JSON for create or update.', required: false),
                new StringParameter('payload_json', 'Optional raw JSON payload override for advanced create or update requests.', required: false),
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
            'copy' => $this->copy($args),
            default => ToolResult::error('Unknown board action: ' . $action),
        };
    }

    /**
     * @param array<string, mixed> $args
     */
    private function create(array $args): ToolResult
    {
        $teamId = $this->text($args, 'team_id');
        if ($teamId === '') {
            $teamId = $this->defaultTeamId();
        }
        if ($teamId === '') {
            return ToolResult::error('A team_id is required to create a Miro board. Configure MIRO_DEFAULT_TEAM_ID or pass team_id explicitly.');
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $name = $this->text($args, 'name');
            if ($name === '') {
                return ToolResult::error('The name parameter is required to create a board.');
            }

            $payload = [
                'name' => $name,
                'policy' => $this->decodeOptionalObject($args, 'policy_json') ?: $this->defaultPolicy(),
            ];

            $this->maybeSet($payload, 'description', $this->text($args, 'description'));
        }

        return $this->client->post('/v2/boards', $payload, ['teamId' => $teamId])
            ->toToolResultWith('Board created:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function list(array $args): ToolResult
    {
        $query = [
            'team_id' => $this->text($args, 'team_id'),
            'query' => $this->text($args, 'query'),
            'owner' => $this->text($args, 'owner'),
            'sort' => $this->text($args, 'sort'),
        ];

        return $this->client->paginateOffset('/v2/boards', $query, 'data', max(1, $this->int($args, 'limit', 25)))
            ->toToolResultWith('Boards:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function get(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('The board_id parameter is required.');
        }

        return $this->client->get('/v2/boards/' . rawurlencode($boardId))
            ->toToolResultWith('Board details:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function update(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('The board_id parameter is required.');
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $payload = [];
            $this->maybeSet($payload, 'name', $this->text($args, 'name'));
            $this->maybeSet($payload, 'description', $this->text($args, 'description'));
            $policy = $this->decodeOptionalObject($args, 'policy_json');
            $this->maybeSet($payload, 'policy', $policy);
        }

        if ($payload === []) {
            return ToolResult::error('Provide at least one field to update, or use payload_json.');
        }

        return $this->client->patch('/v2/boards/' . rawurlencode($boardId), $payload)
            ->toToolResultWith('Board updated:');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function delete(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('The board_id parameter is required.');
        }

        return $this->client->delete('/v2/boards/' . rawurlencode($boardId))
            ->toToolResultWith('Board deleted.');
    }

    /**
     * @param array<string, mixed> $args
     */
    private function copy(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('The board_id parameter is required.');
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $payload = [];
            $this->maybeSet($payload, 'name', $this->text($args, 'name'));
            $this->maybeSet($payload, 'description', $this->text($args, 'description'));
        }

        return $this->client->post('/v2/boards/' . rawurlencode($boardId) . '/copy', $payload)
            ->toToolResultWith('Board copy created:');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPolicy(): array
    {
        return [
            'permissionsPolicy' => [
                'collaborationToolsStartAccess' => 'all_editors',
                'copyAccess' => 'anyone',
                'sharingAccess' => 'team_members_with_editing_rights',
            ],
            'sharingPolicy' => [
                'access' => 'private',
                'inviteToAccountAndBoardLinkAccess' => 'no_access',
                'organizationAccess' => 'private',
                'teamAccess' => 'private',
            ],
        ];
    }
}