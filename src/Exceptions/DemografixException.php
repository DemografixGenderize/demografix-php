<?php

declare(strict_types=1);

namespace Demografix\Exceptions;

use Demografix\Models\Quota;
use RuntimeException;
use Throwable;

/**
 * Base exception for every error raised by the Demografix client.
 *
 * Carries the HTTP status (when one was received) and the rate-limit quota
 * parsed from the response headers (when they were present).
 */
class DemografixException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?Quota $quota = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
