<?php

declare(strict_types=1);

namespace Demografix\Exceptions;

/**
 * Raised on a 402 response. The subscription is expired or inactive.
 */
class SubscriptionError extends DemografixException
{
}
