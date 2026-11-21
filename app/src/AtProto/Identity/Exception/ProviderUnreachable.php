<?php

declare(strict_types=1);

namespace App\AtProto\Identity\Exception;

/**
 * A server of the account (DID directory, PDS, authorization server) did not answer, or answered
 * something that the protocol does not allow.
 */
final class ProviderUnreachable extends \RuntimeException {}
