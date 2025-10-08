<div align="center">
  <img src="./og-image.png" alt="PHP Email Check Logo" width="400">
  <h1>PHP Email Check 📧</h1>
</div>

[![Latest Stable Version](https://img.shields.io/packagist/v/kalimeromk/email-check.svg)](https://packagist.org/packages/kalimeromk/email-check)
[![License](https://img.shields.io/packagist/l/kalimeromk/email-check.svg)](https://packagist.org/packages/kalimeromk/email-check/)

A lightweight PHP library for advanced email address validation. It performs multi-layered checks, including syntax, domain validity (DNS), disposable service detection, and more.

## 📋 Table of Contents

- [Key Features](#key-features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Basic Usage](#basic-usage)
- [Advanced Features](#advanced-features)
  - [Pattern Filtering](#pattern-filtering)
  - [Disposable Email Detection](#disposable-email-detection)
  - [DNS Cache Configuration](#dns-cache-configuration)
  - [Mass Email Validation](#mass-email-validation)
  - [Domain Suggestions](#domain-suggestions)
- [Configuration](#configuration)
- [Testing](#testing)
- [API Reference](#api-reference)
- [Contributing](#contributing)
- [License](#license)

## ✨ Key Features

- ✅ **Format Check:** Validates the basic `user@domain.com` syntax using PHP's filter_var and advanced validation
- 🌐 **Domain Check:** Verifies the domain by checking for valid `MX` and `A` DNS records
- 🔍 **Advanced Validation:** Comprehensive email format validation with length checks and forbidden character detection
- ⚡ **DNS Caching:** Built-in caching for DNS queries to improve performance
- 💡 **Typo Correction Suggestions:** Offers "Did you mean?" suggestions for common typos in domain names
- 🚫 **Disposable Email Detection:** Blocks known disposable/temporary email services with auto-updating lists from multiple sources
- 📧 **SMTP Validation:** Optional real-time SMTP validation to verify mailbox existence (disabled by default)
- 🔧 **Configurable:** Customizable DNS servers, timeouts, and validation options
- 📦 **Batch Processing:** Validate multiple emails at once
- 🧪 **Comprehensive Testing:** 150+ tests with 500+ assertions covering all functionality
- 🚀 **Mass Validation:** Parallel processing for millions of emails with optimized performance

## ⚠️ Important Notice & Limitations

> **Please Note:** This package performs a detailed technical analysis of an email address. However, it **cannot 100% guarantee** that a specific mailbox actually exists or is active. The only definitive way to verify this is by sending an email with a confirmation link.

## 📦 Installation

Install the package easily via Composer.

```bash
composer require kalimeromk/email-check
```

## ⚙️ System Requirements

### Minimum Requirements

- **PHP:** 8.1 or higher
- **Memory:** 256MB RAM
- **CPU:** 2 cores
- **Extensions:** `ext-json`, `ext-mbstring`

### Recommended for Mass Validation

- **PHP:** 8.4 or higher
- **Memory:** 128GB RAM (for 9M+ emails)
- **CPU:** 40+ cores with hyperthreading (80 threads)
- **Storage:** RAID 10 SSD for optimal I/O performance
- **Extensions:** `ext-pcntl` (for parallel processing)

## 🚀 Quick Start

```php
<?php
require 'vendor/autoload.php';

use KalimeroMK\EmailCheck\EmailValidator;

$validator = new EmailValidator();
$result = $validator->validate('test@gmail.com');

if ($result['is_valid']) {
    echo "Email is valid!";
} else {
    echo "Email is invalid: " . implode(', ', $result['errors']);
}
```

## 📖 Basic Usage

### Understanding the Result

The `validate()` method returns an associative array with the following keys:

| Key                | Type         | Description                                                                               |
| :----------------- | :----------- | :---------------------------------------------------------------------------------------- |
| `email`            | string       | The submitted email address.                                                              |
| `is_valid`         | bool         | `true` if the email passes all key validations (format and domain).                       |
| `domain_valid`     | bool         | `true` if the domain has valid MX or A DNS records.                                       |
| `is_disposable`    | bool         | `true` if the email is from a known disposable service.                                   |
| `pattern_valid`    | bool         | `true` if email passes pattern filtering (fast rejection).                                |
| `pattern_status`   | string       | Pattern validation status: `passed`, `rejected`, or `warning`.                            |
| `matched_pattern`  | string\|null | Regex pattern that matched (if rejected), `null` if passed.                               |
| `smtp_valid`       | bool\|null   | `true` if SMTP validation confirms mailbox exists, `false` if failed, `null` if disabled. |
| `smtp_response`    | string\|null | SMTP server response (if SMTP validation enabled), `null` if disabled.                    |
| `smtp_status_code` | string\|null | Detailed SMTP status code (if SMTP validation enabled), `null` if disabled.               |
| `timestamp`        | string       | When the validation was performed.                                                        |

### Example Output

```php
$result = $validator->validate('test@gmail.com');

// Output:
[
    'email' => 'test@gmail.com',
    'is_valid' => true,
    'domain_valid' => true,
    'is_disposable' => false,
    'pattern_valid' => true,
    'pattern_status' => 'passed',
    'matched_pattern' => null,
    'smtp_valid' => null,
    'smtp_response' => null,
    'smtp_status_code' => null,
    'timestamp' => '2025-10-08 15:43:06'
]
```

## 🔧 Advanced Features

### Pattern Filtering

Pattern filtering provides ultra-fast rejection of obviously invalid emails before expensive DNS/SMTP checks:

```php
$validator = new EmailValidator([
    'enable_pattern_filtering' => true,
    'pattern_strict_mode' => false,
]);

$result = $validator->validate('invalid-email');

if ($result['pattern_status'] === 'rejected') {
    echo "Email rejected by pattern: " . $result['matched_pattern'];
    echo "Reason: " . $result['errors'][0];
}
```

**Pattern Status Values:**

- `passed` - Email passed all pattern checks
- `rejected` - Email matched an invalid pattern (fast rejection)
- `warning` - Email matched a strict pattern (if strict mode enabled)

**Common Invalid Patterns Detected:**

- Missing @ symbol
- Multiple @ symbols
- Multiple consecutive dots
- Starts/ends with dots or @
- Contains spaces or invalid characters
- Empty local/domain parts
- Emails that are too long

### Disposable Email Detection

The library includes an automatic update system for disposable email domains:

#### Manual Update

```bash
php src/Scripts/update-disposable-domains.php
```

#### Automatic Update (Cron Job)

```bash
# Add to crontab (run daily at 2 AM)
0 2 * * * /path/to/your/project/src/Scripts/auto-update-disposable-domains.sh
```

#### Monitoring Disposable Domains

```php
use KalimeroMK\EmailCheck\Detectors\DisposableEmailDetector;

$detector = new DisposableEmailDetector();
$metadata = $detector->getDomainsMetadata();

echo "Source: " . $metadata['source'] . "\n";
echo "Total domains: " . $metadata['total_domains'] . "\n";
echo "Last updated: " . ($metadata['last_updated'] ?? 'N/A') . "\n";
```

### DNS Cache Configuration

The `CachedDnsValidator` provides advanced caching capabilities:

#### Cache Drivers

**File Cache (Default):**

```php
$validator = new EmailValidator([
    'dns_cache_driver' => 'file',
    'dns_cache_ttl' => 3600, // 1 hour
]);
```

**Redis Cache:**

```php
$validator = new EmailValidator([
    'dns_cache_driver' => 'redis',
    'dns_cache_ttl' => 7200, // 2 hours
    'redis_host' => '127.0.0.1',
    'redis_port' => 6379,
]);
```

#### Cache Telemetry Monitoring

```php
use KalimeroMK\EmailCheck\Validators\CachedDnsValidator;

$cachedValidator = new CachedDnsValidator();

// Perform some validations
$cachedValidator->validateDomain('example.com');
$cachedValidator->validateDomain('google.com');
$cachedValidator->validateDomain('example.com'); // This should be a cache hit

// Get telemetry data
$telemetry = $cachedValidator->getTelemetry();

echo "Cache Hit Rate: " . $telemetry['hit_rate'] . "%\n";
echo "Total Requests: " . $telemetry['total_requests'] . "\n";
echo "Cache Hits: " . $telemetry['hits'] . "\n";
echo "Cache Misses: " . $telemetry['misses'] . "\n";
```

### Mass Email Validation

For processing millions of emails efficiently, use the mass validation system:

#### Basic Mass Validation

```bash
# Process a large email list (optimized for 40-core server)
php src/Scripts/mass-email-validator.php emails.json --batch-size=5000 --max-processes=40
```

#### Performance Configuration

```bash
# Optimized for high-performance servers (40+ cores, 128GB+ RAM)
php src/Scripts/mass-email-validator.php emails.json \
  --batch-size=5000 \
  --max-processes=40 \
  --memory-limit=1GB

# Ultra-fast mode for maximum performance (80 threads, 2GB per process)
php src/Scripts/mass-email-validator.php emails.json \
  --aggressive-mode \
  --batch-size=10000 \
  --max-processes=80 \
  --memory-limit=2GB
```

#### Monitoring Progress

```bash
# Monitor validation progress in real-time
php src/Scripts/monitor-validation.php src/data/mass_validation_*/progress.json
```

#### Performance Statistics

Based on testing with real email data on high-performance servers:

**Standard Mode (40 cores, 1GB per process):**

- **Processing Speed:** 8,000-12,000 emails/second
- **Memory Usage:** ~1GB per process
- **CPU Utilization:** 100% (all 40 cores)
- **Estimated Time:** ~15-20 minutes for 9 million emails

**Aggressive Mode (80 threads, 2GB per process):**

- **Processing Speed:** 15,000-25,000 emails/second
- **Memory Usage:** ~2GB per process
- **CPU Utilization:** 100% (all 80 threads)
- **Estimated Time:** ~8-12 minutes for 9 million emails

#### Output Files

The mass validator generates:

- `valid_emails.json` - All valid email addresses
- `invalid_emails.json` - All invalid email addresses
- `statistics.json` - Detailed performance metrics
- `progress.json` - Real-time progress tracking

#### Example Output

```json
{
  "summary": {
    "total_emails": 9000000,
    "processed_emails": 9000000,
    "valid_emails": 8991000,
    "invalid_emails": 9000,
    "validation_rate": 99.9
  },
  "performance": {
    "total_time_seconds": 720,
    "emails_per_second": 12500,
    "emails_per_hour": 45000000,
    "cpu_cores_used": 40,
    "memory_usage_gb": 40
  }
}
```

### Domain Suggestions

If the basic validation indicates that the domain is invalid, you can use a helper function to offer the user a correction suggestion:

```php
$userEmail = 'test@gmal.com'; // Email with a typo
$validator = new EmailValidator();
$result = $validator->validate($userEmail);

if (!$result['domain_valid']) {
    // If the domain is invalid, try to find a suggestion
    $suggestion = suggestDomainCorrection($userEmail);

    if ($suggestion) {
        echo "Did you mean: " . $suggestion . "?";
        // Output: Did you mean: test@gmail.com?
    }
}
```

## ⚙️ Configuration

### Environment Variables (.env)

Copy the `env.example` file to `.env` and configure your settings:

```bash
cp env.example .env
```

#### DNS Cache Configuration

```env
EMAIL_DNS_CACHE_TTL=3600     # Cache TTL in seconds (default: 3600 = 1 hour)
EMAIL_DNS_CACHE_DRIVER=file  # Cache driver: 'file', 'redis', 'array', 'null' (fallback: 'array')
```

#### Redis Configuration (used when EMAIL_DNS_CACHE_DRIVER=redis)

```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
```

#### SMTP Validation Settings

```env
CHECK_SMTP=false                    # Enable SMTP validation (disabled by default)
SMTP_TIMEOUT=10                     # SMTP connection timeout in seconds
SMTP_FROM_EMAIL=test@example.com   # From email for SMTP validation
SMTP_FROM_NAME=Email Validator      # From name for SMTP validation
```

#### Pattern Filtering Configuration

```env
ENABLE_PATTERN_FILTERING=true        # Enable fast pattern-based rejection
PATTERN_STRICT_MODE=false            # Enable strict pattern validation
```

### Programmatic Configuration

The `EmailValidator` accepts configuration options:

```php
$validator = new EmailValidator([
    'timeout' => 5,                    // DNS query timeout in seconds
    'dns_servers' => ['8.8.8.8', '1.1.1.1'], // DNS servers to use
    'check_mx' => true,                // Check MX records
    'check_a' => true,                 // Check A records as fallback
    'check_spf' => false,              // Check SPF records
    'check_dmarc' => false,            // Check DMARC records
    'use_advanced_validation' => true, // Enable advanced validation
    'enable_pattern_filtering' => true, // Enable pattern filtering
    'pattern_strict_mode' => false,    // Enable strict pattern validation
    'check_smtp' => false,             // Enable SMTP validation
    'smtp_timeout' => 10,              // SMTP timeout
    'smtp_from_email' => 'test@example.com', // SMTP from email
    'smtp_from_name' => 'Email Validator',   // SMTP from name
]);
```

## 🧪 Testing

The package includes comprehensive test coverage with PHPUnit:

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit tests/EmailValidatorTest.php
./vendor/bin/phpunit tests/DNSValidatorTest.php
./vendor/bin/phpunit tests/CachedDnsValidatorTest.php
./vendor/bin/phpunit tests/DomainSuggestionTest.php
./vendor/bin/phpunit tests/DisposableEmailDetectorTest.php
```

**Test Coverage:**

- **150+ tests** with **500+ assertions**
- Email validation (basic and edge cases)
- DNS validation and caching
- Domain suggestion functionality
- Disposable email detection
- Pattern filtering (fast rejection of invalid emails)
- SMTP validation (enabled/disabled states)
- Data source configuration (.env file reading)
- Helper functions
- Error handling and edge cases

## 📚 API Reference

### EmailValidator Class

#### Constructor

```php
public function __construct(array $config = [])
```

#### Methods

```php
public function validate(string $email): array
public function validateBulk(array $emails): array
public function getConfig(): array
public function setConfig(array $config): void
```

### DisposableEmailDetector Class

#### Methods

```php
public function isDisposable(string $email): bool
public function isDisposableDomain(string $domain): bool
public function getDisposableDomains(): array
public function getDomainsMetadata(): array
public function hasExternalData(): bool
public function getDataFilePath(): string
```

### CachedDnsValidator Class

#### Methods

```php
public function validateDomain(string $domain): array
public function checkMXRecords(string $domain): array
public function checkARecords(string $domain): array
public function getCacheStats(): array
public function getTelemetry(): array
public function resetTelemetry(): void
public function clearCache(): bool
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

**Made with ❤️ by [KalimeroMK](https://github.com/KalimeroMK)**
