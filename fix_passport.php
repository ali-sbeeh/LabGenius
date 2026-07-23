<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find a client
$client = DB::table('oauth_clients')->first();
if ($client) {
    echo "Found client: " . $client->id . "\n";
    
    // Check if it exists in the oauth_personal_access_clients table
    $exists = DB::table('oauth_personal_access_clients')->where('client_id', $client->id)->exists();
    if (!$exists) {
        DB::table('oauth_personal_access_clients')->insert([
            'client_id' => $client->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "Inserted client ID " . $client->id . " into oauth_personal_access_clients\n";
    } else {
        echo "Client already in oauth_personal_access_clients.\n";
    }
} else {
    echo "No clients found.\n";
}
