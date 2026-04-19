<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Auth;

use CarmeloSantana\CoquiToolkitMiro\Exception\MiroAuthException;
use CarmeloSantana\CoquiToolkitMiro\Support\Json;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MiroOAuth
{
    private const string AUTH_URL = 'https://miro.com/oauth/authorize';
    private const string TOKEN_URL = 'https://api.miro.com/v1/oauth/token';
    private const string TOKENS_FILE = '.miro-tokens.json';
    private const int CALLBACK_TIMEOUT = 120;
    private const int TOKEN_EXPIRY_BUFFER = 60;

    private const array DEFAULT_SCOPES = [
        'boards:read',
        'boards:write',
    ];

    public function __construct(
        private readonly string $workspacePath,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly HttpClientInterface $httpClient = new \Symfony\Component\HttpClient\CurlHttpClient(),
    ) {}

    public static function fromEnv(string $workspacePath): self
    {
        return new self(
            workspacePath: $workspacePath,
            clientId: self::envString('MIRO_CLIENT_ID'),
            clientSecret: self::envString('MIRO_CLIENT_SECRET'),
        );
    }

    /**
     * @param list<string> $scopes
     * @return array{access_token: string, refresh_token?: string, expires_at?: int, scope?: string, token_type?: string}
     */
    public function authorize(array $scopes = []): array
    {
        $clientId = $this->resolveClientId();
        $clientSecret = $this->resolveClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            throw MiroAuthException::configError(
                'MIRO_CLIENT_ID and MIRO_CLIENT_SECRET are required for OAuth login.',
            );
        }

        if ($scopes === []) {
            $scopes = self::DEFAULT_SCOPES;
        }

        $port = $this->findAvailablePort();
        $redirectUri = sprintf('http://127.0.0.1:%d/callback', $port);
        $state = bin2hex(random_bytes(16));

        $authUrl = self::AUTH_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ]);

        $this->openBrowser($authUrl);
        $callbackData = $this->waitForCallback($port, $state);

        if (isset($callbackData['error'])) {
            throw MiroAuthException::authorizationFailed((string) $callbackData['error']);
        }

        $code = (string) ($callbackData['code'] ?? '');
        if ($code === '') {
            throw MiroAuthException::authorizationFailed('No authorization code received from Miro.');
        }

        $tokens = $this->exchangeCode($code, $redirectUri);
        $this->storeTokens($tokens);

        return $tokens;
    }

    public function getAccessToken(): ?string
    {
        $tokens = $this->loadTokens();

        if ($tokens === null) {
            return null;
        }

        $expiresAt = (int) ($tokens['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time() + self::TOKEN_EXPIRY_BUFFER) {
            $refreshToken = (string) ($tokens['refresh_token'] ?? '');

            if ($refreshToken === '') {
                return null;
            }

            try {
                $refreshed = $this->refreshToken($refreshToken);
                $this->storeTokens($refreshed);

                return $refreshed['access_token'];
            } catch (\Throwable) {
                return null;
            }
        }

        $accessToken = $tokens['access_token'];

        return $accessToken !== '' ? $accessToken : null;
    }

    public function hasTokens(): bool
    {
        return $this->loadTokens() !== null;
    }

    public function getStatus(): string
    {
        $tokens = $this->loadTokens();

        if ($tokens === null) {
            return 'Not authenticated with Miro OAuth. Run miro_auth(action: "login") to connect an account.';
        }

        $expiresAt = (int) ($tokens['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt < time()) {
            return 'Stored Miro OAuth access token is expired. It will refresh automatically if a refresh token is available.';
        }

        $remaining = max(0, $expiresAt - time());

        return sprintf(
            'Authenticated with Miro OAuth. Token expires in %dh %dm.',
            intdiv($remaining, 3600),
            intdiv($remaining % 3600, 60),
        );
    }

    public function logout(): void
    {
        $this->clearTokens();
    }

    public function clearTokens(): void
    {
        $path = $this->tokensPath();

        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function hasLoginConfig(): bool
    {
        return $this->resolveClientId() !== '' && $this->resolveClientSecret() !== '';
    }

    private function resolveClientId(): string
    {
        return $this->clientId !== '' ? $this->clientId : self::envString('MIRO_CLIENT_ID');
    }

    private function resolveClientSecret(): string
    {
        return $this->clientSecret !== '' ? $this->clientSecret : self::envString('MIRO_CLIENT_SECRET');
    }

    private function findAvailablePort(): int
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $port = random_int(49152, 65535);
            $server = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errorMessage);

            if ($server !== false) {
                fclose($server);

                return $port;
            }
        }

        throw MiroAuthException::authorizationFailed('Unable to find a free callback port for Miro OAuth.');
    }

    /**
     * @return array<string, string>
     */
    private function waitForCallback(int $port, string $expectedState): array
    {
        $server = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errorMessage);
        if ($server === false) {
            throw MiroAuthException::authorizationFailed('Failed to start local callback server: ' . $errorMessage);
        }

        stream_set_timeout($server, self::CALLBACK_TIMEOUT);

        $connection = @stream_socket_accept($server, self::CALLBACK_TIMEOUT);
        fclose($server);

        if ($connection === false) {
            throw MiroAuthException::authorizationFailed('Timed out waiting for the Miro OAuth callback.');
        }

        $requestLine = fgets($connection);
        $path = '/';

        if (is_string($requestLine) && preg_match('#^[A-Z]+\s+([^\s]+)#', $requestLine, $matches) === 1) {
            $path = $matches[1];
        }

        while (($line = fgets($connection)) !== false) {
            if (trim($line) === '') {
                break;
            }
        }

        $parts = parse_url($path);
        $params = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
        }

        fwrite(
            $connection,
            "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\n\r\n"
            . '<html><body><h1>Miro authorization complete</h1><p>You can close this window.</p></body></html>',
        );
        fclose($connection);

        if (($params['state'] ?? '') !== $expectedState) {
            throw MiroAuthException::authorizationFailed('Received an invalid OAuth state from Miro.');
        }

        /** @var array<string, string> $params */
        return $params;
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_at?: int, scope?: string, token_type?: string}
     */
    private function exchangeCode(string $code, string $redirectUri): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'grant_type' => 'authorization_code',
                'client_id' => $this->resolveClientId(),
                'client_secret' => $this->resolveClientSecret(),
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]),
            'timeout' => 30,
        ]);

        $data = $response->toArray(false);
        if ($response->getStatusCode() >= 400) {
            $message = (string) ($data['message'] ?? $data['error'] ?? 'OAuth token exchange failed.');
            throw MiroAuthException::authorizationFailed($message);
        }

        return $this->normalizeTokens($data);
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_at?: int, scope?: string, token_type?: string}
     */
    private function refreshToken(string $refreshToken): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'grant_type' => 'refresh_token',
                'client_id' => $this->resolveClientId(),
                'client_secret' => $this->resolveClientSecret(),
                'refresh_token' => $refreshToken,
            ]),
            'timeout' => 30,
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400) {
            $message = (string) ($data['message'] ?? $data['error'] ?? 'OAuth token refresh failed.');
            throw MiroAuthException::authorizationFailed($message);
        }

        return $this->normalizeTokens($data);
    }

    /**
     * @param array<string, mixed> $tokens
     * @return array{access_token: string, refresh_token?: string, expires_at?: int, scope?: string, token_type?: string}
     */
    private function normalizeTokens(array $tokens): array
    {
        $accessToken = (string) ($tokens['access_token'] ?? '');
        if ($accessToken === '') {
            throw MiroAuthException::authorizationFailed('Miro OAuth did not return an access token.');
        }

        $normalized = [
            'access_token' => $accessToken,
        ];

        $refreshToken = (string) ($tokens['refresh_token'] ?? '');
        if ($refreshToken !== '') {
            $normalized['refresh_token'] = $refreshToken;
        }

        $expiresIn = (int) ($tokens['expires_in'] ?? 0);
        if ($expiresIn > 0) {
            $normalized['expires_at'] = time() + $expiresIn;
        }

        $scope = (string) ($tokens['scope'] ?? '');
        if ($scope !== '') {
            $normalized['scope'] = $scope;
        }

        $tokenType = (string) ($tokens['token_type'] ?? '');
        if ($tokenType !== '') {
            $normalized['token_type'] = $tokenType;
        }

        return $normalized;
    }

    /**
     * @param array{access_token: string, refresh_token?: string, expires_at?: int, scope?: string, token_type?: string} $tokens
     */
    private function storeTokens(array $tokens): void
    {
        $path = $this->tokensPath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o700, true);
        }

        file_put_contents($path, Json::encodePretty($tokens) . "\n");
        chmod($path, 0o600);
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_at?: int, scope?: string, token_type?: string}|null
     */
    private function loadTokens(): ?array
    {
        $path = $this->tokensPath();
        if (!file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false || trim($contents) === '') {
            return null;
        }

        try {
            $data = Json::decodeObject($contents);
        } catch (\InvalidArgumentException) {
            return null;
        }

        $accessToken = isset($data['access_token']) ? (string) $data['access_token'] : '';
        if ($accessToken === '') {
            return null;
        }

        $tokens = ['access_token' => $accessToken];

        if (isset($data['refresh_token']) && is_string($data['refresh_token']) && $data['refresh_token'] !== '') {
            $tokens['refresh_token'] = $data['refresh_token'];
        }

        if (isset($data['expires_at'])) {
            $tokens['expires_at'] = (int) $data['expires_at'];
        }

        if (isset($data['scope']) && is_string($data['scope']) && $data['scope'] !== '') {
            $tokens['scope'] = $data['scope'];
        }

        if (isset($data['token_type']) && is_string($data['token_type']) && $data['token_type'] !== '') {
            $tokens['token_type'] = $data['token_type'];
        }

        return $tokens;
    }

    private function tokensPath(): string
    {
        return rtrim($this->workspacePath, '/') . '/' . self::TOKENS_FILE;
    }

    private function openBrowser(string $url): void
    {
        $escaped = escapeshellarg($url);

        match (PHP_OS_FAMILY) {
            'Darwin' => exec('open ' . $escaped . ' >/dev/null 2>&1 &'),
            'Windows' => exec('start "" ' . $escaped),
            default => exec('xdg-open ' . $escaped . ' >/dev/null 2>&1 &'),
        };
    }

    private static function envString(string $key): string
    {
        $value = getenv($key);

        return $value !== false ? trim((string) $value) : '';
    }
}