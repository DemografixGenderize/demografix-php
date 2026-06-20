<?php

declare(strict_types=1);

namespace Demografix\Http;

use Demografix\Exceptions\TransportError;

/**
 * Default Transport backed by the cURL extension. No runtime dependencies.
 */
final class CurlTransport implements Transport
{
    public function get(string $url, array $headers, float $timeout): Response
    {
        $handle = curl_init();
        if ($handle === false) {
            throw new TransportError('Failed to initialize cURL.');
        }

        $requestHeaders = [];
        foreach ($headers as $name => $value) {
            $requestHeaders[] = $name . ': ' . $value;
        }

        $responseHeaders = [];
        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_TIMEOUT_MS => (int) round($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($timeout * 1000),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        $body = curl_exec($handle);
        if ($body === false) {
            $message = curl_error($handle);
            curl_close($handle);
            throw new TransportError(
                $message !== '' ? $message : 'cURL request failed.',
            );
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($status, $responseHeaders, (string) $body);
    }
}
