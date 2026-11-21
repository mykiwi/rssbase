<?php

declare(strict_types=1);

namespace App\AtProto\Identity\Exception;

/**
 * The input names no account: an invalid handle, a handle that resolves to no DID, or a DID document
 * that does not confirm the handle.
 */
final class AccountNotFound extends \RuntimeException {}
