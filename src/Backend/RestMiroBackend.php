<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Backend;

use CarmeloSantana\CoquiToolkitMiro\Auth\MiroAuthResolver;
use CarmeloSantana\CoquiToolkitMiro\Client\MiroResult;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RestMiroBackend implements MiroBackendInterface
{
    private const string BASE_URL = 'https://api.miro.com';
    private const int TIMEOUT = 30;
    private const int MAX_RATE_LIMIT_RETRIES = 1;

    public function __construct(
        private readonly MiroAuthResolver $auth,
        private readonly HttpClientInterface $httpClient = new \Symfony\Component\HttpClient\CurlHttpClient(),
    ) {}

    public static function fromEnv(string $workspacePath): self
    {
        return new self(MiroAuthResolver::fromEnv($workspacePath));
    }

    public function auth(): MiroAuthResolver
    {
        return $this->auth;
    }

    public function request(string $method, string $endpoint, array $query = [], array $body = [], array $headers = []): MiroResult
    {
        $token = $this->auth->getAccessToken();
        if ($token === null) {
            return MiroResult::error('Not authenticated with Miro. Run miro_auth(action: "status") to inspect configuration and miro_auth(action: "login") for OAuth.', statusCode: 401);
        }

        $url = $this->normalizeUrl($endpoint);
        $options = [
            'headers' => array_merge([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ], $headers),
            'timeout' => self::TIMEOUT,
        ];

        if ($query !== []) {
            $options['query'] = $this->filterQuery($query);
        }

        if ($body !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['json'] = $body;
        }

        return $this->executeWithRetry($method, $url, $options);
    }

    public function paginateOffset(string $endpoint, array $query = [], string $itemsKey = 'data', int $limit = 50): MiroResult
    {
        $allItems = [];
        $offset = 0;
        $pages = 0;

        do {
            $pages++;
            $result = $this->request('GET', $endpoint, array_merge($query, [
                'offset' => $offset,
                'limit' => $query['limit'] ?? $limit,
            ]));

            if (!$result->success) {
                return $result;
            }

            $items = $result->data[$itemsKey] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            $allItems = array_merge($allItems, $items);
            $received = count($items);
            $offset += $received;
            $total = (int) ($result->data['total'] ?? $offset);

            if ($received === 0 || $offset >= $total || $pages >= 20) {
                break;
            }
        } while (true);

        return new MiroResult(true, [$itemsKey => $allItems, 'total' => count($allItems)], 200);
    }

    public function paginateCursor(string $endpoint, array $query = [], string $itemsKey = 'data'): MiroResult
    {
        $allItems = [];
        /** @var string|null $cursor */
        $cursor = null;
        $pages = 0;

        do {
            $pages++;
            $cursorQuery = $query;
            if ($cursor !== null) {
                $cursorQuery['cursor'] = $cursor;
            }

            $result = $this->request('GET', $endpoint, $cursorQuery);
            if (!$result->success) {
                return $result;
            }

            $items = $result->data[$itemsKey] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

            $allItems = array_merge($allItems, $items);
            $nextCursor = $result->data['cursor'] ?? null;
            $cursor = is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null;

            if ($cursor === null || $pages >= 20) {
                break;
            }
        } while (true);

        return new MiroResult(true, [$itemsKey => $allItems, 'total' => count($allItems)], 200);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeWithRetry(string $method, string $url, array $options): MiroResult
    {
        for ($attempt = 0; $attempt <= self::MAX_RATE_LIMIT_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                if ($statusCode === 429 && $attempt < self::MAX_RATE_LIMIT_RETRIES) {
                    $this->applyRetryDelay($response->getHeaders(false));
                    continue;
                }

                return MiroResult::fromResponse($response);
            } catch (HttpExceptionInterface $e) {
                $statusCode = $e->getResponse()->getStatusCode();

                if ($statusCode === 429 && $attempt < self::MAX_RATE_LIMIT_RETRIES) {
                    $this->applyRetryDelay($e->getResponse()->getHeaders(false));
                    continue;
                }

                return MiroResult::fromErrorResponse($e);
            } catch (TransportExceptionInterface $e) {
                return MiroResult::error('Transport error communicating with Miro: ' . $e->getMessage());
            }
        }

        return MiroResult::error('Miro rate limit exceeded after retry.');
    }

    /**
     * @param array<string, array<int, string>> $headers
     */
    private function applyRetryDelay(array $headers): void
    {
        $retryAfter = $headers['retry-after'][0] ?? '1';
        $delay = max(1, min((int) $retryAfter, 10));
        sleep($delay);
    }

    private function normalizeUrl(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        return self::BASE_URL . '/' . ltrim($endpoint, '/');
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function filterQuery(array $query): array
    {
        return array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
    }
}