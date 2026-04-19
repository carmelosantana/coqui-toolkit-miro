<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Auth;

final class MiroAuthResolver
{
    private const array TOKEN_KEYS = ['MIRO_ACCESS_TOKEN', 'MIRO_API_TOKEN'];

    public function __construct(
        string $workspacePath,
        private readonly string $explicitToken = '',
        ?MiroOAuth $oauth = null,
    ) {
        $this->oauth = $oauth ?? MiroOAuth::fromEnv($workspacePath);
    }

    private readonly MiroOAuth $oauth;

    public static function fromEnv(string $workspacePath): self
    {
        return new self($workspacePath);
    }

    public function oauth(): MiroOAuth
    {
        return $this->oauth;
    }

    public function getAccessToken(): ?string
    {
        $token = $this->staticToken();
        if ($token !== null) {
            return $token;
        }

        return $this->oauth->getAccessToken();
    }

    public function mode(): string
    {
        if ($this->staticToken() !== null) {
            return 'token';
        }

        if ($this->oauth->hasTokens()) {
            return 'oauth';
        }

        return 'none';
    }

    public function status(): string
    {
        return match ($this->mode()) {
            'token' => 'Authenticated with a direct Miro token from environment or constructor configuration.',
            'oauth' => $this->oauth->getStatus(),
            default => $this->oauth->hasLoginConfig()
                ? 'No active Miro session. Run miro_auth(action: "login") to authenticate with OAuth, or configure MIRO_ACCESS_TOKEN.'
                : 'No Miro authentication configured. Set MIRO_ACCESS_TOKEN for direct access or MIRO_CLIENT_ID and MIRO_CLIENT_SECRET for OAuth.',
        };
    }

    public function source(): string
    {
        return match ($this->mode()) {
            'token' => 'token',
            'oauth' => 'oauth',
            default => 'none',
        };
    }

    /**
     * @return array{access_token: string, refresh_token?: string, expires_at?: int, scope?: string, token_type?: string}
     */
    public function login(): array
    {
        return $this->oauth->authorize();
    }

    public function logout(): string
    {
        $this->oauth->logout();

        if ($this->staticToken() !== null) {
            return 'Cleared any stored OAuth tokens. Direct token authentication remains configured through environment or constructor state.';
        }

        return 'Cleared stored Miro OAuth tokens.';
    }

    private function staticToken(): ?string
    {
        if ($this->explicitToken !== '') {
            return $this->explicitToken;
        }

        foreach (self::TOKEN_KEYS as $key) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}