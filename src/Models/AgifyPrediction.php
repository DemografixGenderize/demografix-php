<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * A single agify prediction.
 *
 * `$age` is an integer or null. `$countryId` is populated only when the request
 * sent a country_id.
 */
final readonly class AgifyPrediction
{
    public function __construct(
        public string $name,
        public ?int $age,
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
            age: isset($data['age']) ? (int) $data['age'] : null,
            count: (int) ($data['count'] ?? 0),
            countryId: isset($data['country_id']) ? (string) $data['country_id'] : null,
        );
    }
}
