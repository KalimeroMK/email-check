# Email Check Application

Standalone email validation with DNS checks for analyzing email databases.

## 🚀 Technologies

- **PHP 8.1+**
- **MySQL** - Database integration
- **JSON** - File data source
- **DNS Validation** - MX and A record checks
- **Environment Configuration** - Secure configuration via .env
- **Composer** - Dependency management

## 📁 Project Structure

```
email-check/
├── src/
│   ├── EmailValidator.php           # Core validation (DNS only)
│   ├── DNSValidator.php             # DNS checks
│   ├── ExistingDatabaseManager.php # MySQL integration
│   ├── ConfigManager.php            # Environment configuration
│   ├── DataManager.php              # Data source management
│   └── QueryManager.php             # Query system
├── scripts/
│   ├── simple_extract.php           # Database validation
│   ├── quick_validate.php           # Quick test
│   └── validate_json_file.php       # JSON validation
├── analyze_server.php               # Server analysis command
├── test_json.php                    # JSON testing command
├── config/
│   └── app.php                      # Configuration
├── .env.example                     # Environment variables example
├── composer.json
└── README.md
```

## ⚙️ Installation

1. **Clone the project:**
```bash
git clone https://github.com/KalimeroMK/email-check.git
cd email-check
```

2. **Install dependencies:**
```bash
composer install
```

3. **Configure environment:**
```bash
# Option 1: Create .env file
cp .env.example .env
# Edit .env with your database credentials

# Option 2: Set environment variables directly
export DB_HOST="your_database_host"
export DB_PORT="3306"
export DB_DATABASE="your_database_name"
export DB_USERNAME="your_username"
export DB_PASSWORD="your_password"
```

## 🚀 Quick Start

1. **Set environment variables:**
```bash
export DB_HOST="your_database_host"
export DB_PORT="3306"
export DB_DATABASE="your_database_name"
export DB_USERNAME="your_username"
export DB_PASSWORD="your_password"
```

2. **Run validation:**
```bash
# Quick test (1000 emails)
composer test

# Full validation (all emails from database)
composer validate

# Analyze all server emails (detailed analysis)
composer analyze

# Test JSON file (provide path as argument)
composer test-json /path/to/emails.json
```

## 📋 Available Commands

### 🔍 Server Analysis
```bash
# Analyze all valid emails from database
php analyze_server.php
# or
composer analyze
```
- Analyzes all emails with status 'valid' from database
- Saves results to timestamped JSON files
- Shows progress every 10 batches
- Estimated time: ~15 minutes for 284K emails

### 📄 JSON File Testing
```bash
# Test specific JSON file
php test_json.php /path/to/emails.json
# or
composer test-json /path/to/emails.json
```
- Validates emails from any JSON file
- Supports both array format and object format
- Saves results to timestamped JSON files
- Fast processing for small files

### ⚡ Quick Testing
```bash
# Quick validation test
php scripts/quick_validate.php
# or
composer test
```
- Tests 1000 emails for quick validation
- Good for testing configuration

### 📊 Full Database Validation
```bash
# Validate all emails from database
php scripts/simple_extract.php
# or
composer validate
```
- Validates all emails with status 'valid'
- Saves progress every 5 batches

## 🔧 Configuration

### Database Settings
```php
// config/app.php
'database' => [
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'email_check',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

### Validation Settings
```php
// config/app.php
'settings' => [
    'timeout' => 5,
    'dns_servers' => ['8.8.8.8', '1.1.1.1'],
    'check_mx' => true,        // Check MX records
    'check_a' => true,         // Check A records
    'check_spf' => false,      // Check SPF records
    'check_dmarc' => false,    // Check DMARC records
    'use_advanced_validation' => true,
    'use_strict_rfc' => false,
],
```

## 📊 Output Files

All commands generate timestamped output files:

- `*_valid_emails_*.json` - List of valid emails
- `*_invalid_emails_*.json` - List of invalid emails
- `*_stats_*.json` - Detailed statistics
- `*_progress.json` - Progress tracking (for long operations)

## 🔍 Validation Process

1. **Format Validation** - Basic email format check
2. **Domain Extraction** - Extract domain from email
3. **DNS Validation** - Check MX and A records
4. **Advanced Checks** - Additional format validation
5. **Result Generation** - Create validation report

## 📈 Performance

- **DNS Validation** - Fast and reliable
- **Batch Processing** - 1000 emails per batch
- **Memory Efficient** - Processes large datasets
- **Progress Tracking** - Real-time progress updates

## 🛠️ Development

### Running Tests
```bash
# Quick validation test
composer test

# Test with specific JSON file
composer test-json /path/to/test.json
```

### Code Quality
```bash
# Run PHPStan
vendor/bin/phpstan analyse

# Run Rector
vendor/bin/rector process
```

## 📝 Examples

### Analyze Server Emails
```bash
export DB_HOST="your_database_host"
export DB_PORT="3306"
export DB_DATABASE="your_database_name"
export DB_USERNAME="your_username"
export DB_PASSWORD="your_password"

composer analyze
```

### Test JSON File
```bash
composer test-json /path/to/your/emails.json
```

## 🚨 Important Notes

- **DNS Validation Only** - No SMTP validation (safe and fast)
- **Environment Variables** - Required for database connection
- **Large Datasets** - Use `analyze_server.php` for full analysis
- **Progress Tracking** - Check progress files for long operations

## 📄 License

This project is open source and available under the MIT License.