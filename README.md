# Coqui Toolkit Miro

Miro REST API toolkit for Coqui.

This package gives Coqui a REST-first integration for managing Miro boards through natural-language tool calls. It supports direct token authentication and OAuth, board CRUD, supported item CRUD, collaborator management, tags, frames, grouping, webhook registration, and board activity summaries.

## Package

- Package: `carmelosantana/coqui-toolkit-miro`
- Namespace: `CarmeloSantana\CoquiToolkitMiro\`
- PHP: `^8.4`

## Installation

```bash
composer require carmelosantana/coqui-toolkit-miro
```

## Authentication

The toolkit automatically prefers a direct token when one is configured.

Supported environment variables:

- `MIRO_ACCESS_TOKEN`
- `MIRO_API_TOKEN`
- `MIRO_CLIENT_ID`
- `MIRO_CLIENT_SECRET`
- `MIRO_DEFAULT_TEAM_ID`
- `MIRO_WEBHOOKS_BASE_PATH`

### Direct token mode

```bash
export MIRO_ACCESS_TOKEN="your-token"
```

### OAuth mode

```bash
export MIRO_CLIENT_ID="your-client-id"
export MIRO_CLIENT_SECRET="your-client-secret"
```

Then inside Coqui:

```txt
miro_auth(action: "status")
miro_auth(action: "login")
```

## Tools

- `miro_auth`
- `miro_board`
- `miro_item`
- `miro_frame`
- `miro_group`
- `miro_tag`
- `miro_collaborator`
- `miro_webhook`
- `miro_activity`

## Supported item types

- `sticky_note`
- `card`
- `shape`
- `text`
- `embed`
- `frame`
- `connector`

## Current scope

This first implementation pass is intentionally REST-first and avoids a runtime MCP dependency.

Included:

- board CRUD
- supported item CRUD
- frame management
- grouping flows
- tag management
- collaborator management
- webhook CRUD
- read-only board activity summaries

Not included yet:

- comment CRUD
- hosted webhook listener infrastructure
- image/document upload helpers
- runtime MCP fallback execution
- enterprise org and project administration

## Development

```bash
composer install
composer test
composer analyse
```# Coqui Toolkit Miro

Miro REST API toolkit for Coqui.

This package gives Coqui a REST-first integration for managing Miro boards through natural-language tool calls. It supports direct token authentication and OAuth, board CRUD, supported item CRUD, collaborator management, tags, frames, grouping, webhook registration, and board activity summaries.

## Package

- Package: `carmelosantana/coqui-toolkit-miro`
- Namespace: `CarmeloSantana\CoquiToolkitMiro\`
- PHP: `^8.4`

## Installation

```bash
composer require carmelosantana/coqui-toolkit-miro
```

## Authentication

The toolkit automatically prefers a direct token when one is configured.

Supported environment variables:

- `MIRO_ACCESS_TOKEN`
- `MIRO_API_TOKEN`
- `MIRO_CLIENT_ID`
- `MIRO_CLIENT_SECRET`
- `MIRO_DEFAULT_TEAM_ID`
- `MIRO_WEBHOOKS_BASE_PATH`

### Direct token mode

```bash
export MIRO_ACCESS_TOKEN="your-token"
```

### OAuth mode

```bash
export MIRO_CLIENT_ID="your-client-id"
export MIRO_CLIENT_SECRET="your-client-secret"
```

Then inside Coqui:

```txt
miro_auth(action: "status")
miro_auth(action: "login")
```

## Tools

- `miro_auth`
- `miro_board`
- `miro_item`
- `miro_frame`
- `miro_group`
- `miro_tag`
- `miro_collaborator`
- `miro_webhook`
- `miro_activity`

## Supported item types

- `sticky_note`
- `card`
- `shape`
- `text`
- `embed`
- `frame`
- `connector`

## Current scope

This first implementation pass is intentionally REST-first and avoids a runtime MCP dependency.

Included:

- board CRUD
- supported item CRUD
- frame management
- grouping flows
- tag management
- collaborator management
- webhook CRUD
- read-only board activity summaries

Not included yet:

- comment CRUD
- hosted webhook listener infrastructure
- image/document upload helpers
- runtime MCP fallback execution
- enterprise org and project administration

## Development

```bash
composer install
composer test
composer analyse
```