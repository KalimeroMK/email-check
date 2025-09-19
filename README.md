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
    $suggestion = suggestDomainCorrection($userEmail); // Assuming you have implemented this function

    if ($suggestion) {
        echo "Did you mean: " . $suggestion . "?";
        // Output: Did you mean: test@gmail.com?
    }
}
```

---

## Maintenance

### Updating the Lists

The lists for disposable, free, and role-based domains require periodic updates. This package includes a script to automatically download the latest community-maintained list of disposable domains.

To update the list, run the following command from your project's root directory:

```bash
php scripts/update-lists.php
```
*Note: Adjust the path `scripts/update-lists.php` to match your project's structure.*

---

## Contributing

Contributions are always welcome! The best way to help is by improving the lists for `free` and `role-based` domains or by opening an issue for suggestions and bug reports.

## License

This project is licensed under the **MIT License**.