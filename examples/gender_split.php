<?php

declare(strict_types=1);

/**
 * Compute the gender split of a list of names.
 *
 * Run from the package root after installing dependencies:
 *
 *     composer install
 *     php examples/gender_split.php
 *
 * Set DEMOGRAFIX_API_KEY in the environment to send an API key. Without one,
 * requests use the free per-IP tier.
 */

require __DIR__ . '/../vendor/autoload.php';

use Demografix\Client;
use Demografix\Exceptions\RateLimitError;

$apiKey = getenv('DEMOGRAFIX_API_KEY') ?: null;
$client = new Client($apiKey);

$names = ['peter', 'lois', 'meg', 'chris', 'stewie', 'brian'];

try {
    $batch = $client->genderizeBatch($names);
} catch (RateLimitError $e) {
    fwrite(STDERR, "Rate limited. Retry in {$e->quota->reset} seconds.\n");
    exit(1);
}

$split = ['male' => 0, 'female' => 0, 'unknown' => 0];
foreach ($batch->results as $prediction) {
    $bucket = $prediction->gender ?? 'unknown';
    $split[$bucket]++;
}

echo "Gender split across " . count($names) . " names:\n";
foreach ($split as $label => $total) {
    echo sprintf("  %-8s %d\n", $label, $total);
}

echo "Quota remaining: {$batch->quota->remaining}\n";
