<?php

// Suppress all warnings and errors
error_reporting(0);
ini_set('log_errors', 0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../vendor/autoload.php';

use App\ExistingDatabaseManager;
use App\EmailValidator;

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Initialize managers
$existingDbManager = new ExistingDatabaseManager($config);
$emailValidator = new EmailValidator($config['settings']);

echo "🚀 Starting email extraction and validation...\n\n";

$batchSize = 100; // Process 100 emails at a time
$maxBatches = 50; // Maximum 50 batches (5000 emails)
$offset = 0;
$totalProcessed = 0;
$validEmails = [];
$invalidEmails = [];

for ($batch = 0; $batch < $maxBatches; $batch++) {
    echo "📦 Processing batch " . ($batch + 1) . "/{$maxBatches}...\n";

    // Fetch batch of valid emails
    $result = $existingDbManager->executeCustomQuery(
        sprintf("SELECT status, email FROM check_emails WHERE status = 'valid' LIMIT %d OFFSET %s", $batchSize, $offset)
    );

    if (!$result['success']) {
        echo "❌ Error: " . $result['message'] . "\n";
        break;
    }

    $emails = $result['results'];
    $count = count($emails);

    if ($count == 0) {
        echo "✅ No more emails found.\n";
        break;
    }

    // Extract email addresses
    $emailAddresses = array_map(fn($row) => $row->email, $emails);
    
    echo "   📧 Validating {$count} emails...\n";

    // Validate emails
    $validationResults = $emailValidator->validateBatch($emailAddresses);
    
    // Separate valid and invalid emails
    foreach ($validationResults as $result) {
        if ($result['is_valid']) {
            $validEmails[] = $result['email'];
        } else {
            $invalidEmails[] = $result['email'];
        }
    }

    $totalProcessed += $count;
    $validCount = count($validEmails);
    $invalidCount = count($invalidEmails);
    
    echo "   ✅ Batch completed: {$count} processed, {$validCount} valid, {$invalidCount} invalid\n";
    echo "   📊 Total so far: {$totalProcessed} processed, {$validCount} valid, {$invalidCount} invalid\n\n";

    $offset += $batchSize;
}

echo "🎉 === EXTRACTION COMPLETED ===\n";
echo "📊 Total emails processed: {$totalProcessed}\n";
echo "✅ Total valid emails: " . count($validEmails) . "\n";
echo "❌ Total invalid emails: " . count($invalidEmails) . "\n\n";

// Create timestamp for filenames
$timestamp = date('Y-m-d_H-i-s');

// Save valid emails to JSON
if (!empty($validEmails)) {
    $validFile = "valid_emails_{$timestamp}.json";
    file_put_contents($validFile, json_encode($validEmails, JSON_PRETTY_PRINT));
    echo "✅ Valid emails saved to: {$validFile}\n";
} else {
    echo "ℹ️  No valid emails to save.\n";
}

// Save invalid emails to JSON
if (!empty($invalidEmails)) {
    $invalidFile = "invalid_emails_{$timestamp}.json";
    file_put_contents($invalidFile, json_encode($invalidEmails, JSON_PRETTY_PRINT));
    echo "❌ Invalid emails saved to: {$invalidFile}\n";
} else {
    echo "ℹ️  No invalid emails to save.\n";
}

echo "\n✨ Email extraction completed successfully!\n";
