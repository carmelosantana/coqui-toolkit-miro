<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Backend;

use CarmeloSantana\CoquiToolkitMiro\Auth\MiroAuthResolver;
use CarmeloSantana\CoquiToolkitMiro\Client\MiroResult;

interface MiroBackendInterface
{
    public function auth(): MiroAuthResolver;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function request(string $method, string $endpoint, array $query = [], array $body = [], array $headers = []): MiroResult;

    /**
     * @param array<string, mixed> $query
     */
    public function paginateOffset(string $endpoint, array $query = [], string $itemsKey = 'data', int $limit = 50): MiroResult;

    /**
     * @param array<string, mixed> $query
     */
    public function paginateCursor(string $endpoint, array $query = [], string $itemsKey = 'data'): MiroResult;
}