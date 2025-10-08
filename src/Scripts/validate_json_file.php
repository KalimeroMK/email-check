<?php

// Suppress all warnings and errors
error_reporting(0);
ini_set('log_errors', 0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../vendor/autoload.php';

use App\EmailValidator;

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Initialize validator
$emailValidator = new EmailValidator($config['settings']);

echo "🚀 Starting JSON file email validation...\n\n";

// Check if file exists
$jsonFile = '/Users/zoran/Downloads/Email.json';
if (!file_exists($jsonFile)) {
    echo "❌ Error: File not found: {$jsonFile}\n";
    exit(1);
}

echo "📁 Reading file: {$jsonFile}\n";

// Read JSON file
$content = file_get_contents($jsonFile);
if ($content === false) {
    echo "❌ Error: Could not read file\n";
    exit(1);
}

$emails = json_decode($content, true);
if ($emails === null) {
    echo "❌ Error: Invalid JSON format\n";
    exit(1);
}

$totalEmails = count($emails);
echo "📧 Total emails to validate: {$totalEmails}\n\n";

if ($totalEmails === 0) {
    echo "ℹ️  No emails found in file\n";
    exit(0);
}

$batchSize = 100; // Process 100 emails at a time
$maxBatches = ceil($totalEmails / $batchSize);
$validEmails = [];
$invalidEmails = [];
$startTime = time();

echo "⚙️  Configuration:\n";
echo "   Batch size: {$batchSize}\n";
echo "   Total batches: {$maxBatches}\n\n";

for ($batch = 0; $batch < $maxBatches; $batch++) {
    $batchStartTime = time();
    echo "📦 Processing batch " . ($batch + 1) . "/{$maxBatches}...\n";

    // Get batch of emails
    $batchData = array_slice($emails, $batch * $batchSize, $batchSize);
    $count = count($batchData);

    if ($count == 0) {
        echo "✅ No more emails found.\n";
        break;
    }

    // Extract email addresses from objects
    $batchEmails = array_map(function($item) {
        return is_array($item) && isset($item['email']) ? $item['email'] : $item;
    }, $batchData);

    echo "   📧 Processing {$count} emails...\n";

    // Validate emails
    $validationResults = $emailValidator->validateBatch($batchEmails);
    
    foreach ($validationResults as $result) {
        if ($result['is_valid']) {
            $validEmails[] = $result['email'];
        } else {
            $invalidEmails[] = $result['email'];
        }
    }

    $totalProcessed = ($batch + 1) * $batchSize;
    if ($totalProcessed > $totalEmails) {
        $totalProcessed = $totalEmails;
    }
    
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
}

$totalTime = time() - $startTime;
$totalMinutes = round($totalTime / 60, 1);

echo "🎉 === VALIDATION COMPLETED ===\n";
echo "📊 Total emails processed: {$totalEmails}\n";
echo "✅ Total valid emails: " . count($validEmails) . "\n";
echo "❌ Total invalid emails: " . count($invalidEmails) . "\n";
echo "⏱️  Total time: {$totalMinutes} minutes\n\n";

// Create timestamp for filenames
$timestamp = date('Y-m-d_H-i-s');

// Save valid emails to JSON
if (!empty($validEmails)) {
    $validFile = "valid_emails_from_json_{$timestamp}.json";
    file_put_contents($validFile, json_encode($validEmails, JSON_PRETTY_PRINT));
    echo "✅ Valid emails saved to: {$validFile}\n";
} else {
    echo "ℹ️  No valid emails to save.\n";
}

// Save invalid emails to JSON
if (!empty($invalidEmails)) {
    $invalidFile = "invalid_emails_from_json_{$timestamp}.json";
    file_put_contents($invalidFile, json_encode($invalidEmails, JSON_PRETTY_PRINT));
    echo "❌ Invalid emails saved to: {$invalidFile}\n";
} else {
    echo "ℹ️  No invalid emails to save.\n";
}

// Save final statistics
$statsFile = "stats_from_json_{$timestamp}.json";
file_put_contents($statsFile, json_encode([
    'total_processed' => $totalEmails,
    'valid_count' => count($validEmails),
    'invalid_count' => count($invalidEmails),
    'valid_percentage' => round((count($validEmails) / $totalEmails) * 100, 2),
    'invalid_percentage' => round((count($invalidEmails) / $totalEmails) * 100, 2),
    'total_time_minutes' => $totalMinutes,
    'emails_per_minute' => round($totalEmails / $totalMinutes, 1),
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT));
echo "📊 Statistics saved to: {$statsFile}\n";

echo "\n✨ JSON file validation completed successfully!\n";
