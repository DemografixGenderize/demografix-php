<?php

declare(strict_types=1);

namespace Demografix\Exceptions;

/**
 * Raised on a 422 response, and client-side before any HTTP call when a batch
 * exceeds the 10-name maximum.
 */
class ValidationError extends DemografixException
{
}
