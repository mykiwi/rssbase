<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Why a login did not complete, as the login page tells the visitor: its message key is a translation key.
 */
final class LoginFailure extends CustomUserMessageAuthenticationException
{
    private function __construct(TranslatableMessage $message, ?\Throwable $previous = null)
    {
        parent::__construct($message->getMessage(), previous: $previous);
    }

    public static function accountNotFound(?\Throwable $previous = null): self
    {
        return new self(new TranslatableMessage('login.error.account_not_found'), $previous);
    }

    public static function providerUnreachable(?\Throwable $previous = null): self
    {
        return new self(new TranslatableMessage('login.error.provider_unreachable'), $previous);
    }

    public static function denied(): self
    {
        return new self(new TranslatableMessage('login.error.denied'));
    }

    /**
     * The answer of the provider does not match the request: a forged or replayed answer, or another account.
     */
    public static function failed(?\Throwable $previous = null): self
    {
        return new self(new TranslatableMessage('login.error.failed'), $previous);
    }

    public static function sessionEnded(): self
    {
        return new self(new TranslatableMessage('login.error.session_ended'));
    }
}
