<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * One candidate country in a nationalize prediction.
 */
final readonly class NationalizeCountry
{
    public function __construct(
        public string $countryId,
        public float $probability,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            countryId: (string) ($data['country_id'] ?? ''),
            probability: (float) ($data['probability'] ?? 0.0),
        );
    }
}
