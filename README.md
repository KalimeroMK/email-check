# Email Check Application

Standalone email validation with DNS checks and modular query system.

## 🚀 Technologies

- **PHP 8.1+**
- **MySQL** - Existing database (optional)
- **JSON** - File data source (optional)
- **Docker** - Local SMTP server
- **Environment Configuration** - Secure configuration via .env
- **Composer** - Dependency management

## 📁 Project Structure

```
email-check/
├── src/
│   ├── EmailValidator.php     # Core validation
│   ├── DNSValidator.php       # DNS checks
│   ├── BatchProcessor.php     # Synchronous processing
│   ├── EmailFetcher.php       # IMAP/POP3 integration
│   ├── DatabaseManager.php    # SQLite database
│   ├── ExistingDatabaseManager.php # MySQL integration
│   ├── ConfigManager.php      # Environment configuration
│   ├── DataManager.php        # Data source management
│   └── QueryManager.php       # Modular query system
├── scripts/
│   ├── validate_emails.php    # Main script (database or JSON)
│   ├── validate_from_json.php # JSON file validation
│   ├── validate_emails_safe.php # Safe DNS + local SMTP validation
│   ├── test_validator.php     # Validator test script
│   ├── show_query_info.php    # Show query information
│   ├── export_emails.php      # Export emails
│   ├── quick_validate.php     # Quick validation
│   ├── extract_emails.php     # Extract emails to JSON files
│   ├── validate_all_emails.php # Validate ALL emails from database
│   └── check_progress.php     # Check validation progress
├── config/
│   └── app.php            # Configuration
├── .env.example           # Environment variables example
├── .env                   # Environment variables (not committed)
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
cp env.example .env
# Edit .env with your database credentials
```

3. **Configure the application:**
   ```bash
   # Copy .env.example to .env
   cp .env.example .env
   
   # Update .env file with your settings
   nano .env
   ```

4. **Configure database (if using database):**
   Update the following values in `.env` file:
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   DATA_SOURCE=database
   ```

5. **Configure JSON file (if using JSON):**
   ```env
   DATA_SOURCE=json
   JSON_FILE_PATH=emails.json
   ```

## 🚀 Usage

### 1. Start local SMTP server (safe)

```bash
# Start Docker container for local SMTP server
docker-compose up -d smtp-server

# Check if it's running
docker ps
```

### 2. Show query information

```bash
# Show current query settings
php scripts/show_query_info.php
```

### 3. Validate emails

#### Main scripts:
```bash
# Show query information
php scripts/show_query_info.php

# Validate emails (configured in .env)
php scripts/validate_emails.php

# JSON file validation
php scripts/validate_from_json.php

# SAFE validation (DNS + local SMTP)
php scripts/validate_emails_safe.php

# Quick validation (small number of emails)
php scripts/quick_validate.php

# Extract emails to JSON files (valid and invalid)
php scripts/extract_emails.php

# Validate ALL emails from database (long process)
php -d memory_limit=2G scripts/validate_all_emails.php

# Check validation progress
php scripts/check_progress.php

# Export emails from database
php scripts/export_emails.php

# Test validator
php scripts/test_validator.php
```

### 4. Usage examples

#### JSON format:
```json
["email1@domain.com", "email2@domain.com", "email3@domain.com"]
```

#### Use example_emails.json for testing:
```bash
# Copy example JSON file
cp example_emails.json my_emails.json

# Validate emails
php scripts/validate_from_json.php
```

#### Create JSON file with emails:
```bash
# Create JSON file with email addresses
echo '["email1@domain.com", "email2@domain.com", "email3@domain.com"]' > my_emails.json

# Or use existing example_emails.json
cp example_emails.json my_emails.json
```

## 📊 Results

Scripts create the following files:

- `valid_emails_YYYY-MM-DD_HH-MM-SS.txt` - List of valid emails
- `invalid_emails_YYYY-MM-DD_HH-MM-SS.txt` - List of invalid emails
- `validation_report_YYYY-MM-DD_HH-MM-SS.json` - Detailed report

### SPF/DMARC reporting

When `check_spf`/`check_dmarc` are enabled the DNS validator records the presence of authentication policies directly inside the `dns_checks` payload and surfaces high level warnings for missing records:

```json
{
    "email": "user@example.com",
    "warnings": [
        "Domain is missing SPF record"
    ],
    "dns_checks": {
        "domain": "example.com",
        "has_mx": true,
        "has_a": true,
        "has_spf": false,
        "has_dmarc": true,
        "warnings": [
            "No SPF record found for domain"
        ]
    }
}
```

The domain still passes validation thanks to MX/A records, but the warnings make it clear that SPF should be configured.

### JSON Email Extraction

The `extract_emails.php` script creates clean JSON files with only email addresses:

- `valid_emails_YYYY-MM-DD_HH-MM-SS.json` - Array of valid email addresses
- `invalid_emails_YYYY-MM-DD_HH-MM-SS.json` - Array of invalid email addresses

Example output:
```json
[
    "user1@example.com",
    "user2@example.com",
    "user3@example.com"
]
```

### Full Database Validation

The `validate_all_emails.php` script processes ALL emails from the database:

- **Processes all 284,000+ emails** in batches of 200
- **Creates progress tracking** with `progress.json`
- **Saves progress every 10 batches** for monitoring
- **Estimated time**: 2-3 hours for complete validation
- **Memory requirement**: 2GB recommended

**Usage:**
```bash
# Run in background (recommended)
nohup php -d memory_limit=2G scripts/validate_all_emails.php > validation.log 2>&1 &

# Check progress
php scripts/check_progress.php

# Monitor logs
tail -f validation.log
```

**Output files:**
- `all_valid_emails_TIMESTAMP.json` - All valid emails
- `all_invalid_emails_TIMESTAMP.json` - All invalid emails
- `final_stats_TIMESTAMP.json` - Complete statistics
- `progress.json` - Current progress (updated every 10 batches)

### JSON Validation
For JSON validation, files are named with the source JSON name:
- `valid_emails_{filename}_{timestamp}.txt`
- `invalid_emails_{filename}_{timestamp}.txt`
- `validation_report_{filename}_{timestamp}.json`

## ⚡ Features

- **Advanced Validation** - Completely independent of external packages
- **RFC Validation** - Strict validation with custom algorithms
- **DNS Validation** - Checks MX and A records and can include SPF/DMARC lookups
- **Safe SMTP Validation** - Local Docker SMTP server (no IP banning)
- **Synchronous Processing** - Stable processing without async issues
- **Progress Tracking** - Real-time progress updates
- **Batch Processing** - Handles large quantities of emails
- **MySQL Integration** - Works with existing database
- **JSON File Support** - Can use JSON file as data source
- **Environment Configuration** - Secure configuration via .env file
- **IP Protection** - No requests to external SMTP servers
- **Package Protection** - Works even if external packages are removed

## 🔧 Configuration

### Environment variables (.env)
```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Data Source Configuration
DATA_SOURCE=database
# Options: 'database' or 'json'

# JSON File Configuration (if DATA_SOURCE=json)
JSON_FILE_PATH=emails.json

# Query Configuration
QUERY_TYPE=valid_emails
# Options: 'valid_emails', 'all_emails', 'invalid_emails', 'custom'

# Custom Query (if QUERY_TYPE=custom)
CUSTOM_QUERY=SELECT status, email FROM check_emails WHERE status = 'valid'

# Valid Emails Query
VALID_EMAILS_QUERY=SELECT status, email FROM check_emails WHERE status = 'valid'

# All Emails Query
ALL_EMAILS_QUERY=SELECT status, email FROM check_emails

# Invalid Emails Query
INVALID_EMAILS_QUERY=SELECT status, email FROM check_emails WHERE status = 'invalid'

# Query with Limit
QUERY_LIMIT=0
# 0 = no limit, >0 = limit results

# Query with Offset
QUERY_OFFSET=0
# Starting position for results

# Email Validation Configuration
VALIDATION_METHOD=advanced
# Options: 'basic', 'advanced', 'safe'

# Local SMTP Configuration
LOCAL_SMTP_HOST=localhost
LOCAL_SMTP_PORT=1025

# Batch Processing Configuration
BATCH_SIZE=50
MAX_CONCURRENT=5

# Output Configuration
SAVE_RESULTS=true
OUTPUT_DIR=results

# Debug Configuration
DEBUG=false
VERBOSE=false
```

### Email Validator settings
```php
'timeout' => 5,
'dns_servers' => ['8.8.8.8', '1.1.1.1'],
'check_mx' => true,
'check_a' => true,
'check_spf' => false, // Enable to capture SPF presence and warnings
'check_dmarc' => false, // Enable to capture DMARC presence and warnings
```

Enable `check_spf` / `check_dmarc` when you want the validator to surface additional authentication metadata. The DNS report
will include `has_spf` / `has_dmarc` flags and the final result will list warnings when the records are missing.

### Batch Processing settings
```php
'batch_size' => 200,
'memory_limit' => '512M',
```

**Note:** Async processing is disabled due to issues with `spatie/async` and Illuminate classes. Scripts now use synchronous processing which is more stable.

## 🛡️ Safe Validation

### Local SMTP Server
- **Docker MailHog** - Local SMTP server for testing
- **Web UI** - http://localhost:8025 to view emails
- **No IP Banning** - No requests to external servers
- **Same SMTP Protocols** - Uses standard SMTP commands

### Validation strategies:
1. **Advanced validation** (safest) - `validate_emails.php`
2. **DNS + Local SMTP** (recommended) - `validate_emails_safe.php`
3. **JSON file** (easy) - `validate_from_json.php`
4. **Real SMTP** (risky) - not recommended for large quantities

**Note:** Async processing is disabled due to issues with `spatie/async` and Illuminate classes. Scripts now use synchronous processing which is more stable.

## 📈 Performance

- **~1000 emails/minute** with DNS checks
- **~500 emails/minute** with local SMTP validation
- **Memory efficient** - processes in batches
- **Progress tracking** - real-time progress updates

## 🛠️ Development

### Add new validation:
1. Open `src/EmailValidator.php`
2. Add new check
3. Update `DNSValidator.php` if needed

## 📝 Examples

### Configure for database:
```bash
# Update .env file
echo "DATA_SOURCE=database" >> .env
echo "DB_HOST=localhost" >> .env
echo "DB_DATABASE=my_database" >> .env
echo "DB_USERNAME=my_user" >> .env
echo "DB_PASSWORD=my_password" >> .env

# Validate emails from database
php scripts/validate_emails.php
```

### Configure for JSON file:
```bash
# Update .env file
echo "DATA_SOURCE=json" >> .env
echo "JSON_FILE_PATH=my_emails.json" >> .env

# Validate emails from JSON file
php scripts/validate_from_json.php
```

### Create JSON file with emails:
```bash
# Create JSON file with email addresses
echo '["email1@domain.com", "email2@domain.com", "email3@domain.com"]' > my_emails.json

# Validate emails
php scripts/validate_from_json.php
```

### 4. Modular Queries

#### Show query information:
```bash
# Show current query settings
php scripts/show_query_info.php
```

#### Change query type:
```bash
# Valid emails (default)
echo "QUERY_TYPE=valid_emails" >> .env

# All emails
echo "QUERY_TYPE=all_emails" >> .env

# Invalid emails
echo "QUERY_TYPE=invalid_emails" >> .env

# Custom query
echo "QUERY_TYPE=custom" >> .env
echo "CUSTOM_QUERY=SELECT status, email FROM check_emails WHERE created_at > '2024-01-01'" >> .env
```

#### Add limit and offset:
```bash
# Limit to 1000 emails
echo "QUERY_LIMIT=1000" >> .env

# Start from 500th email
echo "QUERY_OFFSET=500" >> .env
```

## 🐛 Troubleshooting

### DNS issues:
- Check if DNS servers are accessible
- Update `dns_servers` in configuration

### Memory issues:
- Reduce `batch_size` in configuration
- Increase `memory_limit` in PHP

### Database connection:
- Check MySQL settings in `config/app.php`
- Test connection with `/existing-db/test` endpoint

## 📄 License

MIT License