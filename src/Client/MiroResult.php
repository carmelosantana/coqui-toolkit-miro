<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Client;

use CarmeloSantana\CoquiToolkitMiro\Support\Json;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class MiroResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public bool $success,
        public array $data = [],
        public int $statusCode = 0,
        public string $message = '',
    ) {}

    public static function fromResponse(ResponseInterface $response): self
    {
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        if ($statusCode === 204) {
            return new self(true, ['ok' => true], $statusCode);
        }

        $data = self::decodeBody($content);

        if ($statusCode >= 400) {
            return new self(false, $data, $statusCode, self::extractMessage($data, $content, $statusCode));
        }

        return new self(true, $data, $statusCode);
    }

    public static function fromErrorResponse(HttpExceptionInterface $error): self
    {
        $response = $error->getResponse();
        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);
        $data = self::decodeBody($content);

        return new self(false, $data, $statusCode, self::extractMessage($data, $content, $statusCode));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function error(string $message, array $data = [], int $statusCode = 0): self
    {
        return new self(false, $data, $statusCode, $message);
    }

    public function toToolResultWith(string $prefix): ToolResult
    {
        if (!$this->success) {
            return ToolResult::error($this->message !== '' ? $this->message : 'Miro request failed.');
        }

        $payload = $this->data !== [] ? $this->data : ['ok' => true, 'statusCode' => $this->statusCode];

        return ToolResult::success($prefix . "\n" . Json::encodePretty($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeBody(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : ['raw' => $content];
        } catch (\JsonException) {
            return ['raw' => $content];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function extractMessage(array $data, string $content, int $statusCode): string
    {
        $message = $data['message'] ?? $data['detail'] ?? $data['error'] ?? null;

        if (is_string($message) && $message !== '') {
            return sprintf('Miro request failed (HTTP %d): %s', $statusCode, $message);
        }

        if ($content !== '') {
            return sprintf('Miro request failed (HTTP %d): %s', $statusCode, $content);
        }

        return sprintf('Miro request failed (HTTP %d).', $statusCode);
    }
}