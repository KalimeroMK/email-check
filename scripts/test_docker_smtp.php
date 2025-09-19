<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\DockerSMTPValidator;

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Initialize validator
$validator = new DockerSMTPValidator($config['settings']);

echo "🐳 Testing Docker SMTP Validator\n";
echo "================================\n\n";

// Test emails
$testEmails = [
    'test@gmail.com',
    'nonexistent@gmail.com',
    'invalid@nonexistent-domain-12345.com',
    'admin@google.com',
    'fake@yahoo.com'
];

foreach ($testEmails as $email) {
    echo "Testing: {$email}\n";
    
    $startTime = microtime(true);
    $isValid = $validator->validateSmtp($email);
    $endTime = microtime(true);
    
    $duration = round(($endTime - $startTime) * 1000, 2);
    
    echo "  Result: " . ($isValid ? "✅ VALID" : "❌ INVALID") . "\n";
    echo "  Time: {$duration}ms\n";
    echo "  SMTP checks: " . $validator->getSmtpCheckCount() . "\n\n";
}

echo "Total SMTP checks performed: " . $validator->getSmtpCheckCount() . "\n";
echo "Rate limit delay: " . ($config['settings']['rate_limit_delay'] ?? 2) . " seconds\n";
echo "Max SMTP checks: " . ($config['settings']['max_smtp_checks'] ?? 100) . "\n";
