<?php

declare(strict_types=1);

namespace Demografix\Exceptions;

/**
 * Raised on a 401 response. The API key is missing or invalid.
 */
class AuthError extends DemografixException
{
}
