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

echo "🚀 Starting simple email extraction...\n\n";

// Get total count first
echo "📊 Getting total email count...\n";
$countResult = $existingDbManager->executeCustomQuery("SELECT COUNT(*) as total FROM check_emails WHERE status = 'valid'");
$totalEmails = $countResult['results'][0]->total ?? 0;
echo "📧 Total emails to process: {$totalEmails}\n\n";

$batchSize = 1000; // Process 1000 emails at a time
$maxBatches = ceil($totalEmails / $batchSize);
$offset = 0;
$totalProcessed = 0;
$validEmails = [];
$invalidEmails = [];
$startTime = time();

echo "⚙️  Configuration:\n";
echo "   Batch size: {$batchSize}\n";
echo "   Total batches: {$maxBatches}\n\n";

for ($batch = 0; $batch < $maxBatches; $batch++) {
    $batchStartTime = time();
    echo "📦 Processing batch " . ($batch + 1) . "/{$maxBatches}...\n";

    // Fetch batch of valid emails
    $result = $existingDbManager->executeCustomQuery(
        sprintf("SELECT status, email FROM check_emails WHERE status = 'valid' LIMIT %d OFFSET %d", $batchSize, $offset)
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
    
    echo "   📧 Processing {$count} emails...\n";

    // Process emails with proper validation (DNS + SMTP)
    $validationResults = $emailValidator->validateBatch($emailAddresses);
    
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
    
    $batchTime = time() - $batchStartTime;
    $elapsedTime = time() - $startTime;
    $remainingBatches = $maxBatches - ($batch + 1);
    $avgTimePerBatch = $elapsedTime / ($batch + 1);
    $estimatedRemaining = round(($remainingBatches * $avgTimePerBatch) / 60, 1);
    
    echo "   ✅ Batch completed: {$count} processed, {$validCount} valid, {$invalidCount} invalid ({$batchTime}s)\n";
    echo "   📊 Total so far: {$totalProcessed} processed, {$validCount} valid, {$invalidCount} invalid\n";
    echo "   ⏱️  Estimated remaining time: {$estimatedRemaining} minutes\n\n";

    $offset += $batchSize;
    
    // Save progress every 5 batches
    if (($batch + 1) % 5 == 0) {
        $progressFile = "progress.json";
        file_put_contents($progressFile, json_encode([
            'batch' => $batch + 1,
            'total_batches' => $maxBatches,
            'processed' => $totalProcessed,
            'valid' => $validCount,
            'invalid' => $invalidCount,
            'elapsed_time' => $elapsedTime,
            'estimated_remaining' => $estimatedRemaining,
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));
        echo "   💾 Progress updated: {$progressFile}\n\n";
    }
}

$totalTime = time() - $startTime;
$totalMinutes = round($totalTime / 60, 1);

echo "🎉 === EXTRACTION COMPLETED ===\n";
echo "📊 Total emails processed: {$totalProcessed}\n";
echo "✅ Total valid emails: " . count($validEmails) . "\n";
echo "❌ Total invalid emails: " . count($invalidEmails) . "\n";
echo "⏱️  Total time: {$totalMinutes} minutes\n\n";

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

// Save final statistics
$statsFile = "stats_{$timestamp}.json";
file_put_contents($statsFile, json_encode([
    'total_processed' => $totalProcessed,
    'valid_count' => count($validEmails),
    'invalid_count' => count($invalidEmails),
    'valid_percentage' => round((count($validEmails) / $totalProcessed) * 100, 2),
    'invalid_percentage' => round((count($invalidEmails) / $totalProcessed) * 100, 2),
    'total_time_minutes' => $totalMinutes,
    'emails_per_minute' => round($totalProcessed / $totalMinutes, 1),
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT));
echo "📊 Statistics saved to: {$statsFile}\n";

echo "\n✨ Email extraction completed successfully!\n";
