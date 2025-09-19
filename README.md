# PHP Email Check 📧

[![Latest Stable Version](https://img.shields.io/packagist/v/kalimeromk/email-check.svg)](https://packagist.org/packages/kalimeromk/email-check)
[![License](https://img.shields.io/packagist/l/kalimeromk/email-check.svg)](https://packagist.org/packages/kalimeromk/email-check)

A lightweight PHP library for advanced email address validation. It performs multi-layered checks, including syntax, domain validity (DNS), disposable service detection, and more.

---

## Key Features

* ✅ **Format Check:** Validates the basic `user@domain.com` syntax.
* 🌐 **Domain Check:** Verifies the domain by checking for valid `MX` and `A` DNS records.
* 🗑️ **Disposable Address Detection:** Blocks known disposable or throwaway email services.
* 🆓 **Free Service Detection:** Identifies addresses from free providers (Gmail, Yahoo, etc.).
* 👨‍💼 **Role-Based Address Detection:** Recognizes generic, role-based addresses like `info@`, `admin@`, `support@`.
* 💡 **Typo Correction Suggestions:** Offers "Did you mean?" suggestions for common typos in domain names.

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

Instantiate the `EmailCheck` class and call the `check()` method.

```php
<?php

require 'vendor/autoload.php';

use KalimeroMK\EmailCheck\EmailCheck;

$email = 'test@gmail.com';

$validator = new EmailCheck($email);
$result = $validator->check();

print_r($result);
```

### Understanding the Result

The `check()` method returns an associative array with the following keys:

| Key             | Type    | Description                                                              |
|:----------------|:--------|:-------------------------------------------------------------------------|
| `email`         | string  | The submitted email address.                                             |
| `is_valid`      | bool    | `true` if the email passes all key validations (format and domain).      |
| `format_valid`  | bool    | `true` if the email address syntax is correct.                           |
| `domain_valid`  | bool    | `true` if the domain has valid MX or A DNS records.                      |
| `is_disposable` | bool    | `true` if the domain is from a known disposable email service.           |
| `is_free`       | bool    | `true` if the domain is from a known free provider (Gmail, Yahoo...).    |
| `is_role_based` | bool    | `true` if the address is a generic, role-based one (info@, admin@...).   |

### Advanced Usage: "Did You Mean?" Suggestions

If the basic validation indicates that the domain is invalid (`domain_valid` is `false`), you can use a helper function to offer the user a correction suggestion.

**Example:**

```php
$userEmail = 'test@gmal.com'; // Email with a typo
$validator = new EmailCheck($userEmail);
$result = $validator->check();

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

You can test the domain suggestion functionality:

```bash
php test_domain_suggestion.php
```

This will test various common typos and show you the suggestions.

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

Before running validation scripts, ensure your `config/app.php` is properly configured with:

- Database connection settings
- Email validation settings
- DNS validation parameters

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