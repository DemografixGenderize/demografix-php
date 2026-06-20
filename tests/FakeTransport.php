<?php

declare(strict_types=1);

namespace Demografix\Tests;

use Demografix\Http\Response;
use Demografix\Http\Transport;
use LogicException;

/**
 * Test double for the Transport seam. Returns a queued canned response and
 * records the request so tests can assert on the built query string. A failed
 * test that makes an unexpected HTTP call surfaces because the queue is empty.
 */
final class FakeTransport implements Transport
{
    /** @var list<Response> */
    private array $queue = [];

    public ?string $lastUrl = null;

    /** @var array<string, string>|null */
    public ?array $lastHeaders = null;

    public int $callCount = 0;

    public function queue(Response $response): void
    {
        $this->queue[] = $response;
    }

    public function get(string $url, array $headers, float $timeout): Response
    {
        $this->callCount++;
        $this->lastUrl = $url;
        $this->lastHeaders = $headers;

        if ($this->queue === []) {
            throw new LogicException('FakeTransport received an unexpected request: ' . $url);
        }

        return array_shift($this->queue);
    }
}
