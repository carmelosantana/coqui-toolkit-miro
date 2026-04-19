<?php

declare(strict_types=1);

namespace CarmeloSantana\CoquiToolkitMiro\Exception;

final class MiroAuthException extends \RuntimeException
{
    public static function configError(string $message): self
    {
        return new self($message);
    }

    public static function authorizationFailed(string $message): self
    {
        return new self($message);
    }
}