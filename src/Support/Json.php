<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Support;

final class Json
{
    /**
     * @param mixed $value
     */
    public static function encodePretty(mixed $value): string
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function decodeObject(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid JSON: ' . $e->getMessage(), previous: $e);
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Expected a JSON object or array.');
        }

        return $decoded;
    }
}