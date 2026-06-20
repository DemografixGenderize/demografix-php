<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * A single genderize prediction.
 *
 * `$gender` is "male", "female", or null. `$countryId` is populated only when
 * the request sent a country_id.
 */
final readonly class GenderizePrediction
{
    public function __construct(
        public string $name,
        public ?string $gender,
        public float $probability,
        public int $count,
        public ?string $countryId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            gender: isset($data['gender']) ? (string) $data['gender'] : null,
            probability: (float) ($data['probability'] ?? 0.0),
            count: (int) ($data['count'] ?? 0),
            countryId: isset($data['country_id']) ? (string) $data['country_id'] : null,
        );
    }
}
