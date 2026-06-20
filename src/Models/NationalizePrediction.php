<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * A single nationalize prediction.
 *
 * `$country` holds up to five candidates in descending probability, and is
 * empty when there is no match.
 */
final readonly class NationalizePrediction
{
    /**
     * @param list<NationalizeCountry> $country
     */
    public function __construct(
        public string $name,
        public array $country,
        public int $count,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $candidates = [];
        foreach ((array) ($data['country'] ?? []) as $entry) {
            $candidates[] = NationalizeCountry::fromArray((array) $entry);
        }

        return new self(
            name: (string) ($data['name'] ?? ''),
            country: $candidates,
            count: (int) ($data['count'] ?? 0),
        );
    }
}
