<?php

declare(strict_types=1);

namespace Demografix\Models;

/**
 * A batch response: the per-name predictions in input order, plus one quota for
 * the whole response.
 *
 * @template T of GenderizePrediction|AgifyPrediction|NationalizePrediction
 */
final readonly class Batch
{
    /**
     * @param list<T> $results
     */
    public function __construct(
        public array $results,
        public Quota $quota,
    ) {
    }
}
