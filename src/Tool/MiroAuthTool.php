<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Tool\Parameter\EnumParameter;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;

final readonly class MiroAuthTool extends AbstractMiroTool
{
    public function build(): ToolInterface
    {
        return new Tool(
            name: 'miro_auth',
            description: 'Manage Miro authentication — login with OAuth, inspect current status, logout, or inspect the active auth source.',
            parameters: [
                new EnumParameter(
                    'action',
                    'Authentication action to perform.',
                    values: ['login', 'status', 'logout', 'source'],
                    required: true,
                ),
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
            'login' => $this->login(),
            'status' => ToolResult::success($this->client->auth()->status()),
            'logout' => ToolResult::success($this->client->auth()->logout()),
            'source' => ToolResult::success('Active Miro auth source: ' . $this->client->auth()->source()),
            default => ToolResult::error('Unknown auth action: ' . $action),
        };
    }

    private function login(): ToolResult
    {
        try {
            $tokens = $this->client->auth()->login();

            return ToolResult::success('Successfully authenticated with Miro.\n' . json_encode([
                'source' => 'oauth',
                'has_refresh_token' => isset($tokens['refresh_token']),
                'scope' => $tokens['scope'] ?? null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            return ToolResult::error('Miro OAuth login failed: ' . $e->getMessage());
        }
    }
}