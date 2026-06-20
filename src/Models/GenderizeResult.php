<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * A genderize prediction for a single name, plus the quota for the response.
 *
 * The prediction fields are exposed directly: `$result->gender`,
 * `$result->probability`, and so on.
 */
final readonly class GenderizeResult
{
    public string $name;
    public ?string $gender;
    public float $probability;
    public int $count;
    public ?string $countryId;

    public function __construct(
        public GenderizePrediction $prediction,
        public Quota $quota,
    ) {
        $this->name = $prediction->name;
        $this->gender = $prediction->gender;
        $this->probability = $prediction->probability;
        $this->count = $prediction->count;
        $this->countryId = $prediction->countryId;
    }
}
