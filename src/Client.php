<?php

declare(strict_types=1);

namespace Demografix;

use Demografix\Exceptions\AuthError;
use Demografix\Exceptions\DemografixException;
use Demografix\Exceptions\RateLimitError;
use Demografix\Exceptions\SubscriptionError;
use Demografix\Exceptions\TransportError;
use Demografix\Exceptions\ValidationError;
use Demografix\Http\CurlTransport;
use Demografix\Http\Response;
use Demografix\Http\Transport;
use Demografix\Models\AgifyPrediction;
use Demografix\Models\AgifyResult;
use Demografix\Models\Batch;
use Demografix\Models\GenderizePrediction;
use Demografix\Models\GenderizeResult;
use Demografix\Models\NationalizePrediction;
use Demografix\Models\NationalizeResult;
use Demografix\Models\Quota;

/**
 * Client for the three Demografix APIs: genderize, agify, and nationalize.
 *
 * One key works across all three services and shares one quota. The hosts and
 * the User-Agent are hardcoded constants, not options.
 */
final class Client
{
    public const VERSION = '0.1.0';
    private const USER_AGENT = 'demografix-php/0.1.0';
    private const MAX_BATCH = 10;

    private const HOST_GENDERIZE = 'https://api.genderize.io/';
    private const HOST_AGIFY = 'https://api.agify.io/';
    private const HOST_NATIONALIZE = 'https://api.nationalize.io/';

    private readonly Transport $transport;

    /**
     * @param string|null    $apiKey    optional API key; omit for the free per-IP tier
     * @param float          $timeout   request timeout in seconds
     * @param Transport|null $transport internal seam; defaults to cURL. Tests inject a fake.
     */
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly float $timeout = 10.0,
        ?Transport $transport = null,
    ) {
        $this->transport = $transport ?? new CurlTransport();
    }

    // -- genderize ---------------------------------------------------------

    /**
     * Predict the gender of a single name.
     *
     * @param string|null $countryId optional ISO 3166-1 alpha-2 code to scope the prediction
     *
     * @throws ValidationError   on a 422 response (invalid parameter)
     * @throws AuthError         on a 401 response (missing or invalid API key)
     * @throws SubscriptionError on a 402 response (expired or inactive subscription)
     * @throws RateLimitError    on a 429 response (quota exhausted)
     * @throws TransportError    on a network failure, timeout, or non-JSON body
     * @throws DemografixException on any other non-2xx response
     */
    public function genderize(string $name, ?string $countryId = null): GenderizeResult
    {
        $response = $this->request(self::HOST_GENDERIZE, [$name], false, $countryId);
        $data = $this->decode($response);

        return new GenderizeResult(
            GenderizePrediction::fromArray($data),
            $this->quota($response),
        );
    }

    /**
     * Predict the gender of up to 10 names in one request.
     *
     * @param list<string> $names     up to 10 names; more raises ValidationError before any HTTP call
     * @param string|null  $countryId optional ISO 3166-1 alpha-2 code to scope the predictions
     *
     * @return Batch<GenderizePrediction> results in input order, plus one quota
     *
     * @throws ValidationError   client-side when more than 10 names are given, or on a 422 response
     * @throws AuthError         on a 401 response (missing or invalid API key)
     * @throws SubscriptionError on a 402 response (expired or inactive subscription)
     * @throws RateLimitError    on a 429 response (quota exhausted)
     * @throws TransportError    on a network failure, timeout, or non-JSON body
     * @throws DemografixException on any other non-2xx response
     */
    public function genderizeBatch(array $names, ?string $countryId = null): Batch
    {
        $response = $this->request(self::HOST_GENDERIZE, $names, true, $countryId);
        $rows = $this->decode($response);
        $results = [];
        foreach ($rows as $row) {
            $results[] = GenderizePrediction::fromArray((array) $row);
        }

        return new Batch($results, $this->quota($response));
    }

    // -- agify -------------------------------------------------------------

    /**
     * Predict the age of a single name.
     *
     * @param string|null $countryId optional ISO 3166-1 alpha-2 code to scope the prediction
     *
     * @throws ValidationError   on a 422 response (invalid parameter)
     * @throws AuthError         on a 401 response (missing or invalid API key)
     * @throws SubscriptionError on a 402 response (expired or inactive subscription)
     * @throws RateLimitError    on a 429 response (quota exhausted)
     * @throws TransportError    on a network failure, timeout, or non-JSON body
     * @throws DemografixException on any other non-2xx response
     */
    public function agify(string $name, ?string $countryId = null): AgifyResult
    {
        $response = $this->request(self::HOST_AGIFY, [$name], false, $countryId);
        $data = $this->decode($response);

        return new AgifyResult(
            AgifyPrediction::fromArray($data),
            $this->quota($response),
        );
    }

    /**
     * Predict the age of up to 10 names in one request.
     *
     * @param list<string> $names     up to 10 names; more raises ValidationError before any HTTP call
     * @param string|null  $countryId optional ISO 3166-1 alpha-2 code to scope the predictions
     *
     * @return Batch<AgifyPrediction> results in input order, plus one quota
     *
     * @throws ValidationError   client-side when more than 10 names are given, or on a 422 response
     * @throws AuthError         on a 401 response (missing or invalid API key)
     * @throws SubscriptionError on a 402 response (expired or inactive subscription)
     * @throws RateLimitError    on a 429 response (quota exhausted)
     * @throws TransportError    on a network failure, timeout, or non-JSON body
     * @throws DemografixException on any other non-2xx response
     */
    public function agifyBatch(array $names, ?string $countryId = null): Batch
    {
        $response = $this->request(self::HOST_AGIFY, $names, true, $countryId);
        $rows = $this->decode($response);
        $results = [];
        foreach ($rows as $row) {
            $results[] = AgifyPrediction::fromArray((array) $row);
        }

        return new Batch($results, $this->quota($response));
    }

    // -- nationalize -------------------------------------------------------

    /**
     * Predict the nationality of a single name.
     *
     * @throws ValidationError   on a 422 response (invalid parameter)
     * @throws AuthError         on a 401 response (missing or invalid API key)
     * @throws SubscriptionError on a 402 response (expired or inactive subscription)
     * @throws RateLimitError    on a 429 response (quota exhausted)
     * @throws TransportError    on a network failure, timeout, or non-JSON body
     * @throws DemografixException on any other non-2xx response
     */
    public function nationalize(string $name): NationalizeResult
    {
        $response = $this->request(self::HOST_NATIONALIZE, [$name], false, null);
        $data = $this->decode($response);

        return new NationalizeResult(
            NationalizePrediction::fromArray($data),
            $this->quota($response),
        );
    }

    /**
     * Predict the nationality of up to 10 names in one request.
     *
     * @param list<string> $names up to 10 names; more raises ValidationError before any HTTP call
     *
     * @return Batch<NationalizePrediction> results in input order, plus one quota
     *
     * @throws ValidationError   client-side when more than 10 names are given, or on a 422 response
     * @throws AuthError         on a 401 response (missing or invalid API key)
     * @throws SubscriptionError on a 402 response (expired or inactive subscription)
     * @throws RateLimitError    on a 429 response (quota exhausted)
     * @throws TransportError    on a network failure, timeout, or non-JSON body
     * @throws DemografixException on any other non-2xx response
     */
    public function nationalizeBatch(array $names): Batch
    {
        $response = $this->request(self::HOST_NATIONALIZE, $names, true, null);
        $rows = $this->decode($response);
        $results = [];
        foreach ($rows as $row) {
            $results[] = NationalizePrediction::fromArray((array) $row);
        }

        return new Batch($results, $this->quota($response));
    }

    // -- internals ---------------------------------------------------------

    /**
     * Build the query, enforce the batch limit, and send the request. The body
     * is parsed as JSON by decode(); status-based error mapping happens there,
     * never here, so a non-JSON body always maps to TransportError first.
     *
     * @param list<string> $names
     */
    private function request(string $host, array $names, bool $batch, ?string $countryId): Response
    {
        $names = array_values($names);

        if (count($names) > self::MAX_BATCH) {
            throw new ValidationError(
                sprintf('A batch accepts at most %d names, got %d.', self::MAX_BATCH, count($names)),
            );
        }

        $url = $host . '?' . $this->query($names, $batch, $countryId);

        $headers = [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'application/json',
        ];

        return $this->transport->get($url, $headers, $this->timeout);
    }

    /**
     * Build the query string. A single name uses `name=v`; a batch uses
     * repeated `name[]=v`. `country_id` and `apikey` are added only when set.
     *
     * @param list<string> $names
     */
    private function query(array $names, bool $batch, ?string $countryId): string
    {
        $parts = [];
        foreach ($names as $name) {
            $key = $batch ? 'name[]' : 'name';
            $parts[] = rawurlencode($key) . '=' . rawurlencode($name);
        }

        if ($countryId !== null && $countryId !== '') {
            $parts[] = 'country_id=' . rawurlencode($countryId);
        }

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $parts[] = 'apikey=' . rawurlencode($this->apiKey);
        }

        return implode('&', $parts);
    }

    /**
     * Parse the body as JSON first, regardless of status. A body that is not
     * well-formed JSON maps to TransportError (carrying the status and the
     * quota when the rate-limit headers are present). Only a well-formed JSON
     * body proceeds to status-based error mapping: a non-2xx status raises the
     * matching typed exception, while a 2xx status returns the decoded array.
     *
     * @return array<int|string, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new TransportError(
                'Response body was not valid JSON.',
                $response->status,
                $this->quota($response),
            );
        }

        if ($response->status < 200 || $response->status >= 300) {
            throw $this->mapError($response, $decoded);
        }

        return $decoded;
    }

    /**
     * Read the three rate-limit headers into a Quota when present.
     */
    private function quota(Response $response): ?Quota
    {
        $limit = $response->header('x-rate-limit-limit');
        $remaining = $response->header('x-rate-limit-remaining');
        $reset = $response->header('x-rate-limit-reset');

        if ($limit === null || $remaining === null || $reset === null) {
            return null;
        }

        return new Quota((int) $limit, (int) $remaining, (int) $reset);
    }

    /**
     * Map a non-2xx response with a well-formed JSON body to the matching
     * typed exception, passing the server's `error` string through as the
     * message. The body has already been decoded by decode().
     *
     * @param array<int|string, mixed> $decoded
     */
    private function mapError(Response $response, array $decoded): DemografixException
    {
        $quota = $this->quota($response);
        $status = $response->status;
        $message = isset($decoded['error']) && is_string($decoded['error'])
            ? $decoded['error']
            : 'Request failed with status ' . $status . '.';

        return match ($status) {
            401 => new AuthError($message, $status, $quota),
            402 => new SubscriptionError($message, $status, $quota),
            422 => new ValidationError($message, $status, $quota),
            429 => new RateLimitError($message, $status, $quota),
            default => new DemografixException($message, $status, $quota),
        };
    }
}
