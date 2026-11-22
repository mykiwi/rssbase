<?php

declare(strict_types=1);

namespace App\Security;

/**
 * A token refresh that failed without ending the access, kept in the session until the next try.
 */
final readonly class PostponedRefresh
{
    /**
     * @param int $notBefore when the provider may be asked again, as a Unix timestamp
     * @param int $refusals  how many times in a row the provider refused, with an error that says
     *                       nothing of the tokens
     */
    public function __construct(
        public int $notBefore,
        public int $refusals,
    ) {}

    public function isOver(int $now): bool
    {
        return $now >= $this->notBefore;
    }
}
