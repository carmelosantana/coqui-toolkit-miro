<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro;

use CarmeloSantana\CoquiToolkitMiro\Client\MiroClient;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroActivityTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroAuthTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroBoardTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroCollaboratorTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroFrameTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroGroupTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroItemTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroTagTool;
use CarmeloSantana\CoquiToolkitMiro\Tool\MiroWebhookTool;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;

final class MiroToolkit implements ToolkitInterface
{
    private readonly MiroClient $client;

    public function __construct(
        ?MiroClient $client = null,
        string $workspacePath = '',
    ) {
        $workspace = $workspacePath !== '' ? $workspacePath : (string) (getenv('COQUI_WORKSPACE') ?: getcwd());
        $this->client = $client ?? MiroClient::fromEnv($workspace);
    }

    /**
     * @return array<\CarmeloSantana\PHPAgents\Contract\ToolInterface>
     */
    public function tools(): array
    {
        return [
            (new MiroAuthTool($this->client))->build(),
            (new MiroBoardTool($this->client))->build(),
            (new MiroItemTool($this->client))->build(),
            (new MiroFrameTool($this->client))->build(),
            (new MiroGroupTool($this->client))->build(),
            (new MiroTagTool($this->client))->build(),
            (new MiroCollaboratorTool($this->client))->build(),
            (new MiroWebhookTool($this->client))->build(),
            (new MiroActivityTool($this->client))->build(),
        ];
    }

    public function guidelines(): string
    {
        return <<<'GUIDELINES'
        <MIRO-GUIDELINES>
        ## Miro Toolkit

        You can manage Miro boards through a REST-first toolkit that supports both direct token authentication and OAuth.

        ### Tool Overview
        - **miro_auth** — inspect auth source, login with OAuth, logout, and check status
        - **miro_board** — create, list, get, update, delete, and copy boards
        - **miro_item** — create, list, get, update, and delete supported board items
        - **miro_frame** — create, inspect, update, delete frames, and list items within a frame
        - **miro_group** — group items, inspect groups, update group membership, and ungroup
        - **miro_tag** — create, list, update, delete tags and attach/remove them from items
        - **miro_collaborator** — list members, invite members, update roles, and remove access
        - **miro_webhook** — create, list, inspect, update, and delete board webhook subscriptions
        - **miro_activity** — generate read-only board inventory and collaboration summaries

        ### Authentication Flow
        1. Check auth status first with `miro_auth(action: "status")`
        2. If a direct token is configured, the toolkit uses it automatically
        3. Otherwise run `miro_auth(action: "login")` when OAuth client credentials are configured

        ### Usage Guidance
        - Prefer board-scoped workflows. Start by identifying a board, then manage its items and collaborators.
        - Use `miro_item` for sticky notes, cards, shapes, text, embeds, frames, and connectors.
        - Use `miro_tag` for item tagging rather than embedding tag logic into general item calls.
        - Use `miro_activity` for inventory and collaboration insights after making structural changes.

        ### Important Notes
        - This toolkit is REST-first. It is designed so an internal MCP-backed implementation can be added later without changing the user-facing tool names.
        - Miro comments are not currently exposed here because REST support is limited.
        - Some Miro endpoints are plan-gated or evolving. When the API reports a capability limitation, surface that limitation rather than guessing.
        - Delete, remove, and ungroup actions are destructive and should only be used when the intent is clear.
        </MIRO-GUIDELINES>
        GUIDELINES;
    }
}