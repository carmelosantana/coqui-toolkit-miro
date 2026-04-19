<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Client;

use CarmeloSantana\CoquiToolkitMiro\Auth\MiroAuthResolver;
use CarmeloSantana\CoquiToolkitMiro\Backend\MiroBackendInterface;
use CarmeloSantana\CoquiToolkitMiro\Backend\RestMiroBackend;

final class MiroClient
{
    public function __construct(
        private readonly MiroBackendInterface $backend,
    ) {}

    public static function fromEnv(string $workspacePath): self
    {
        return new self(RestMiroBackend::fromEnv($workspacePath));
    }

    public function auth(): MiroAuthResolver
    {
        return $this->backend->auth();
    }

    /**
     * @param array<string, mixed> $query
     */
    public function get(string $endpoint, array $query = []): MiroResult
    {
        return $this->backend->request('GET', $endpoint, $query);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    public function post(string $endpoint, array $body = [], array $query = []): MiroResult
    {
        return $this->backend->request('POST', $endpoint, $query, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function patch(string $endpoint, array $body = []): MiroResult
    {
        return $this->backend->request('PATCH', $endpoint, body: $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function put(string $endpoint, array $body = []): MiroResult
    {
        return $this->backend->request('PUT', $endpoint, body: $body);
    }

    public function delete(string $endpoint): MiroResult
    {
        return $this->backend->request('DELETE', $endpoint);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function paginateOffset(string $endpoint, array $query = [], string $itemsKey = 'data', int $limit = 50): MiroResult
    {
        return $this->backend->paginateOffset($endpoint, $query, $itemsKey, $limit);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function paginateCursor(string $endpoint, array $query = [], string $itemsKey = 'data'): MiroResult
    {
        return $this->backend->paginateCursor($endpoint, $query, $itemsKey);
    }
}