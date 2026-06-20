<?php

declare(strict_types=1);

namespace Demografix\Http;

use Demografix\Exceptions\TransportError;

/**
 * The seam between the client and the network.
 *
 * The default implementation is {@see CurlTransport}. Tests inject a fake that
 * returns canned responses without touching the network. The public client API
 * does not expose this; it is an internal extension point.
 */
interface Transport
{
    /**
     * Perform a GET request and return the raw response.
     *
     * @param array<string, string> $headers request headers to send
     *
     * @throws TransportError on a network failure or timeout
     */
    public function get(string $url, array $headers, float $timeout): Response;
}
