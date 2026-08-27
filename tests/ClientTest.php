<?php

declare(strict_types=1);

namespace Demografix\Tests;

use Demografix\Client;
use Demografix\Exceptions\AuthError;
use Demografix\Exceptions\RateLimitError;
use Demografix\Exceptions\SubscriptionError;
use Demografix\Exceptions\TransportError;
use Demografix\Exceptions\ValidationError;
use Demografix\Http\Response;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    /** @var array<string, string> */
    private const HEADERS = [
        'x-rate-limit-limit' => '25000',
        'x-rate-limit-remaining' => '24987',
        'x-rate-limit-reset' => '1314000',
    ];

    private function ok(string $body): Response
    {
        return new Response(200, self::HEADERS, $body);
    }

    private function error(int $status, string $body): Response
    {
        return new Response($status, self::HEADERS, $body);
    }

    private function client(FakeTransport $transport, string $apiKey = 'test-key'): Client
    {
        return new Client($apiKey, 10.0, $transport);
    }

    // (1) single parse + quota.remaining == 24987 ------------------------

    public function testGenderizeSingleParsesFieldsAndQuota(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok(
            '{ "count": 1352696, "name": "peter", "gender": "male", "probability": 1.0 }',
        ));

        $result = $this->client($transport)->genderize('peter');

        $this->assertSame('peter', $result->name);
        $this->assertSame('male', $result->gender);
        $this->assertSame(1.0, $result->probability);
        $this->assertSame(1352696, $result->count);
        $this->assertNull($result->countryId);
        $this->assertSame(25000, $result->quota->limit);
        $this->assertSame(24987, $result->quota->remaining);
        $this->assertSame(1314000, $result->quota->reset);

        $this->assertStringContainsString('name=peter', (string) $transport->lastUrl);
        $this->assertStringNotContainsString('name%5B%5D', (string) $transport->lastUrl);
    }

    public function testAgifySingleParsesFieldsAndQuota(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok('{ "count": 311558, "name": "michael", "age": 57 }'));

        $result = $this->client($transport)->agify('michael');

        $this->assertSame('michael', $result->name);
        $this->assertSame(57, $result->age);
        $this->assertSame(311558, $result->count);
        $this->assertSame(24987, $result->quota->remaining);
    }

    public function testNationalizeSingleParsesFieldsAndQuota(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok(
            '{ "count": 100783, "name": "nguyen",'
            . ' "country": [ { "country_id": "VN", "probability": 0.891132 },'
            . ' { "country_id": "MO", "probability": 0.019031 } ] }',
        ));

        $result = $this->client($transport)->nationalize('nguyen');

        $this->assertSame('nguyen', $result->name);
        $this->assertSame(100783, $result->count);
        $this->assertCount(2, $result->country);
        $this->assertSame('VN', $result->country[0]->countryId);
        $this->assertSame(0.891132, $result->country[0]->probability);
        $this->assertSame('MO', $result->country[1]->countryId);
        $this->assertSame(24987, $result->quota->remaining);
    }

    // (2) batch order + quota --------------------------------------------

    public function testAgifyBatchParsesResultsInOrderWithQuota(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok(
            '[ { "count": 311558, "name": "michael", "age": 57 },'
            . ' { "count": 55682, "name": "matthew", "age": 48 } ]',
        ));

        $batch = $this->client($transport)->agifyBatch(['michael', 'matthew']);

        $this->assertCount(2, $batch->results);
        $this->assertSame('michael', $batch->results[0]->name);
        $this->assertSame(57, $batch->results[0]->age);
        $this->assertSame('matthew', $batch->results[1]->name);
        $this->assertSame(48, $batch->results[1]->age);
        $this->assertSame(24987, $batch->quota->remaining);

        $url = (string) $transport->lastUrl;
        $this->assertStringContainsString('name%5B%5D=michael', $url);
        $this->assertStringContainsString('name%5B%5D=matthew', $url);
        // input order preserved in the query string
        $this->assertLessThan(
            strpos($url, 'name%5B%5D=matthew'),
            strpos($url, 'name%5B%5D=michael'),
        );
    }

    // (8) a batch of one name still sends name[], never name= -------------

    public function testBatchOfOneNameSendsNameArrayAndParsesOneElementArray(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok(
            '[ { "count": 1352696, "name": "peter", "gender": "male", "probability": 1.0 } ]',
        ));

        $batch = $this->client($transport)->genderizeBatch(['peter']);

        $this->assertCount(1, $batch->results);
        $this->assertSame('peter', $batch->results[0]->name);
        $this->assertSame('male', $batch->results[0]->gender);
        $this->assertSame(1.0, $batch->results[0]->probability);
        $this->assertSame(1352696, $batch->results[0]->count);
        $this->assertSame(25000, $batch->quota->limit);
        $this->assertSame(24987, $batch->quota->remaining);
        $this->assertSame(1314000, $batch->quota->reset);

        $url = (string) $transport->lastUrl;
        // the call form picks the parameter shape, never the name count
        $this->assertStringContainsString('name%5B%5D=peter', $url);
        $this->assertStringNotContainsString('name=', $url);
    }

    // (3) null prediction is a normal success ----------------------------

    public function testGenderizeNullPredictionIsNotAnError(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok('{ "name": "xÿz", "gender": null, "probability": 0.0, "count": 0 }'));

        $result = $this->client($transport)->genderize('xÿz');

        $this->assertNull($result->gender);
        $this->assertSame(0.0, $result->probability);
        $this->assertSame(0, $result->count);
    }

    public function testAgifyNullPredictionIsNotAnError(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok('{ "name": "xÿz", "age": null, "count": 0 }'));

        $result = $this->client($transport)->agify('xÿz');

        $this->assertNull($result->age);
        $this->assertSame(0, $result->count);
    }

    public function testNationalizeNullPredictionHasEmptyCountry(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok('{ "name": "xÿz", "country": [], "count": 0 }'));

        $result = $this->client($transport)->nationalize('xÿz');

        $this->assertSame([], $result->country);
        $this->assertSame(0, $result->count);
    }

    // (4) country_id round-trips -----------------------------------------

    public function testCountryIdRoundTripsIntoRequestAndBack(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok(
            '{ "count": 196601, "name": "kim", "gender": "female",'
            . ' "country_id": "US", "probability": 0.94 }',
        ));

        $result = $this->client($transport)->genderize('kim', 'US');

        $this->assertSame('US', $result->countryId);
        $this->assertSame('female', $result->gender);
        $this->assertStringContainsString('country_id=US', (string) $transport->lastUrl);
    }

    public function testNoCountryIdMeansNoCountryIdParam(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok('{ "count": 1352696, "name": "peter", "gender": "male", "probability": 1.0 }'));

        $this->client($transport)->genderize('peter');

        $this->assertStringNotContainsString('country_id', (string) $transport->lastUrl);
        // apikey is always present on the wire
        $this->assertStringContainsString('apikey=test-key', (string) $transport->lastUrl);
    }

    public function testApiKeyIsAlwaysSent(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok('{ "count": 1352696, "name": "peter", "gender": "male", "probability": 1.0 }'));

        $this->client($transport, 'secret-key')->genderize('peter');

        $this->assertStringContainsString('apikey=secret-key', (string) $transport->lastUrl);
    }

    // (7) constructing without a valid api_key raises ValidationError, no HTTP call ----

    public function testEmptyApiKeyRaisesValidationErrorWithoutHttpCall(): void
    {
        $transport = new FakeTransport();

        try {
            new Client('', 10.0, $transport);
            $this->fail('Expected ValidationError for an empty API key.');
        } catch (ValidationError $e) {
            $this->assertSame('api_key is required', $e->getMessage());
            $this->assertSame(0, $transport->callCount);
        }
    }

    public function testBlankApiKeyRaisesValidationErrorWithoutHttpCall(): void
    {
        $transport = new FakeTransport();

        try {
            new Client('   ', 10.0, $transport);
            $this->fail('Expected ValidationError for a blank API key.');
        } catch (ValidationError $e) {
            $this->assertSame('api_key is required', $e->getMessage());
            $this->assertSame(0, $transport->callCount);
        }
    }

    public function testOmittedApiKeyIsAClientSideError(): void
    {
        $this->expectException(\ArgumentCountError::class);

        // @phpstan-ignore-next-line  the missing argument is the point of the test
        new Client();
    }

    public function testUserAgentHeaderIsSent(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok('{ "count": 1352696, "name": "peter", "gender": "male", "probability": 1.0 }'));

        $this->client($transport)->genderize('peter');

        $this->assertSame('demografix-php/0.2.1', $transport->lastHeaders['User-Agent'] ?? null);
    }

    // (5) batch of 101 raises ValidationError with no HTTP call -----------

    public function testBatchOverTenRaisesValidationErrorWithoutHttpCall(): void
    {
        $transport = new FakeTransport();
        $names = array_map(static fn (int $i): string => "name{$i}", range(1, 101));

        try {
            $this->client($transport)->genderizeBatch($names);
            $this->fail('Expected ValidationError for a batch of 101 names.');
        } catch (ValidationError $e) {
            $this->assertSame(0, $transport->callCount);
            $this->assertNull($e->status);
            $this->assertNull($e->quota);
        }
    }

    public function testBatchOfMaxIsAllowed(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->ok('[]'));
        $names = array_map(static fn (int $i): string => "name{$i}", range(1, 100));

        $batch = $this->client($transport)->genderizeBatch($names);

        $this->assertSame([], $batch->results);
        $this->assertSame(1, $transport->callCount);
    }

    // (6) error status mapping -------------------------------------------

    public function testAuthErrorOn401(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->error(401, '{ "error": "Invalid API key" }'));

        try {
            $this->client($transport)->genderize('peter');
            $this->fail('Expected AuthError.');
        } catch (AuthError $e) {
            $this->assertSame(401, $e->status);
            $this->assertSame('Invalid API key', $e->getMessage());
        }
    }

    public function testSubscriptionErrorOn402(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->error(402, '{ "error": "Subscription is not active" }'));

        try {
            $this->client($transport)->genderize('peter');
            $this->fail('Expected SubscriptionError.');
        } catch (SubscriptionError $e) {
            $this->assertSame(402, $e->status);
            $this->assertSame('Subscription is not active', $e->getMessage());
        }
    }

    public function testValidationErrorOn422(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->error(422, '{ "error": "Missing \'name\' parameter" }'));

        try {
            $this->client($transport)->genderize('peter');
            $this->fail('Expected ValidationError.');
        } catch (ValidationError $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame("Missing 'name' parameter", $e->getMessage());
        }
    }

    public function testRateLimitErrorOn429CarriesQuota(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->error(429, '{ "error": "Request limit reached" }'));

        try {
            $this->client($transport)->genderize('peter');
            $this->fail('Expected RateLimitError.');
        } catch (RateLimitError $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame('Request limit reached', $e->getMessage());
            $this->assertNotNull($e->quota);
            $this->assertSame(24987, $e->quota->remaining);
            $this->assertSame(1314000, $e->quota->reset);
        }
    }

    // non-JSON error body maps to TransportError, not a status-typed error ----

    public function testNonJsonErrorBodyMapsToTransportError(): void
    {
        $transport = new FakeTransport();
        $transport->queue($this->error(502, '<html>502 Bad Gateway</html>'));

        try {
            $this->client($transport)->genderize('peter');
            $this->fail('Expected TransportError for a non-JSON 502 body.');
        } catch (TransportError $e) {
            $this->assertSame(502, $e->status);
            $this->assertNotNull($e->quota);
            $this->assertSame(24987, $e->quota->remaining);
        }
    }
}
