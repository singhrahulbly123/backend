<?php
// Load Laravel with autoloader
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';

// Get a token
$db = $app['db'];
$token = $db->table('personal_access_tokens')
    ->where('tokenable_type', 'App\\Models\\User')
    ->select('token')
    ->first();

if ($token) {
    echo "Token: " . $token->token . "\n";
} else {
    echo "No tokens found\n";
    // List users
    $users = $db->table('users')->get();
    echo "Users: " . json_encode($users) . "\n";
}
?>
