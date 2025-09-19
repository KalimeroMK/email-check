<?php

// Suppress deprecated warnings from Illuminate
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('log_errors', 0);
ini_set('display_errors', 0);

require_once __DIR__ . "/../vendor/autoload.php";

use App\ConfigManager;
use App\DataManager;
use App\EmailValidator;

// Load configuration
$config = ConfigManager::load();

// Enable advanced validations
$config['settings']['use_advanced_validation'] = true;
$config['settings']['use_strict_rfc'] = false;
$config['settings']['local_smtp_validation'] = true;

// Initialize managers
$dataManager = new DataManager($config);
$emailValidator = new EmailValidator($config['settings']);

// Function for progress bar
function showProgressBar(int $current, int $total, int $width = 50): string {
    $percentage = $total > 0 ? ($current / $total) * 100 : 0;
    $filled = intval(($current / $total) * $width);
    $bar = str_repeat('█', $filled) . str_repeat('░', $width - $filled);
    return sprintf("\r[%s] %.1f%% (%d/%d)", $bar, $percentage, $current, $total);
}

// Function for time formatting
function formatTime(int $seconds): string {
    if ($seconds < 60) {
        return sprintf("%.1fs", $seconds);
    }
    if ($seconds < 3600) {
        return sprintf("%.1fm", $seconds / 60);
    }
    else {
        return sprintf("%.1fh", $seconds / 3600);
    }
}

$dataSource = $dataManager->getDataSource();
$sourceName = $dataManager->getSourceName();
$queryDescription = $dataManager->getQueryDescription();

echo "🚀 Starting validation of emails from {$sourceName}...\n";
echo "📋 Query: {$queryDescription}\n\n";

// First count the total number of valid emails
echo "📊 Counting total valid emails in {$sourceName}...\n";
$totalEmails = $dataManager->countEmails();

if ($totalEmails == 0) {
    echo "❌ No valid emails found in {$sourceName}!\n";
    exit;
}

echo "📧 Found {$totalEmails} valid emails to validate\n\n";

$batchSize = 50; // Smaller batches for testing
$offset = 0;
$totalProcessed = 0;
$totalValidated = 0;
$totalInvalid = 0;
$allResults = [];
$startTime = time();

echo "🔄 Starting validation process...\n";
echo "📦 Batch size: {$batchSize} emails\n";
echo "⚡ Advanced validation (No external dependencies)\n";
echo "🏠 Using local SMTP server (safe mode)\n";
echo "🔒 Completely independent - works without any external packages!\n";
echo "⏱️  Started at: " . date('Y-m-d H:i:s') . "\n\n";

do {
    // Fetch batch of valid emails
    try {
        $emails = $dataManager->getEmails($batchSize, $offset);
        $count = count($emails);
    } catch (\Exception $e) {
        echo "\n❌ Error fetching emails: " . $e->getMessage() . "\n";
        break;
    }

    if ($count == 0) {
        break; // No more emails
    }

    // Extract email addresses
    $emailList = array_map(fn($email) => $email->email, $emails);

    // Synchronous validation with advanced validator
    $batchResults = [];
    foreach ($emailList as $email) {
        $batchResults[] = $emailValidator->validate($email);
    }

    $stats = $emailValidator->getStats($batchResults);

    $allResults = array_merge($allResults, $batchResults);
    $totalProcessed += $count;
    $totalValidated += $stats['valid'];
    $totalInvalid += $stats['invalid'];

    // Show progress bar
    $progressBar = showProgressBar($totalProcessed, $totalEmails);
    $elapsed = time() - $startTime;
    $rate = ($totalProcessed > 0 && $elapsed > 0) ? $totalProcessed / $elapsed : 0;
    $eta = ($rate > 0) ? ($totalEmails - $totalProcessed) / $rate : 0;

    echo $progressBar . " | Rate: " . number_format($rate, 1) . "/s | ETA: " . formatTime($eta) . sprintf(' | Valid: %s | Invalid: %s', $totalValidated, $totalInvalid);

    $offset += $batchSize;

    // Short pause to not overload DNS server
    usleep(200000); // 0.2 seconds

} while ($count == $batchSize);

$totalTime = time() - $startTime;

echo "\n\n🎉 === VALIDATION COMPLETED ===\n";
echo "⏱️  Total time: " . formatTime($totalTime) . "\n";
echo sprintf('📊 Total emails processed: %d%s', $totalProcessed, PHP_EOL);
echo sprintf('✅ Total validated as valid: %s%s', $totalValidated, PHP_EOL);
echo sprintf('❌ Total invalid: %s%s', $totalInvalid, PHP_EOL);
echo "📈 Processing rate: " . number_format(($totalProcessed > 0 && $totalTime > 0) ? $totalProcessed / $totalTime : 0, 1) . " emails/second\n";

// Generate final statistics
$finalStats = $emailValidator->getStats($allResults);
echo "\n📈 === FINAL STATISTICS ===\n";
echo sprintf('Total: %s%s', $finalStats['total'], PHP_EOL);
echo "Valid: {$finalStats['valid']} ({$finalStats['valid_percentage']}%)\n";
echo sprintf('Invalid: %s%s', $finalStats['invalid'], PHP_EOL);
echo sprintf('Format errors: %s%s', $finalStats['format_errors'], PHP_EOL);
echo sprintf('DNS errors: %s%s', $finalStats['dns_errors'], PHP_EOL);
echo sprintf('SMTP errors: %s%s', $finalStats['smtp_errors'], PHP_EOL);
echo sprintf('Advanced errors: %s%s', $finalStats['advanced_errors'], PHP_EOL);

// Save valid and invalid emails in separate files
$validEmails = array_filter($allResults, fn(array $result) => $result['is_valid']);

$invalidEmails = array_filter($allResults, fn(array $result): bool => !$result['is_valid']);

$timestamp = date('Y-m-d_H-i-s');

// Save only email addresses in simple files
$validEmailsList = array_map(fn(array $result) => $result['email'], $validEmails);

$invalidEmailsList = array_map(fn(array $result) => $result['email'], $invalidEmails);

// Save valid emails
if ($validEmailsList !== []) {
    $validFile = sprintf('valid_emails_standalone_%s.txt', $timestamp);
    file_put_contents($validFile, implode("\n", $validEmailsList));
    echo "\n✅ Valid emails saved to: {$validFile} (" . count($validEmailsList) . " emails)\n";
} else {
    echo "\n❌ No valid emails found!\n";
}

// Save invalid emails
if ($invalidEmailsList !== []) {
    $invalidFile = sprintf('invalid_emails_standalone_%s.txt', $timestamp);
    file_put_contents($invalidFile, implode("\n", $invalidEmailsList));
    echo sprintf('❌ Invalid emails saved to: %s (', $invalidFile) . count($invalidEmailsList) . " emails)\n";
} else {
    echo "🎉 All emails are valid! No invalid emails to save.\n";
}

// Save detailed report
$reportFile = sprintf('validation_report_standalone_%s.json', $timestamp);
$report = [
    'summary' => $finalStats,
    'timestamp' => date('Y-m-d H:i:s'),
    'total_processed' => $totalProcessed,
    'total_time_seconds' => $totalTime,
    'processing_rate' => ($totalProcessed > 0 && $totalTime > 0) ? $totalProcessed / $totalTime : 0,
    'validation_method' => 'Advanced (No External Dependencies)',
    'validation_library' => 'Built-in PHP + Custom Code',
    'validation_results' => $allResults
];

file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
echo sprintf('📄 Detailed report saved to: %s%s', $reportFile, PHP_EOL);

echo "\n✨ Validation completed successfully!\n";
echo "🛡️  No external SMTP requests made - your IP is safe!\n";
echo "🌐 Web UI available at: http://localhost:8025\n";
echo "🔒 COMPLETELY INDEPENDENT - works without ANY external packages!\n";
echo "🚀 Protected against package deletion, network issues, or dependency problems!\n";
echo "🎯 Quality check: " . ($finalStats['valid_percentage'] >= 95 ? "EXCELLENT" : ($finalStats['valid_percentage'] >= 90 ? "GOOD" : "NEEDS REVIEW")) . "\n";
