<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * An agify prediction for a single name, plus the quota for the response.
 *
 * The prediction fields are exposed directly: `$result->age`,
 * `$result->count`, and so on.
 */
final readonly class AgifyResult
{
    public string $name;
    public ?int $age;
    public int $count;
    public ?string $countryId;

    public function __construct(
        public AgifyPrediction $prediction,
        public Quota $quota,
    ) {
        $this->name = $prediction->name;
        $this->age = $prediction->age;
        $this->count = $prediction->count;
        $this->countryId = $prediction->countryId;
    }
}
