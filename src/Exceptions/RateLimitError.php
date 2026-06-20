<?php

declare(strict_types=1);

namespace Demografix\Exceptions;

/**
 * Raised on a 429 response. The quota is always populated; read
 * `$error->quota->reset` for the seconds until the window resets.
 */
class RateLimitError extends DemografixException
{
}
