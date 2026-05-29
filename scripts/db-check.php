<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$db = $app->make('db');

echo "TABLE CHECK:\n";
print_r($db->select("SHOW TABLES LIKE 'headline_experiments'"));
print_r($db->select("SHOW TABLES LIKE 'headline_variants'"));
print_r($db->select("SHOW COLUMNS FROM seo_meta LIKE 'discover_thumbnail'"));

echo "MIGRATIONS:\n";
print_r($db->select("SELECT migration,batch FROM migrations ORDER BY id DESC LIMIT 20"));
