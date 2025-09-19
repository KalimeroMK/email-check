<?php

// Suppress deprecated warnings from Illuminate
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('log_errors', 0);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';

use App\ExistingDatabaseManager;
use App\EmailValidator;

// Load configuration
$config = require __DIR__ . '/config/app.php';

// Initialize managers
$existingDbManager = new ExistingDatabaseManager($config);
$emailValidator = new EmailValidator($config['settings']);

echo "🚀 Starting quick validation of valid emails...\n\n";

$batchSize = 50; // Smaller batches
$maxBatches = 20; // Maximum 20 batches (1000 emails)
$offset = 0;
$totalProcessed = 0;
$totalValidated = 0;
$allResults = [];

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
    $emailList = array_map(fn($email) => $email->email, $emails);

    echo "   📧 Validating " . count($emailList) . " emails...\n";

    // Validate them
    $batchResults = $emailValidator->validateBatch($emailList);
    $stats = $emailValidator->getStats($batchResults);

    $allResults = array_merge($allResults, $batchResults);
    $totalProcessed += $count;
    $totalValidated += $stats['valid'];

    echo "   ✅ Batch completed: {$count} processed, {$stats['valid']} valid, {$stats['invalid']} invalid\n";
    echo "   📊 Total so far: {$totalProcessed} processed, {$totalValidated} valid\n\n";

    $offset += $batchSize;

    // Pause to not overload DNS server
    usleep(500000); // 0.5 seconds
}

echo "🎉 === VALIDATION COMPLETED ===\n";
echo sprintf('📊 Total emails processed: %d%s', $totalProcessed, PHP_EOL);
echo sprintf('✅ Total validated as valid: %s%s', $totalValidated, PHP_EOL);
echo "❌ Total invalid: " . ($totalProcessed - $totalValidated) . "\n";

// Generate final statistics
$finalStats = $emailValidator->getStats($allResults);
echo "\n📈 === FINAL STATISTICS ===\n";
echo sprintf('Total: %s%s', $finalStats['total'], PHP_EOL);
echo "Valid: {$finalStats['valid']} ({$finalStats['valid_percentage']}%)\n";
echo sprintf('Invalid: %s%s', $finalStats['invalid'], PHP_EOL);
echo sprintf('DNS Errors: %s%s', $finalStats['dns_errors'], PHP_EOL);
echo sprintf('Format Errors: %s%s', $finalStats['format_errors'], PHP_EOL);

// Save only invalid emails if there are any
$invalidEmails = array_filter($allResults, fn(array $result): bool => !$result['is_valid']);

if ($invalidEmails !== []) {
    $invalidFile = 'invalid_emails_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($invalidFile, json_encode($invalidEmails, JSON_PRETTY_PRINT));
    echo "\n💾 Invalid emails saved to: {$invalidFile}\n";
} else {
    echo "\n🎉 All emails are valid! No invalid emails to save.\n";
}

// Save all results
$reportFile = 'validation_report_' . date('Y-m-d_H-i-s') . '.json';
$report = [
    'summary' => $finalStats,
    'timestamp' => date('Y-m-d H:i:s'),
    'total_processed' => $totalProcessed,
    'validation_results' => $allResults
];

file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
echo sprintf('📄 Detailed report saved to: %s%s', $reportFile, PHP_EOL);

echo "\n✨ Validation completed successfully!\n";
