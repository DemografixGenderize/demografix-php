<?php

declare(strict_types=1);

namespace Demografix\Exceptions;

/**
 * Raised on a network failure, a timeout, or a response body that is not JSON.
 * The status and quota may be absent.
 */
class TransportError extends DemografixException
{
}
