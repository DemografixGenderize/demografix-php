<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * A nationalize prediction for a single name, plus the quota for the response.
 *
 * The prediction fields are exposed directly: `$result->country`,
 * `$result->count`, and so on.
 */
final readonly class NationalizeResult
{
    public string $name;

    /** @var list<NationalizeCountry> */
    public array $country;

    public int $count;

    public function __construct(
        public NationalizePrediction $prediction,
        public Quota $quota,
    ) {
        $this->name = $prediction->name;
        $this->country = $prediction->country;
        $this->count = $prediction->count;
    }
}
