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

echo "🔍 Verifying 'valid' emails from database...\n\n";

// Get total count first
echo "📊 Getting total 'valid' email count...\n";
$countResult = $existingDbManager->executeCustomQuery("SELECT COUNT(*) as total FROM check_emails WHERE status = 'valid'");
$totalEmails = $countResult['results'][0]->total ?? 0;
echo "📧 Total 'valid' emails to verify: {$totalEmails}\n\n";

$batchSize = 1000; // Process 1000 emails at a time
$maxBatches = ceil($totalEmails / $batchSize);
$offset = 0;
$totalProcessed = 0;
$actuallyValidEmails = [];
$actuallyInvalidEmails = [];
$startTime = time();

echo "⚙️  Configuration:\n";
echo "   Batch size: {$batchSize}\n";
echo "   Total batches: {$maxBatches}\n";
echo "   Using advanced validation (DNS + SMTP)\n\n";

for ($batch = 0; $batch < $maxBatches; $batch++) {
    $batchStartTime = time();
    echo "📦 Processing batch " . ($batch + 1) . "/{$maxBatches}...\n";

    // Fetch batch of 'valid' emails
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
    
    echo "   📧 Verifying {$count} emails...\n";

    // Validate emails with advanced validation
    $validationResults = $emailValidator->validateBatch($emailAddresses);
    
    // Separate actually valid and invalid emails
    foreach ($validationResults as $result) {
        if ($result['is_valid']) {
            $actuallyValidEmails[] = $result['email'];
        } else {
            $actuallyInvalidEmails[] = $result['email'];
        }
    }

    $totalProcessed += $count;
    $validCount = count($actuallyValidEmails);
    $invalidCount = count($actuallyInvalidEmails);
    
    $batchTime = time() - $batchStartTime;
    $elapsedTime = time() - $startTime;
    $remainingBatches = $maxBatches - ($batch + 1);
    $avgTimePerBatch = $elapsedTime / ($batch + 1);
    $estimatedRemaining = round(($remainingBatches * $avgTimePerBatch) / 60, 1);
    
    echo "   ✅ Batch completed: {$count} processed, {$validCount} actually valid, {$invalidCount} actually invalid ({$batchTime}s)\n";
    echo "   📊 Total so far: {$totalProcessed} processed, {$validCount} actually valid, {$invalidCount} actually invalid\n";
    echo "   ⏱️  Estimated remaining time: {$estimatedRemaining} minutes\n\n";

    $offset += $batchSize;
    
    // Save progress every 5 batches
    if (($batch + 1) % 5 == 0) {
        $progressFile = "verification_progress.json";
        file_put_contents($progressFile, json_encode([
            'batch' => $batch + 1,
            'total_batches' => $maxBatches,
            'processed' => $totalProcessed,
            'actually_valid' => $validCount,
            'actually_invalid' => $invalidCount,
            'elapsed_time' => $elapsedTime,
            'estimated_remaining' => $estimatedRemaining,
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));
        echo "   💾 Progress updated: {$progressFile}\n\n";
    }
}

$totalTime = time() - $startTime;
$totalMinutes = round($totalTime / 60, 1);

echo "🎉 === VERIFICATION COMPLETED ===\n";
echo "📊 Total emails processed: {$totalProcessed}\n";
echo "✅ Actually valid emails: " . count($actuallyValidEmails) . "\n";
echo "❌ Actually invalid emails: " . count($actuallyInvalidEmails) . "\n";
echo "⏱️  Total time: {$totalMinutes} minutes\n\n";

// Create timestamp for filenames
$timestamp = date('Y-m-d_H-i-s');

// Save actually valid emails to JSON
if (!empty($actuallyValidEmails)) {
    $validFile = "actually_valid_emails_{$timestamp}.json";
    file_put_contents($validFile, json_encode($actuallyValidEmails, JSON_PRETTY_PRINT));
    echo "✅ Actually valid emails saved to: {$validFile}\n";
} else {
    echo "ℹ️  No actually valid emails to save.\n";
}

// Save actually invalid emails to JSON
if (!empty($actuallyInvalidEmails)) {
    $invalidFile = "actually_invalid_emails_{$timestamp}.json";
    file_put_contents($invalidFile, json_encode($actuallyInvalidEmails, JSON_PRETTY_PRINT));
    echo "❌ Actually invalid emails saved to: {$invalidFile}\n";
} else {
    echo "ℹ️  No actually invalid emails to save.\n";
}

// Save verification statistics
$statsFile = "verification_stats_{$timestamp}.json";
file_put_contents($statsFile, json_encode([
    'total_processed' => $totalProcessed,
    'actually_valid_count' => count($actuallyValidEmails),
    'actually_invalid_count' => count($actuallyInvalidEmails),
    'actually_valid_percentage' => round((count($actuallyValidEmails) / $totalProcessed) * 100, 2),
    'actually_invalid_percentage' => round((count($actuallyInvalidEmails) / $totalProcessed) * 100, 2),
    'total_time_minutes' => $totalMinutes,
    'emails_per_minute' => round($totalProcessed / $totalMinutes, 1),
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT));
echo "📊 Verification statistics saved to: {$statsFile}\n";

echo "\n✨ Email verification completed successfully!\n";
echo "\n📋 Summary:\n";
echo "   - Actually valid emails: " . count($actuallyValidEmails) . "\n";
echo "   - Actually invalid emails: " . count($actuallyInvalidEmails) . "\n";
echo "   - You can delete the invalid ones from database\n";