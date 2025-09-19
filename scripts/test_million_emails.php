<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\ProductionSMTPValidator;

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Initialize validator with production settings
$validator = new ProductionSMTPValidator([
    'timeout' => 3, // 3 seconds timeout
    'rate_limit_delay' => 0.5, // 0.5 seconds between checks
    'max_smtp_checks' => 10000, // Allow more SMTP checks
    'enable_logging' => true,
    'from_email' => 'test@example.com',
    'from_name' => 'Email Validator'
]);

echo "🚀 Production SMTP Validator - Million Email Test\n";
echo "================================================\n\n";

// Generate test emails (mix of valid and invalid)
$testEmails = [];
$domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'live.com', 'aol.com', 'icloud.com'];
$invalidDomains = ['nonexistent12345.com', 'fake45678.net', 'invalid99999.org'];

// Generate 1000 test emails
for ($i = 0; $i < 1000; $i++) {
    if ($i % 3 == 0) {
        // Invalid domain
        $domain = $invalidDomains[array_rand($invalidDomains)];
        $username = 'user' . $i;
    } else {
        // Valid domain
        $domain = $domains[array_rand($domains)];
        $username = 'user' . $i;
    }
    
    $testEmails[] = $username . '@' . $domain;
}

echo "📊 Test Configuration:\n";
echo "   Total emails: " . count($testEmails) . "\n";
echo "   Timeout: 3 seconds\n";
echo "   Rate limit: 0.5 seconds\n";
echo "   Max SMTP checks: 10,000\n";
echo "   Logging: Enabled\n\n";

$startTime = microtime(true);
$validCount = 0;
$invalidCount = 0;
$processedCount = 0;

echo "🔄 Processing emails...\n";

foreach ($testEmails as $index => $email) {
    $isValid = $validator->validateSmtp($email);
    
    if ($isValid) {
        $validCount++;
    } else {
        $invalidCount++;
    }
    
    $processedCount++;
    
    // Show progress every 100 emails
    if ($processedCount % 100 == 0) {
        $elapsed = microtime(true) - $startTime;
        $rate = $processedCount / $elapsed;
        $remaining = (count($testEmails) - $processedCount) / $rate;
        
        echo "   Processed: {$processedCount}/" . count($testEmails) . 
             " | Valid: {$validCount} | Invalid: {$invalidCount} | " .
             "Rate: " . round($rate, 2) . "/s | " .
             "ETA: " . round($remaining, 1) . "s\n";
    }
    
    // Show stats every 500 emails
    if ($processedCount % 500 == 0) {
        $stats = $validator->getStats();
        echo "   📈 Stats: Cache hits: {$stats['cache_hits']} | " .
             "DNS fallbacks: {$stats['dns_fallbacks']} | " .
             "SMTP failures: {$stats['smtp_failures']}\n";
    }
}

$totalTime = microtime(true) - $startTime;
$stats = $validator->getStats();

echo "\n🎉 Processing Complete!\n";
echo "======================\n";
echo "Total time: " . round($totalTime, 2) . " seconds\n";
echo "Emails processed: {$processedCount}\n";
echo "Valid emails: {$validCount}\n";
echo "Invalid emails: {$invalidCount}\n";
echo "Processing rate: " . round($processedCount / $totalTime, 2) . " emails/second\n";
echo "Valid percentage: " . round(($validCount / $processedCount) * 100, 2) . "%\n\n";

echo "📊 Performance Statistics:\n";
echo "SMTP checks: {$stats['smtp_checks']}\n";
echo "Cache hits: {$stats['cache_hits']}\n";
echo "Cache misses: {$stats['cache_misses']}\n";
echo "Cache hit rate: {$stats['cache_hit_rate']}%\n";
echo "DNS fallbacks: {$stats['dns_fallbacks']}\n";
echo "SMTP failures: {$stats['smtp_failures']}\n";
echo "Domain cache size: {$stats['domain_cache_size']}\n";
echo "SMTP cache size: {$stats['smtp_cache_size']}\n\n";

echo "📁 Log file created: logs/smtp_validation_" . date('Y-m-d_H-i-s') . ".log\n";
echo "💾 Memory usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
echo "💾 Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
