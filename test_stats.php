<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get a user with a token
$user = App\Models\User::first();
if (!$user) {
    echo "No users found in database\n";
    exit(1);
}

// Create a token for the user
$token = $user->createToken('test-token')->plainTextToken;
echo "Created token for user: {$user->name}\n";

// Make a request to the stats endpoint
$url = 'http://localhost:8000/api/buildings/stats';
$headers = [
    'Authorization: Bearer ' . $token,
    'Accept: application/json',
    'Content-Type: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "cURL Error: $error\n";
    exit(1);
}

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

// Also test the stats method directly
echo "\n--- Direct method test ---\n";
$request = new Illuminate\Http\Request();
$request->setUserResolver(function() use ($user) {
    return $user;
});

// Simulate middleware adding entitlement filters
$service = new App\Services\UserEntitlementService();
$entitlements = $service->getUserEntitlements($user);
$filters = $service->generateEntitlementFilters($entitlements);
$request->merge(['entitlement_filters' => $filters]);

$controller = new App\Http\Controllers\Api\BuildingController($service);
$result = $controller->stats($request);

echo "Direct result: " . $result->getContent() . "\n";