<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class MiroCollaboratorTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_collaborator',
            description: 'Manage Miro board collaborators — list members, inspect a member, invite members, update a role, and remove board access.',
            parameters: [
                new EnumParameter('action', 'Collaborator action to perform.', ['list', 'get', 'add', 'update', 'remove'], true),
                new StringParameter('board_id', 'Board ID.', required: false),
                new StringParameter('member_id', 'Board member ID.', required: false),
                new StringParameter('emails_json', 'JSON array of collaborator email addresses for add.', required: false),
                new StringParameter('role', 'Collaborator role.', required: false),
                new StringParameter('message', 'Optional invitation message.', required: false),
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
            'list' => $this->list($args),
            'get' => $this->get($args),
            'add' => $this->add($args),
            'update' => $this->update($args),
            'remove' => $this->remove($args),
            default => ToolResult::error('Unknown collaborator action: ' . $action),
        };
    }

    /** @param array<string, mixed> $args */
    private function list(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('board_id is required.');
        }

        return $this->client->paginateOffset('/v2/boards/' . rawurlencode($boardId) . '/members', [], 'data', max(1, $this->int($args, 'limit', 50)))
            ->toToolResultWith('Board collaborators:');
    }

    /** @param array<string, mixed> $args */
    private function get(array $args): ToolResult
    {
        [$boardId, $memberId, $error] = $this->requireBoardAndMember($args);
        if ($error !== null) {
            return $error;
        }

        return $this->client->get('/v2/boards/' . rawurlencode($boardId) . '/members/' . rawurlencode($memberId))
            ->toToolResultWith('Collaborator details:');
    }

    /** @param array<string, mixed> $args */
    private function add(array $args): ToolResult
    {
        $boardId = $this->text($args, 'board_id');
        if ($boardId === '') {
            return ToolResult::error('board_id is required.');
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $emails = $this->stringList($args, 'emails_json');
            $role = $this->text($args, 'role');
            if ($emails === [] || $role === '') {
                return ToolResult::error('emails_json and role are required to add collaborators.');
            }

            $payload = [
                'emails' => $emails,
                'role' => $role,
            ];
            $this->maybeSet($payload, 'message', $this->text($args, 'message'));
        }

        return $this->client->post('/v2/boards/' . rawurlencode($boardId) . '/members', $payload)
            ->toToolResultWith('Collaborators added:');
    }

    /** @param array<string, mixed> $args */
    private function update(array $args): ToolResult
    {
        [$boardId, $memberId, $error] = $this->requireBoardAndMember($args);
        if ($error !== null) {
            return $error;
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $role = $this->text($args, 'role');
            if ($role === '') {
                return ToolResult::error('role or payload_json is required to update a collaborator.');
            }

            $payload = ['role' => $role];
        }

        return $this->client->patch('/v2/boards/' . rawurlencode($boardId) . '/members/' . rawurlencode($memberId), $payload)
            ->toToolResultWith('Collaborator updated:');
    }

    /** @param array<string, mixed> $args */
    private function remove(array $args): ToolResult
    {
        [$boardId, $memberId, $error] = $this->requireBoardAndMember($args);
        if ($error !== null) {
            return $error;
        }

        return $this->client->delete('/v2/boards/' . rawurlencode($boardId) . '/members/' . rawurlencode($memberId))
            ->toToolResultWith('Collaborator removed.');
    }

    /**
     * @param array<string, mixed> $args
     * @return array{0:string,1:string,2:?ToolResult}
     */
    private function requireBoardAndMember(array $args): array
    {
        $boardId = $this->text($args, 'board_id');
        $memberId = $this->text($args, 'member_id');

        if ($boardId === '' || $memberId === '') {
            return ['', '', ToolResult::error('board_id and member_id are required.')];
        }

        return [$boardId, $memberId, null];
    }
}