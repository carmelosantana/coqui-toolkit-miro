<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Tool;

use CarmeloSantana\CoquiToolkitMiro\Client\MiroClient;
use CarmeloSantana\CoquiToolkitMiro\Support\Json;
use CarmeloSantana\CoquiToolkitMiro\Support\MiroItemTypeMap;

abstract readonly class AbstractMiroTool
{
    public function __construct(
        protected MiroClient $client,
    ) {}

    /**
     * @param array<string, mixed> $args
     */
    protected function text(array $args, string $key): string
    {
        return trim((string) ($args[$key] ?? ''));
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function int(array $args, string $key, int $default = 0): int
    {
        return isset($args[$key]) ? (int) $args[$key] : $default;
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function float(array $args, string $key, float $default = 0.0): float
    {
        return isset($args[$key]) ? (float) $args[$key] : $default;
    }

    /**
     * @param array<string, mixed> $args
     * @return array<int, mixed>|array<string, mixed>
     */
    protected function json(array $args, string $key): array
    {
        $value = $this->text($args, $key);

        return $value !== '' ? Json::decodeObject($value) : [];
    }

    /**
     * @param array<string, mixed> $args
     * @return list<string>
     */
    protected function stringList(array $args, string $key): array
    {
        $decoded = $this->json($args, $key);

        return array_map(
            static fn(mixed $item): string => (string) $item,
            array_values($decoded),
        );
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    protected function payloadOverride(array $args): array
    {
        $payload = $this->json($args, 'payload_json');
        if ($payload === [] || array_is_list($payload)) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    protected function decodeOptionalObject(array $args, string $key): array
    {
        $value = $this->text($args, $key);

        if ($value === '') {
            return [];
        }

        $decoded = Json::decodeObject($value);
        if (array_is_list($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPosition(float $x, float $y): array
    {
        if ($x === 0.0 && $y === 0.0) {
            return [];
        }

        return ['x' => $x, 'y' => $y];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildGeometry(float $width, float $height): array
    {
        if ($width <= 0 || $height <= 0) {
            return [];
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function maybeSet(array &$payload, string $key, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $payload[$key] = $value;
    }

    protected function itemSegment(string $itemType): string
    {
        $segment = MiroItemTypeMap::endpoint($itemType);
        if ($segment === null) {
            throw new \InvalidArgumentException(
                'Unsupported Miro item type. Supported types: ' . implode(', ', MiroItemTypeMap::supportedTypes()),
            );
        }

        return $segment;
    }

    protected function defaultTeamId(): string
    {
        $value = getenv('MIRO_DEFAULT_TEAM_ID');

        return $value !== false ? trim((string) $value) : '';
    }

    protected function webhookCollectionPath(): string
    {
        $value = getenv('MIRO_WEBHOOKS_BASE_PATH');

        return $value !== false && trim((string) $value) !== ''
            ? trim((string) $value)
            : '/v2-experimental/webhooks/board_subscriptions';
    }
}