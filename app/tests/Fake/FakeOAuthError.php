<?php

declare(strict_types=1);

namespace App\Tests\Fake;

/**
 * An OAuth error the fake authorization server answers, its message being the error code.
 */
final class FakeOAuthError extends \RuntimeException {}
