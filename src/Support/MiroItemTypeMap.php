<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Support;

final class MiroItemTypeMap
{
    /**
     * @var array<string, string>
     */
    private const array ENDPOINTS = [
        'sticky_note' => 'sticky_notes',
        'card' => 'cards',
        'shape' => 'shapes',
        'text' => 'texts',
        'embed' => 'embeds',
        'frame' => 'frames',
        'connector' => 'connectors',
    ];

    public static function endpoint(string $itemType): ?string
    {
        return self::ENDPOINTS[$itemType] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return array_keys(self::ENDPOINTS);
    }
}