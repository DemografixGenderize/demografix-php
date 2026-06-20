<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * Rate-limit state parsed from the response headers.
 */
final readonly class Quota
{
    public function __construct(
        public int $limit,
        public int $remaining,
        public int $reset,
    ) {
    }
}
