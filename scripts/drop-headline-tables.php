<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$schema = $app->make('db')->getSchemaBuilder();

$schema->dropIfExists('headline_variants');
$schema->dropIfExists('headline_experiments');
echo "Dropped headline_variants and headline_experiments if they existed\n";
