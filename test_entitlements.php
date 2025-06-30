<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
if (!$user) {
    echo "No users found in database\n";
    exit(1);
}

echo "User: {$user->name} ({$user->email})\n";

$service = new App\Services\UserEntitlementService();
$entitlements = $service->getUserEntitlements($user);
$filters = $service->generateEntitlementFilters($entitlements);

echo "Entitlements count: " . $entitlements->count() . "\n";
echo "Filters: " . json_encode($filters, JSON_PRETTY_PRINT) . "\n";

// Test building count with filters
$query = App\Models\Building::query();
$query->where(function ($filterQuery) use ($filters) {
    $filterQuery->applyEntitlementFilters($filters);
});

echo "Buildings accessible: " . $query->count() . "\n";