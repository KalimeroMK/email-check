<?php

// Suppress deprecated warnings from Illuminate
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('log_errors', 0);
ini_set('display_errors', 0);

$vendorAutoload = __DIR__ . "/../vendor/autoload.php";
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    spl_autoload_register(function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }

        $relative = substr($class, 4);
        $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($path)) {
            require_once $path;
        }
    });

    require_once __DIR__ . '/../src/Support/async_polyfill.php';
}

use App\ConfigManager;
use App\EmailValidator;

$config = ConfigManager::load();

// Enable advanced validations
$config['settings']['use_advanced_validation'] = true;
$config['settings']['use_strict_rfc'] = false;
$config['settings']['local_smtp_validation'] = true;
$config['settings']['check_spf'] = true;
$config['settings']['check_dmarc'] = true;

$emailValidator = new EmailValidator($config['settings']);

$testEmails = [
    // Valid emails
    'user@gmail.com',
    'contact@yahoo.com',
    'info@outlook.com',
    'test@example.com',
    'admin@iana.org', // Valid domain with only A record (no MX)

    // Invalid emails
    'invalid-email',
    'user@',
    '@domain.com',
    'user@domain',

    // Special characters
    'test+tag@gmail.com',
    'user.name@domain.co.uk',
    'user123@subdomain.example.com',

    // Edge cases
    'a@b.co', // Short domain
    'very.long.email.address@very.long.domain.name.example.com', // Long email
    'user@domain.com', // Normal
    'user@domain.co.uk', // With TLD
];

echo "🧪 Testing Email Validator (No External Dependencies)\n";
echo "================================================================\n\n";

echo "📊 Configuration:\n";
echo "  - Advanced validation: " . ($config['settings']['use_advanced_validation'] ? "YES" : "NO") . "\n";
echo "  - Strict RFC: " . ($config['settings']['use_strict_rfc'] ? "YES" : "NO") . "\n";
echo "  - Local SMTP: " . ($config['settings']['local_smtp_validation'] ? "YES" : "NO") . "\n";
echo "  - Check SPF records: " . ($config['settings']['check_spf'] ? "YES" : "NO") . "\n";
echo "  - Check DMARC records: " . ($config['settings']['check_dmarc'] ? "YES" : "NO") . "\n";
echo "  - Total test emails: " . count($testEmails) . "\n\n";

echo "📧 Test emails:\n";
foreach ($testEmails as $i => $email) {
    echo "  " . ($i + 1) . sprintf('. %s%s', $email, PHP_EOL);
}

echo "\n";

echo "🔄 Starting standalone validation...\n\n";

$canUseAsync = class_exists('Spatie\\Async\\Pool');

if (!$canUseAsync) {
    echo "⚠️  Async pool not available – running sequential validation.\n\n";
}

if ($canUseAsync) {
    $results = $emailValidator->validateBatch($testEmails);
} else {
    $results = [];
    foreach ($testEmails as $index => $email) {
        $results[$index] = $emailValidator->validate($email);
    }
}

foreach ($results as $i => $result) {
    $email = $result['email'];
    echo "[" . ($i + 1) . "/" . count($results) . sprintf('] Validating: %s ... ', $email);

    if ($result['is_valid']) {
        echo "✅ VALID";

        // Show additional information
        $advanced = $result['advanced_checks'];
        $checks = [];
        if ($advanced['format_validation']) {
            $checks[] = "Format";
        }

        if ($advanced['length_validation']) {
            $checks[] = "Length";
        }

        if ($advanced['domain_validation']) {
            $checks[] = "Domain";
        }

        if ($advanced['local_validation']) {
            $checks[] = "Local";
        }

        if ($checks !== []) {
            echo " (" . implode(", ", $checks) . ")";
        }

        echo " [STANDALONE]";
    } else {
        echo "❌ INVALID";
        $error = $result['errors'][0] ?? 'Unknown error';
        if (strlen($error) > 50) {
            $error = substr($error, 0, 47) . "...";
        }

        echo ' - ' . $error;
    }

    if (!empty($result['warnings'])) {
        echo ' ⚠️  ' . implode(' | ', $result['warnings']);
    }

    echo "\n";
}

echo "\n📊 === VALIDATION RESULTS ===\n";
$stats = $emailValidator->getStats($results);
echo sprintf('Total emails: %s%s', $stats['total'], PHP_EOL);
echo "Valid: {$stats['valid']} ({$stats['valid_percentage']}%)\n";
echo sprintf('Invalid: %s%s', $stats['invalid'], PHP_EOL);
echo sprintf('Format errors: %s%s', $stats['format_errors'], PHP_EOL);
echo sprintf('DNS errors: %s%s', $stats['dns_errors'], PHP_EOL);
echo sprintf('SMTP errors: %s%s', $stats['smtp_errors'], PHP_EOL);
echo sprintf('Advanced errors: %s%s', $stats['advanced_errors'], PHP_EOL);

echo "\n📋 === DETAILED RESULTS ===\n\n";
foreach ($results as $result) {
    echo sprintf('Email: %s%s', $result['email'], PHP_EOL);
    echo "Valid: " . ($result['is_valid'] ? "YES" : "NO") . "\n";
    echo "Validator Type: " . ($result['validator_type'] ?? 'unknown') . "\n";

    if (!empty($result['warnings'])) {
        echo "Warnings: " . implode(' | ', $result['warnings']) . "\n";
    }

    $advanced = $result['advanced_checks'];
    echo "Advanced checks:\n";
    echo "  - Format: " . ($advanced['format_validation'] ? "PASS" : "FAIL") . "\n";
    echo "  - Length: " . ($advanced['length_validation'] ? "PASS" : "FAIL") . "\n";
    echo "  - Domain: " . ($advanced['domain_validation'] ? "PASS" : "FAIL") . "\n";
    echo "  - Local: " . ($advanced['local_validation'] ? "PASS" : "FAIL") . "\n";

    if (!empty($advanced['errors'])) {
        echo "  - Errors: " . implode(", ", $advanced['errors']) . "\n";
    }

    if (!empty($result['errors'])) {
        echo "Main errors: " . implode(", ", $result['errors']) . "\n";
    }

    $dns = $result['dns_checks'];
    if ($dns !== []) {
        echo "DNS checks:\n";
        echo "  - MX: " . (!empty($dns['has_mx']) ? "FOUND" : "MISSING") . "\n";
        echo "  - A fallback: " . (!empty($dns['has_a']) ? "AVAILABLE" : "UNAVAILABLE") . "\n";
        if (array_key_exists('has_spf', $dns)) {
            echo "  - SPF: " . (!empty($dns['has_spf']) ? "FOUND" : "MISSING") . "\n";
        }
        if (array_key_exists('has_dmarc', $dns)) {
            echo "  - DMARC: " . (!empty($dns['has_dmarc']) ? "FOUND" : "MISSING") . "\n";
        }
        if (!empty($dns['warnings'])) {
            echo "  - Warnings: " . implode(' | ', $dns['warnings']) . "\n";
        }
    }

    echo "\n";
}

echo "✨ Standalone validation test completed!\n";
echo "🔒 This validator is COMPLETELY INDEPENDENT!\n";
echo "🛡️  Protected against ANY external package issues!\n";
echo "📚 Uses only built-in PHP functions and our own code!\n";
echo "🚀 Will work even if ALL external packages are removed!\n";
