# Demografix PHP SDK

Run demographic analysis over names — predicted gender, age, and nationality — from one PHP client. The
package covers [genderize.io](https://genderize.io), [agify.io](https://agify.io), and
[nationalize.io](https://nationalize.io).

## Install

```bash
composer require demografix/demografix
```

Requires PHP 8.2 or later and the cURL extension. No runtime dependencies.

## Quickstart

Construct a client, run a batch over a list of names, read the predictions, and read the remaining quota.

```php
use Demografix\Client;

$client = new Client('YOUR_API_KEY');

$batch = $client->genderizeBatch(['peter', 'lois', 'meg', 'chris']);

$split = ['male' => 0, 'female' => 0, 'unknown' => 0];
foreach ($batch->results as $prediction) {
    $split[$prediction->gender ?? 'unknown']++;
}

// $split is the gender distribution of the list: ['male' => 2, 'female' => 2, 'unknown' => 0]
echo $batch->quota->remaining; // 24987
```

The client reads quota from the response. It is never cached on the client.

## Construction

```php
$client = new Client(
    apiKey: 'YOUR_API_KEY', // required
    timeout: 10.0,          // optional, seconds; default 10
);
```

The hosts and the User-Agent are hardcoded. They are not options. The API key is required: constructing the
client with an empty or blank key raises `ValidationError`. One API key works across all three services.

## genderize

Single name returns a result with the prediction fields and a quota.

```php
$result = $client->genderize('peter');
$result->gender;          // "male", "female", or null
$result->probability;     // 1.0
$result->count;           // 1352696
$result->quota->remaining; // 24987
```

Batch (up to 10 names) returns results in input order plus one quota. Aggregate the predictions into a
distribution rather than labeling any one name.

```php
$batch = $client->genderizeBatch(['peter', 'lois', 'meg']);

$female = array_filter($batch->results, fn ($p) => $p->gender === 'female');
$femaleShare = count($female) / count($batch->results); // share of the list predicted female
```

## agify

```php
$result = $client->agify('michael');
$result->age;   // 57 or null
$result->count; // 311558
```

Batch into an age distribution across a list.

```php
$batch = $client->agifyBatch(['michael', 'matthew', 'jane']);

$ages = array_filter(array_map(fn ($p) => $p->age, $batch->results), fn ($a) => $a !== null);
$mean = $ages === [] ? null : array_sum($ages) / count($ages); // mean predicted age of the list
```

## nationalize

```php
$result = $client->nationalize('nguyen');
$result->country[0]->countryId;    // "VN"
$result->country[0]->probability;  // 0.891132
```

Batch into a nationality mix across a list.

```php
$batch = $client->nationalizeBatch(['nguyen', 'smith', 'garcia']);

$mix = [];
foreach ($batch->results as $prediction) {
    $top = $prediction->country[0] ?? null;
    if ($top !== null) {
        $mix[$top->countryId] = ($mix[$top->countryId] ?? 0) + 1;
    }
}
// $mix is the top-country breakdown of the list
```

## country_id

`genderize` and `agify` accept an optional ISO 3166-1 alpha-2 `country_id` to scope the prediction.
`nationalize` does not take one. The API echoes the value back uppercase.

```php
$result = $client->genderize('kim', 'US');
$result->countryId; // "US"

$batch = $client->agifyBatch(['kim', 'andrea'], 'US');
```

## Quota

Every result and every raised error carries a `Quota` read from the response headers.

| Field | Meaning |
|---|---|
| `limit` | names allowed in the current window |
| `remaining` | names left in the current window |
| `reset` | seconds until the window resets |

```php
$batch->quota->limit;
$batch->quota->remaining;
$batch->quota->reset;
```

## Errors

Every error extends `Demografix\Exceptions\DemografixException`, which carries `status`, the passthrough
`message`, and a nullable `quota`.

| Exception | Status | Meaning |
|---|---|---|
| `AuthError` | 401 | API key missing or invalid |
| `SubscriptionError` | 402 | subscription expired or inactive |
| `ValidationError` | 422 | invalid parameter; also raised client-side when a batch exceeds 10 names |
| `RateLimitError` | 429 | quota exhausted; `quota` is always populated |
| `TransportError` | — | network failure, timeout, or non-JSON body |
| `DemografixException` | other non-2xx | base type |

A batch of more than 10 names raises `ValidationError` before any HTTP call.

`RateLimitError` reports when the window resets. Read `quota->reset` to back off.

```php
use Demografix\Exceptions\RateLimitError;

try {
    $batch = $client->agifyBatch($names);
} catch (RateLimitError $e) {
    sleep($e->quota->reset);
    $batch = $client->agifyBatch($names);
}
```

## Methods

| Method | Returns | country_id |
|---|---|---|
| `genderize(string $name, ?string $countryId = null)` | `GenderizeResult` | yes |
| `genderizeBatch(array $names, ?string $countryId = null)` | `Batch` of `GenderizePrediction` | yes |
| `agify(string $name, ?string $countryId = null)` | `AgifyResult` | yes |
| `agifyBatch(array $names, ?string $countryId = null)` | `Batch` of `AgifyPrediction` | yes |
| `nationalize(string $name)` | `NationalizeResult` | no |
| `nationalizeBatch(array $names)` | `Batch` of `NationalizePrediction` | no |

Each single result exposes the prediction fields directly and a `quota`. Each `Batch` exposes `results` and
one `quota`.

## API keys

An API key is required. Creating one is free and includes 2,500 requests per month. Generate a key in your
dashboard at [genderize.io](https://genderize.io), [agify.io](https://agify.io), or
[nationalize.io](https://nationalize.io). One key works across all three services. Full reference:
<https://genderize.io/documentation/api>.

## Tests

```bash
composer install
composer test
```

Tests inject a fake `Transport`, so they run without network access.

## License

MIT. See [LICENSE](LICENSE).
