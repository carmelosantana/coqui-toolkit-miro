<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\NumberParameter;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class MiroWebhookTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_webhook',
            description: 'Manage Miro board webhook subscriptions — create, list, inspect, update, and delete webhook registrations.',
            parameters: [
                new EnumParameter('action', 'Webhook action to perform.', ['create', 'list', 'get', 'update', 'delete'], true),
                new StringParameter('subscription_id', 'Webhook subscription ID.', required: false),
                new StringParameter('board_id', 'Board ID associated with the subscription.', required: false),
                new StringParameter('callback_url', 'HTTPS callback URL for webhook delivery.', required: false),
                new EnumParameter('status', 'Webhook status.', ['enabled', 'disabled', 'lost_access'], required: false),
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
            default => ToolResult::error('Unknown webhook action: ' . $action),
        };
    }

    /** @param array<string, mixed> $args */
    private function create(array $args): ToolResult
    {
        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $boardId = $this->text($args, 'board_id');
            $callbackUrl = $this->text($args, 'callback_url');

            if ($boardId === '' || $callbackUrl === '') {
                return ToolResult::error('board_id and callback_url are required to create a webhook.');
            }

            $payload = [
                'boardId' => $boardId,
                'callbackUrl' => $callbackUrl,
                'status' => $this->text($args, 'status') !== '' ? $this->text($args, 'status') : 'enabled',
            ];
        }

        return $this->client->post($this->webhookCollectionPath(), $payload)
            ->toToolResultWith('Webhook subscription created:');
    }

    /** @param array<string, mixed> $args */
    private function list(array $args): ToolResult
    {
        $query = [];
        $boardId = $this->text($args, 'board_id');
        if ($boardId !== '') {
            $query['boardId'] = $boardId;
        }

        $limit = $this->int($args, 'limit', 50);
        if ($limit > 0) {
            $query['limit'] = $limit;
        }

        return $this->client->get($this->webhookCollectionPath(), $query)
            ->toToolResultWith('Webhook subscriptions:');
    }

    /** @param array<string, mixed> $args */
    private function get(array $args): ToolResult
    {
        $subscriptionId = $this->text($args, 'subscription_id');
        if ($subscriptionId === '') {
            return ToolResult::error('subscription_id is required.');
        }

        return $this->client->get($this->webhookCollectionPath() . '/' . rawurlencode($subscriptionId))
            ->toToolResultWith('Webhook subscription details:');
    }

    /** @param array<string, mixed> $args */
    private function update(array $args): ToolResult
    {
        $subscriptionId = $this->text($args, 'subscription_id');
        if ($subscriptionId === '') {
            return ToolResult::error('subscription_id is required.');
        }

        $payload = $this->payloadOverride($args);
        if ($payload === []) {
            $payload = [];
            $this->maybeSet($payload, 'callbackUrl', $this->text($args, 'callback_url'));
            $status = $this->text($args, 'status');
            if ($status !== '' && $status !== 'lost_access') {
                $payload['status'] = $status;
            }
        }

        if ($payload === []) {
            return ToolResult::error('Provide callback_url, status, or payload_json to update a webhook.');
        }

        return $this->client->patch($this->webhookCollectionPath() . '/' . rawurlencode($subscriptionId), $payload)
            ->toToolResultWith('Webhook subscription updated:');
    }

    /** @param array<string, mixed> $args */
    private function delete(array $args): ToolResult
    {
        $subscriptionId = $this->text($args, 'subscription_id');
        if ($subscriptionId === '') {
            return ToolResult::error('subscription_id is required.');
        }

        return $this->client->delete($this->webhookCollectionPath() . '/' . rawurlencode($subscriptionId))
            ->toToolResultWith('Webhook subscription deleted.');
    }
}