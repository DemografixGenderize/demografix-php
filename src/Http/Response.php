<?php

declare(strict_types=1);

namespace Demografix\Http;

/**
 * A raw HTTP response handed back by a Transport.
 *
 * Header keys are normalized to lowercase by the transport so the client can
 * read the rate-limit headers without case juggling.
 */
final class Response
{
    /**
     * @param array<string, string> $headers lowercase header names to values
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
