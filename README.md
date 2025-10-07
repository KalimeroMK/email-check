# PHP Email Check 📧

[![Latest Stable Version](https://img.shields.io/packagist/v/kalimeromk/email-check.svg)](https://packagist.org/packages/kalimeromk/email-check)
[![License](https://img.shields.io/packagist/l/kalimeromk/email-check.svg)](https://packagist.org/packages/kalimeromk/email-check/)

A lightweight PHP library for advanced email address validation. It performs multi-layered checks, including syntax, domain validity (DNS), disposable service detection, and more.

---

## Key Features

- ✅ **Format Check:** Validates the basic `user@domain.com` syntax using PHP's filter_var and advanced validation.
- 🌐 **Domain Check:** Verifies the domain by checking for valid `MX` and `A` DNS records.
- 🔍 **Advanced Validation:** Comprehensive email format validation with length checks and forbidden character detection.
- ⚡ **DNS Caching:** Built-in caching for DNS queries to improve performance.
- 💡 **Typo Correction Suggestions:** Offers "Did you mean?" suggestions for common typos in domain names.
- 🚫 **Disposable Email Detection:** Blocks known disposable/temporary email services.
- 📧 **SMTP Validation:** Optional real-time SMTP validation to verify mailbox existence (disabled by default).
- 🔧 **Configurable:** Customizable DNS servers, timeouts, and validation options.
- 📦 **Batch Processing:** Validate multiple emails at once.
- 🧪 **Comprehensive Testing:** 112 tests with 382 assertions covering all functionality.

---

## Important Notice & Limitations

> ⚠️ **Please Note:** This package performs a detailed technical analysis of an email address. However, it **cannot 100% guarantee** that a specific mailbox actually exists or is active. The only definitive way to verify this is by sending an email with a confirmation link.

---

## Installation

Install the package easily via Composer.

```bash
composer require kalimeromk/email-check
```

---

## Usage

### Basic Usage

Instantiate the `EmailValidator` class and call the `validate()` method.

```php
<?php

require 'vendor/autoload.php';

use KalimeroMK\EmailCheck\EmailValidator;

$email = 'test@gmail.com';

$validator = new EmailValidator();
$result = $validator->validate($email);

print_r($result);
```

### Understanding the Result

The `validate()` method returns an associative array with the following keys:

| Key               | Type         | Description                                                                               |
| :---------------- | :----------- | :---------------------------------------------------------------------------------------- |
| `email`           | string       | The submitted email address.                                                              |
| `is_valid`        | bool         | `true` if the email passes all key validations (format and domain).                       |
| `domain_valid`    | bool         | `true` if the domain has valid MX or A DNS records.                                       |
| `is_disposable`   | bool         | `true` if the email is from a known disposable service.                                   |
| `smtp_valid`      | bool\|null   | `true` if SMTP validation confirms mailbox exists, `false` if failed, `null` if disabled. |
| `errors`          | array        | Array of validation errors found.                                                         |
| `warnings`        | array        | Array of validation warnings.                                                             |
| `dns_checks`      | array        | Detailed DNS validation results.                                                          |
| `advanced_checks` | array        | Advanced validation results (if enabled).                                                 |
| `smtp_response`   | string\|null | SMTP server response (if SMTP validation enabled), `null` if disabled.                    |
| `timestamp`       | string       | When the validation was performed.                                                        |

### Advanced Usage: "Did You Mean?" Suggestions

If the basic validation indicates that the domain is invalid (`domain_valid` is `false`), you can use a helper function to offer the user a correction suggestion.

**Example:**

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

### Testing Domain Suggestions

You can test the domain suggestion functionality using the included test suite:

```bash
./vendor/bin/phpunit tests/DomainSuggestionTest.php
```

This will test various common typos and show you the suggestions.

### Running Tests

The package includes comprehensive test coverage with PHPUnit:

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit tests/EmailValidatorTest.php
./vendor/bin/phpunit tests/DNSValidatorTest.php
./vendor/bin/phpunit tests/CachedDnsValidatorTest.php
./vendor/bin/phpunit tests/DomainSuggestionTest.php
./vendor/bin/phpunit tests/HelpersTest.php
./vendor/bin/phpunit tests/EmailValidatorEdgeCasesTest.php
./vendor/bin/phpunit tests/SMTPValidatorTest.php
./vendor/bin/phpunit tests/DisposableEmailDetectorTest.php
```

**Test Coverage:**

- **112 tests** with **382 assertions**
- Email validation (basic and edge cases)
- DNS validation and caching
- Domain suggestion functionality
- Disposable email detection
- SMTP validation (enabled/disabled states)
- Data source configuration (.env file reading)
- Helper functions
- Error handling and edge cases

### Configuration

#### Environment Variables (.env)

Copy the `env.example` file to `.env` and configure your settings:

```bash
cp env.example .env
```

**Data Source Configuration:**

```env
DATA_SOURCE=database          # 'database' or 'json'
JSON_FILE_PATH=emails.json   # Path to JSON file if using JSON source
```

**Database Configuration (used when DATA_SOURCE=database):**

```env
DB_HOST=192.168.142.41
DB_PORT=3307
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password_here
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

**Email Validation Settings:**

```env
SMTP_MAX_CONNECTIONS=3
SMTP_MAX_CHECKS=50
SMTP_RATE_LIMIT_DELAY=3
LOCAL_SMTP_VALIDATION=true
LOCAL_SMTP_HOST=localhost
LOCAL_SMTP_PORT=1025
FROM_EMAIL=test@example.com
FROM_NAME=Email Validator
```

**SMTP Validation Settings:**

```env
CHECK_SMTP=false                    # Enable SMTP validation (disabled by default)
SMTP_TIMEOUT=10                     # SMTP connection timeout in seconds
SMTP_FROM_EMAIL=test@example.com   # From email for SMTP validation
SMTP_FROM_NAME=Email Validator      # From name for SMTP validation
```

**Batch Processing:**

```env
BATCH_SIZE=100
MAX_CONCURRENT=10
ASYNC_CHUNK_SIZE=100
ASYNC_TIMEOUT=30
ASYNC_SLEEP_TIME=50000
MEMORY_LIMIT=512M
MAX_EXECUTION_TIME=300
```

**Additional Configuration Options:**

```env
# Data Source Configuration
DATA_SOURCE=database          # 'database' or 'json'
JSON_FILE_PATH=emails.json   # Path to JSON file if using JSON source

# Validation Method
VALIDATION_METHOD=advanced    # 'basic', 'advanced', or 'strict'

# Output Configuration
SAVE_RESULTS=true            # Save validation results to files
OUTPUT_DIR=./output          # Directory for output files

# Debug Configuration
DEBUG=false                  # Enable debug mode
VERBOSE=false               # Enable verbose output
```

> **Note:** The `.env` file is automatically loaded by the `ConfigManager` class. Make sure to create your `.env` file based on `env.example` before running any validation scripts.

#### Programmatic Configuration

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
    'use_strict_rfc' => false,         // Use strict RFC validation
    'check_disposable' => false,       // Enable disposable email detection
    'disposable_strict' => true,       // Strict mode for disposable emails
    'check_smtp' => false,             // Enable SMTP validation (disabled by default)
    'smtp_timeout' => 10,             // SMTP connection timeout
    'smtp_max_connections' => 3,       // Max concurrent SMTP connections
    'smtp_max_checks' => 50,          // Max SMTP checks per batch
    'smtp_rate_limit_delay' => 3,     // Delay between SMTP checks
    'smtp_from_email' => 'test@example.com', // From email for SMTP
    'smtp_from_name' => 'Email Validator',   // From name for SMTP
]);
```

### Advanced Usage

#### Batch Validation

Validate multiple emails at once:

```php
$emails = ['test1@gmail.com', 'test2@yahoo.com', 'invalid-email'];
$results = $validator->validateBatch($emails);

foreach ($results as $result) {
    if ($result['is_valid']) {
        echo "Valid: " . $result['email'] . "\n";
    } else {
        echo "Invalid: " . $result['email'] . " - " . implode(', ', $result['errors']) . "\n";
    }
}
```

#### Custom DNS Validator

Use a custom DNS validator with caching:

```php
use KalimeroMK\EmailCheck\CachedDnsValidator;
use KalimeroMK\EmailCheck\EmailValidator;

$cachedDnsValidator = new CachedDnsValidator();
$validator = new EmailValidator([], $cachedDnsValidator);
```

### Disposable Email Detection

Block disposable/temporary email addresses:

```php
$validator = new EmailValidator([
    'check_disposable' => true,        // Enable disposable detection
    'disposable_strict' => true,       // Strict mode (block) or warning mode
]);

$result = $validator->validate('test@10minutemail.com');

if ($result['is_disposable']) {
    echo "Disposable email detected!";
    // In strict mode: $result['is_valid'] = false
    // In warning mode: $result['warnings'] contains warning
}
```

**Supported disposable services:**

- 10minutemail.com, guerrillamail.com, mailinator.com
- tempmail.org, throwaway.email, yopmail.com
- And 200+ other disposable email services

#### SMTP Validation (Optional)

Enable real-time SMTP validation to verify mailbox existence. **This feature is disabled by default** and must be explicitly enabled:

```php
$validator = new EmailValidator([
    'check_smtp' => true,              // Enable SMTP validation (disabled by default)
    'smtp_timeout' => 10,             // Connection timeout
    'smtp_from_email' => 'validator@yourdomain.com',
    'smtp_from_name' => 'Email Validator',
]);

$result = $validator->validate('user@example.com');

if ($result['smtp_valid'] === true) {
    echo "Mailbox exists and can receive emails!";
} elseif ($result['smtp_valid'] === false) {
    echo "SMTP validation failed: " . $result['smtp_response'];
} else {
    echo "SMTP validation is disabled";
}
```

**When SMTP validation is disabled:**

- `smtp_valid` will be `null`
- `smtp_response` will be `null`
- No SMTP connection attempts are made
- Validation is faster and more reliable

**Checking SMTP status:**

```php
$result = $validator->validate('user@example.com');

if ($result['smtp_valid'] === null) {
    echo "SMTP validation is disabled";
} elseif ($result['smtp_valid'] === true) {
    echo "SMTP validation passed - mailbox exists";
} else {
    echo "SMTP validation failed - mailbox may not exist";
}
```

**SMTP Validation Features:**

- Real-time connection to MX servers
- Mailbox existence verification
- Rate limiting and connection management
- Configurable timeouts and retry logic
- Batch processing support

> **Note:** SMTP validation is slower than DNS validation and may be blocked by some email providers. Use with caution in production environments.

#### Data Source Configuration

The EmailValidator can be configured to use either a database or JSON files as the data source:

**Using Database (default):**

```php
// In .env file:
DATA_SOURCE=database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=email_db
DB_USERNAME=user
DB_PASSWORD=password

$validator = new EmailValidator();
if ($validator->useDatabase()) {
    echo "Using database as data source";
    $dbConfig = $validator->getDatabaseConfig();
    echo "Database: " . $dbConfig['database'];
}
```

**Using JSON Files:**

```php
// In .env file:
DATA_SOURCE=json
JSON_FILE_PATH=custom_emails.json

$validator = new EmailValidator();
if ($validator->useJsonFile()) {
    echo "Using JSON file as data source";
    echo "File path: " . $validator->getJsonFilePath();
}
```

**Checking Data Source:**

```php
$validator = new EmailValidator();

switch ($validator->getDataSource()) {
    case 'database':
        echo "Database mode enabled";
        break;
    case 'json':
        echo "JSON file mode enabled";
        break;
    default:
        echo "Unknown data source";
}
```

---

## Validation Scripts

This package includes several powerful scripts for bulk email validation and analysis. All scripts are located in the `scripts/` directory.

### Available Scripts

#### 1. Quick Validation (`quick_validate.php`)

Validates emails in small batches with detailed progress reporting.

```bash
php scripts/quick_validate.php
```

**Features:**

- Processes emails in batches of 50
- Maximum 20 batches (1000 emails total)
- Real-time progress reporting
- Saves invalid emails to JSON file
- Generates detailed validation report

#### 2. Simple Extract (`simple_extract.php`)

Comprehensive email extraction and validation with progress tracking.

```bash
php scripts/simple_extract.php
```

**Features:**

- Processes emails in batches of 1000
- Progress tracking with time estimates
- Saves valid and invalid emails separately
- Generates statistics report
- Creates progress backup files

#### 3. JSON File Validation (`validate_json_file.php`)

Validates emails from a JSON file.

```bash
php scripts/validate_json_file.php
```

**Features:**

- Reads emails from JSON file
- Processes in batches of 100
- Supports both array and object formats
- Generates separate valid/invalid files
- Time estimation and progress tracking

#### 4. Server Analysis (`analyze_server.php`)

Advanced server analysis and email validation.

```bash
php scripts/analyze_server.php
```

**Features:**

- Comprehensive server analysis
- Database integration
- Detailed logging
- Performance metrics

### Script Output Files

All validation scripts generate timestamped output files:

- `valid_emails_YYYY-MM-DD_HH-MM-SS.json` - Valid email addresses
- `invalid_emails_YYYY-MM-DD_HH-MM-SS.json` - Invalid email addresses
- `validation_report_YYYY-MM-DD_HH-MM-SS.json` - Detailed validation report
- `stats_YYYY-MM-DD_HH-MM-SS.json` - Statistics and metrics

### Configuration

Before running validation scripts, ensure your configuration is properly set up:

1. **Copy the environment file:**

   ```bash
   cp env.example .env
   ```

2. **Configure your `.env` file** with your specific settings:

   - Database connection details
   - Email validation parameters
   - Batch processing settings
   - Output directory preferences

3. **Verify `config/app.php`** contains the base configuration structure.

The scripts will automatically load settings from your `.env` file using the `ConfigManager` class.

---

## Maintenance

### Updating the Lists

The lists for disposable, free, and role-based domains require periodic updates. This package includes a script to automatically download the latest community-maintained list of disposable domains.

To update the list, run the following command from your project's root directory:

```bash
php scripts/update-lists.php
```

### Running Validation

To validate emails using the package scripts:

1. **Quick validation** (recommended for testing):

   ```bash
   php scripts/quick_validate.php
   ```

2. **Full extraction** (for large datasets):

   ```bash
   php scripts/simple_extract.php
   ```

3. **JSON file validation**:
   ```bash
   php scripts/validate_json_file.php
   ```

---

## Contributing

Contributions are always welcome! The best way to help is by improving the lists for `free` and `role-based` domains or by opening an issue for suggestions and bug reports.

## License

This project is licensed under the **MIT License**.
