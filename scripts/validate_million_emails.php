<?php

// Suppress all warnings and errors for production
error_reporting(0);
ini_set('log_errors', 0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../vendor/autoload.php';

use App\ProductionSMTPValidator;
use App\ExistingDatabaseManager;

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Initialize managers
$existingDbManager = new ExistingDatabaseManager($config);
$validator = new ProductionSMTPValidator([
    'timeout' => 3,
    'rate_limit_delay' => 0.5,
    'max_smtp_checks' => 50000, // Allow 50k SMTP checks
    'enable_logging' => true,
    'from_email' => 'test@example.com',
    'from_name' => 'Email Validator'
]);

echo "🚀 Million Email Validation System\n";
echo "==================================\n\n";

// Get total count
echo "📊 Getting total email count...\n";
$countResult = $existingDbManager->executeCustomQuery("SELECT COUNT(*) as total FROM check_emails");
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
echo "   Total batches: {$maxBatches}\n";
echo "   Timeout: 3 seconds\n";
echo "   Rate limit: 0.5 seconds\n";
echo "   Max SMTP checks: 50,000\n\n";

for ($batch = 0; $batch < $maxBatches; $batch++) {
    $batchStartTime = time();
    echo "📦 Processing batch " . ($batch + 1) . "/{$maxBatches}...\n";

    // Fetch batch of emails
    $result = $existingDbManager->executeCustomQuery(
        sprintf("SELECT email FROM check_emails LIMIT %d OFFSET %d", $batchSize, $offset)
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

    // Process emails
    $batchValid = 0;
    $batchInvalid = 0;
    
    foreach ($emailAddresses as $email) {
        $isValid = $validator->validateSmtp($email);
        
        if ($isValid) {
            $validEmails[] = $email;
            $batchValid++;
        } else {
            $invalidEmails[] = $email;
            $batchInvalid++;
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
    
    echo "   ✅ Batch completed: {$count} processed, {$batchValid} valid, {$batchInvalid} invalid ({$batchTime}s)\n";
    echo "   📊 Total so far: {$totalProcessed} processed, {$validCount} valid, {$invalidCount} invalid\n";
    echo "   ⏱️  Estimated remaining time: {$estimatedRemaining} minutes\n";
    
    // Show performance stats
    $stats = $validator->getStats();
    echo "   📈 Performance: Rate: " . round($count / $batchTime, 2) . "/s | " .
         "Cache hit: {$stats['cache_hit_rate']}% | " .
         "SMTP checks: {$stats['smtp_checks']}\n\n";

    $offset += $batchSize;
    
    // Save progress every 10 batches
    if (($batch + 1) % 10 == 0) {
        $progressFile = "million_progress.json";
        file_put_contents($progressFile, json_encode([
            'batch' => $batch + 1,
            'total_batches' => $maxBatches,
            'processed' => $totalProcessed,
            'valid' => $validCount,
            'invalid' => $invalidCount,
            'elapsed_time' => $elapsedTime,
            'estimated_remaining' => $estimatedRemaining,
            'performance_stats' => $stats,
            'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT));
        echo "   💾 Progress saved: {$progressFile}\n\n";
    }
    
    // Clear caches every 50 batches to prevent memory issues
    if (($batch + 1) % 50 == 0) {
        $validator->clearCaches();
        echo "   🧹 Caches cleared to free memory\n\n";
    }
}

$totalTime = time() - $startTime;
$totalMinutes = round($totalTime / 60, 1);

echo "🎉 === VALIDATION COMPLETED ===\n";
echo "📊 Total emails processed: {$totalProcessed}\n";
echo "✅ Total valid emails: " . count($validEmails) . "\n";
echo "❌ Total invalid emails: " . count($invalidEmails) . "\n";
echo "⏱️  Total time: {$totalMinutes} minutes\n";
echo "🚀 Processing rate: " . round($totalProcessed / $totalTime, 2) . " emails/second\n\n";

// Create timestamp for filenames
$timestamp = date('Y-m-d_H-i-s');

// Save valid emails to JSON
if (!empty($validEmails)) {
    $validFile = "million_valid_emails_{$timestamp}.json";
    file_put_contents($validFile, json_encode($validEmails, JSON_PRETTY_PRINT));
    echo "✅ Valid emails saved to: {$validFile}\n";
}

// Save invalid emails to JSON
if (!empty($invalidEmails)) {
    $invalidFile = "million_invalid_emails_{$timestamp}.json";
    file_put_contents($invalidFile, json_encode($invalidEmails, JSON_PRETTY_PRINT));
    echo "❌ Invalid emails saved to: {$invalidFile}\n";
}

// Save final statistics
$finalStats = $validator->getStats();
$statsFile = "million_stats_{$timestamp}.json";
file_put_contents($statsFile, json_encode([
    'total_processed' => $totalProcessed,
    'valid_count' => count($validEmails),
    'invalid_count' => count($invalidEmails),
    'valid_percentage' => round((count($validEmails) / $totalProcessed) * 100, 2),
    'invalid_percentage' => round((count($invalidEmails) / $totalProcessed) * 100, 2),
    'total_time_minutes' => $totalMinutes,
    'emails_per_minute' => round($totalProcessed / $totalMinutes, 1),
    'emails_per_second' => round($totalProcessed / $totalTime, 2),
    'performance_stats' => $finalStats,
    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT));
echo "📊 Final statistics saved to: {$statsFile}\n";

echo "\n✨ Million email validation completed successfully!\n";
