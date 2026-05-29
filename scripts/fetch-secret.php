<?php
// Simple script to fetch a secret using the app's SecretsManager.
require __DIR__ . '/../vendor/autoload.php';

use App\Services\SecretsManager;

$key = $argv[1] ?? null;
if (! $key) {
    echo "Usage: php fetch-secret.php <secret-id>\n";
    exit(1);
}

$manager = new SecretsManager();
$val = $manager->get($key);

if ($val === null) {
    echo "Secret not found: {$key}\n";
    exit(2);
}

if (is_array($val)) {
    echo json_encode($val, JSON_PRETTY_PRINT) . "\n";
} else {
    echo $val . "\n";
}
