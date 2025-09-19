<?php

// Suppress deprecated warnings from Illuminate
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('log_errors', 0);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

use App\ExistingDatabaseManager;

// Load configuration
$config = require __DIR__ . '/config/app.php';

// Initialize database manager
$dbManager = new ExistingDatabaseManager($config);

echo "Starting export of valid emails...\n";

$batchSize = 1000;
$offset = 0;
$totalExported = 0;
$allEmails = [];

do {
    echo "Fetching batch starting at offset {$offset}...\n";

    $result = $dbManager->executeCustomQuery(
        sprintf("SELECT status, email FROM check_emails WHERE status = 'valid' LIMIT %d OFFSET %s", $batchSize, $offset)
    );

    if (!$result['success']) {
        echo "Error: " . $result['message'] . "\n";
        break;
    }

    $emails = $result['results'];
    $count = count($emails);

    if ($count == 0) {
        break; // No more emails
    }

    // Add to our collection
    foreach ($emails as $email) {
        $allEmails[] = $email->email;
    }

    $totalExported += $count;
    $offset += $batchSize;

    echo "Exported {$count} emails (Total: {$totalExported})\n";

} while ($count == $batchSize);

echo "\nExport completed!\n";
echo sprintf('Total valid emails exported: %d%s', $totalExported, PHP_EOL);

// Save to JSON file
$jsonFile = 'valid_emails_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($jsonFile, json_encode($allEmails, JSON_PRETTY_PRINT));
echo sprintf('Saved to: %s%s', $jsonFile, PHP_EOL);

// Save to CSV file
$csvFile = 'valid_emails_' . date('Y-m-d_H-i-s') . '.csv';
$csv = "Email\n";
foreach ($allEmails as $email) {
    $csv .= $email . "\n";
}

file_put_contents($csvFile, $csv);
echo sprintf('Saved to: %s%s', $csvFile, PHP_EOL);

echo "\nDone!\n";
