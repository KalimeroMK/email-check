<?php

namespace KalimeroMK\EmailCheck;

use KalimeroMK\EmailCheck\Interfaces\DnsCheckerInterface;
use KalimeroMK\EmailCheck\Detectors\DisposableEmailDetector;
use KalimeroMK\EmailCheck\Validators\DNSValidator;
use KalimeroMK\EmailCheck\Validators\SMTPValidator;
use KalimeroMK\EmailCheck\Validators\PatternValidator;
use KalimeroMK\EmailCheck\Detectors\DomainSuggestion;
use KalimeroMK\EmailCheck\Data\ConfigManager;
use Exception;
use Throwable;

class EmailValidator
{
    private readonly DnsCheckerInterface $dnsValidator;

    private readonly DisposableEmailDetector $disposableDetector;

    private readonly SMTPValidator $smtpValidator;

    private readonly PatternValidator $patternValidator;

    /** @var array<string, mixed> */
    private array $config;


    /** @param array<string, mixed> $config */
    public function __construct(array $config = [], ?DnsCheckerInterface $dnsValidator = null)
    {
        // Load configuration from .env file if available
        $envConfig = [];
        try {
            $envConfig = $this->loadEnvConfig();
        } catch (Exception) {
            // If .env file doesn't exist or has errors, continue with defaults
        }

        // Merge configurations: defaults < .env < user provided
        $this->config = array_merge([
            'timeout' => 5,
            'dns_servers' => ['8.8.8.8', '1.1.1.1'],
            'check_mx' => true,
            'check_a' => true,
            'check_spf' => false,
            'check_dmarc' => false,
            'use_advanced_validation' => true,
            'use_strict_rfc' => false,
            'check_disposable' => false,
            'disposable_strict' => true,
            'check_smtp' => false,
            'enable_pattern_filtering' => true,
            'pattern_strict_mode' => false,
            'smtp_timeout' => 10,
            'smtp_max_connections' => 3,
            'smtp_max_checks' => 50,
            'smtp_rate_limit_delay' => 3,
            'smtp_from_email' => 'test@example.com',
            'smtp_from_name' => 'Email Validator',
        ], $envConfig, $config);

        $this->dnsValidator = $dnsValidator ?? new DNSValidator($this->config);
        $this->disposableDetector = new DisposableEmailDetector();
        $this->patternValidator = new PatternValidator($this->config);

        // Initialize SMTP validator with SMTP-specific config
        $smtpConfig = [
            'timeout' => $this->config['smtp_timeout'],
            'max_connections' => $this->config['smtp_max_connections'],
            'max_checks' => $this->config['smtp_max_checks'],
            'rate_limit_delay' => $this->config['smtp_rate_limit_delay'],
            'from_email' => $this->config['smtp_from_email'],
            'from_name' => $this->config['smtp_from_name'],
        ];
        $this->smtpValidator = new SMTPValidator($smtpConfig);
    }

    /**
     * Loads configuration from .env file
     *
     * @return array<string, mixed>
     */
    private function loadEnvConfig(): array
    {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            return [];
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            // Ignore comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parse key=value pairs
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if they exist
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                $env[$key] = $value;
            }
        }

        // Convert .env values to config array
        $config = [];

        // SMTP validation configuration
        if (isset($env['CHECK_SMTP'])) {
            $config['check_smtp'] = filter_var($env['CHECK_SMTP'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($env['SMTP_TIMEOUT'])) {
            $config['smtp_timeout'] = (int)$env['SMTP_TIMEOUT'];
        }

        if (isset($env['SMTP_FROM_EMAIL'])) {
            $config['smtp_from_email'] = $env['SMTP_FROM_EMAIL'];
        }

        if (isset($env['SMTP_FROM_NAME'])) {
            $config['smtp_from_name'] = $env['SMTP_FROM_NAME'];
        }

        // Data source configuration
        if (isset($env['DATA_SOURCE'])) {
            $config['data_source'] = $env['DATA_SOURCE'];
        }

        if (isset($env['JSON_FILE_PATH'])) {
            $config['json_file_path'] = $env['JSON_FILE_PATH'];
        }

        // Database configuration
        if (isset($env['DB_HOST'])) {
            $config['database']['host'] = $env['DB_HOST'];
        }

        if (isset($env['DB_PORT'])) {
            $config['database']['port'] = (int)$env['DB_PORT'];
        }

        if (isset($env['DB_DATABASE'])) {
            $config['database']['database'] = $env['DB_DATABASE'];
        }

        if (isset($env['DB_USERNAME'])) {
            $config['database']['username'] = $env['DB_USERNAME'];
        }

        if (isset($env['DB_PASSWORD'])) {
            $config['database']['password'] = $env['DB_PASSWORD'];
        }

        if (isset($env['DB_CHARSET'])) {
            $config['database']['charset'] = $env['DB_CHARSET'];
        }

        if (isset($env['DB_COLLATION'])) {
            $config['database']['collation'] = $env['DB_COLLATION'];
        }

        // Other configuration options
        if (isset($env['TIMEOUT'])) {
            $config['timeout'] = (int)$env['TIMEOUT'];
        }

        if (isset($env['CHECK_DISPOSABLE'])) {
            $config['check_disposable'] = filter_var($env['CHECK_DISPOSABLE'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($env['DISPOSABLE_STRICT'])) {
            $config['disposable_strict'] = filter_var($env['DISPOSABLE_STRICT'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($env['ENABLE_PATTERN_FILTERING'])) {
            $config['enable_pattern_filtering'] = filter_var($env['ENABLE_PATTERN_FILTERING'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($env['PATTERN_STRICT_MODE'])) {
            $config['pattern_strict_mode'] = filter_var($env['PATTERN_STRICT_MODE'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($env['VALIDATION_METHOD'])) {
            $config['validation_method'] = $env['VALIDATION_METHOD'];
        }

        if (isset($env['DEBUG'])) {
            $config['debug'] = filter_var($env['DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($env['VERBOSE'])) {
            $config['verbose'] = filter_var($env['VERBOSE'], FILTER_VALIDATE_BOOLEAN);
        }

        return $config;
    }

    /**
     * Validates an email address with advanced checks
     * @return mixed[]
     */
    public function validate(string $email): array
    {
        $result = $this->createBaseResult($email);

        // 0. Pattern filtering (fast rejection of known invalid patterns)
        if ($this->config['enable_pattern_filtering']) {
            $patternResult = $this->patternValidator->validate($email);
            $result['pattern_valid'] = $patternResult['pattern_valid'];
            $result['pattern_status'] = $patternResult['pattern_status'];
            $result['matched_pattern'] = $patternResult['matched_pattern'];

            if (!$patternResult['pattern_valid']) {
                $result['errors'] = array_merge($result['errors'], $patternResult['errors']);
                $result['smtp_status_code'] = 'invalid_format';
                return $result;
            }

            if (!empty($patternResult['warnings'])) {
                $result['warnings'] = array_merge($result['warnings'], $patternResult['warnings']);
            }
        }

        // 1. Basic format validation
        if (!$this->isValidFormat($email)) {
            $result['errors'][] = 'Invalid email format';
            $result['smtp_status_code'] = 'invalid_format';
            return $result;
        }

        // 1.1. Disposable email check (if enabled)
        if ($this->config['check_disposable']) {
            $isDisposable = $this->disposableDetector->isDisposable($email);
            $result['is_disposable'] = $isDisposable;

            if ($isDisposable) {
                if ($this->config['disposable_strict']) {
                    $result['errors'][] = 'Disposable email address not allowed';
                    return $result;
                }

                $result['warnings'][] = 'Disposable email address detected';
            }
        }

        // 2. Extract domain
        $atPos = strrchr($email, "@");
        $domain = $atPos ? substr($atPos, 1) : '';
        if ($domain === '' || $domain === '0') {
            $result['errors'][] = 'No domain found';
            return $result;
        }

        // 2.1. Handle IDN (Internationalized Domain Names) conversion
        $domain = $this->normalizeDomainForValidation($domain);

        // 3. DNS checks
        $dnsResult = $this->dnsValidator->validateDomain($domain);
        $result['dns_checks'] = $dnsResult;

        // More strict DNS validation - require MX records, not just A records
        $domainValid = ($dnsResult['has_mx'] ?? false);
        $result['domain_valid'] = $domainValid;

        $dnsWarnings = [];

        if (($this->config['check_spf'] ?? false) && !($dnsResult['has_spf'] ?? false)) {
            $dnsWarnings[] = 'Domain is missing SPF record';
        }

        if (($this->config['check_dmarc'] ?? false) && !($dnsResult['has_dmarc'] ?? false)) {
            $dnsWarnings[] = 'Domain is missing DMARC record';
        }

        if ($dnsWarnings !== []) {
            $result['warnings'] = array_values(array_unique(array_merge($result['warnings'], $dnsWarnings)));
        }

        // 4. Advanced checks
        if ($this->config['use_advanced_validation']) {
            $advancedResult = $this->performAdvancedValidation($email);
            $result['advanced_checks'] = $advancedResult;
        }

        // 5. SMTP validation (if enabled)
        if ($this->config['check_smtp']) {
            try {
                $smtpResult = $this->smtpValidator->validate($email);
                $result['smtp_valid'] = $smtpResult['smtp_valid'];
                $result['smtp_response'] = $smtpResult['smtp_response'];
                $result['smtp_status_code'] = $smtpResult['smtp_status_code'];

                if (!$smtpResult['smtp_valid']) {
                    $result['warnings'][] = 'SMTP validation failed: ' . ($smtpResult['error'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $result['smtp_valid'] = false;  // Set to false on error
                $result['smtp_response'] = 'Error: ' . $e->getMessage();
                $result['smtp_status_code'] = 'connection_failure';
                $result['warnings'][] = 'SMTP validation error: ' . $e->getMessage();
            }
        }

        // If SMTP is disabled, smtp_valid remains null and smtp_response remains null

        // 6. Final validation result
        if ($domainValid) {
            $result['is_valid'] = true;
        } else {
            $result['errors'][] = 'Domain has no valid MX or A records';
        }

        if (!$domainValid && !in_array('Domain has no valid MX or A records', $result['errors'], true)) {
            $result['errors'][] = 'Domain has no valid MX or A records';
        }

        if ($result['warnings'] !== []) {
            $result['warnings'] = array_values(array_unique($result['warnings']));
        }

        return $result;
    }

    /**
     * Advanced format validation
     */
    private function isValidFormat(string $email): bool
    {
        // Basic validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Additional checks
        if ($this->config['use_advanced_validation']) {
            return $this->performAdvancedFormatValidation($email);
        }

        return true;
    }

    /**
     * Advanced format validation
     */
    private function performAdvancedFormatValidation(string $email): bool
    {
        // Check length
        if (strlen($email) > 254) {
            return false;
        }

        // Check that it has @ symbol
        if (substr_count($email, '@') !== 1) {
            return false;
        }

        // Split into local and domain parts
        [$local, $domain] = explode('@', $email);

        // Check local part
        if (strlen($local) > 64) {
            return false;
        }

        if ($local === '' || $local === '0') {
            return false;
        }

        // Check domain part
        if (strlen($domain) > 253) {
            return false;
        }

        if ($domain === '' || $domain === '0') {
            return false;
        }

        // Check that domain has TLD
        if (!preg_match('/\.[a-zA-Z]{2,}$/', $domain)) {
            return false;
        }

        // Check for forbidden characters
        if (preg_match('/[<>]/', $email)) {
            return false;
        }

        // Check for consecutive dots
        return !str_contains($email, '..');
    }

    /**
     * Performs advanced checks
     */
    /** @return array<string, mixed> */
    private function performAdvancedValidation(string $email): array
    {
        $advancedResult = [
            'format_validation' => false,
            'length_validation' => false,
            'domain_validation' => false,
            'local_validation' => false,
            'warnings' => [],
            'errors' => []
        ];

        try {
            // Format validation
            $advancedResult['format_validation'] = $this->performAdvancedFormatValidation($email);

            // Length validation
            $advancedResult['length_validation'] = strlen($email) <= 254;

            // Domain validation
            $atPos = strrchr($email, "@");
            $domain = $atPos ? substr($atPos, 1) : '';
            $advancedResult['domain_validation'] = $this->validateDomainFormat($domain);

            // Local part validation
            $atPos = strpos($email, "@");
            $local = $atPos !== false ? substr($email, 0, $atPos) : '';
            $advancedResult['local_validation'] = $this->validateLocalFormat($local);

        } catch (\Exception $exception) {
            $advancedResult['errors'][] = 'Advanced validation error: ' . $exception->getMessage();
        }

        return $advancedResult;
    }

    /**
     * Validates domain format
     */
    private function validateDomainFormat(string $domain): bool
    {
        // Basic checks
        if ($domain === '' || $domain === '0' || strlen($domain) > 253) {
            return false;
        }

        // Check that it has TLD
        if (!preg_match('/\.[a-zA-Z]{2,}$/', $domain)) {
            return false;
        }

        // Check for forbidden characters
        return !preg_match('/[<>]/', $domain);
    }

    /**
     * Validates local part format
     */
    private function validateLocalFormat(string $local): bool
    {
        // Basic checks
        if ($local === '' || $local === '0' || strlen($local) > 64) {
            return false;
        }

        // Check for forbidden characters
        return !preg_match('/[<>]/', $local);
    }

    /**
     * @return array<string, mixed>
     */
    private function createBaseResult(string $email): array
    {
        return [
            'email' => $email,
            'is_valid' => false,
            'domain_valid' => false,
            'is_disposable' => false,
            'pattern_valid' => true,
            'pattern_status' => 'passed',
            'matched_pattern' => null,
            'smtp_valid' => null,  // Will be set to false if SMTP is enabled, null if disabled
            'errors' => [],
            'warnings' => [],
            'dns_checks' => [],
            'advanced_checks' => [],
            'smtp_response' => null,
            'smtp_status_code' => null,
            'timestamp' => date('Y-m-d H:i:s'),
            'validator_type' => 'standalone',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createAsyncErrorResult(string $email, string $message): array
    {
        $result = $this->createBaseResult($email);
        $result['errors'][] = $message;
        $result['dns_checks'] = [
            'domain' => null,
            'has_mx' => false,
            'has_a' => false,
            'has_spf' => false,
            'has_dmarc' => false,
            'mx_records' => [],
            'a_records' => [],
            'response_time' => 0,
            'warnings' => [],
            'errors' => [$message],
        ];
        $result['advanced_checks'] = [
            'format_validation' => false,
            'length_validation' => false,
            'domain_validation' => false,
            'local_validation' => false,
            'warnings' => [],
            'errors' => [$message],
        ];

        return $result;
    }

    /**
     * Validates a list of email addresses
     * @return list
     */
    /**
     * @param array<int, string> $emails
     * @return array<int, array<string, mixed>>
     */
    public function validateBatch(array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $results = [];

        // Use synchronous validation to avoid async issues
        foreach ($emails as $index => $email) {
            try {
                $results[$index] = $this->validate($email);
            } catch (Throwable $throwable) {
                // Record failures as structured results so batch processing can continue gracefully.
                $results[$index] = $this->createAsyncErrorResult(
                    $email,
                    'Validation error: ' . $throwable->getMessage()
                );
            }
        }

        return array_values($results);
    }

    /**
     * Statistics for results
     */
    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>
     */
    public function getStats(array $results): array
    {
        $stats = [
            'total' => count($results),
            'valid' => 0,
            'domain_valid' => 0,
            'invalid' => 0,
            'dns_errors' => 0,
            'smtp_errors' => 0,
            'format_errors' => 0,
            'advanced_errors' => 0,
        ];

        foreach ($results as $result) {
            if (!empty($result['domain_valid'])) {
                $stats['domain_valid']++;
            }

            if (!empty($result['is_valid'])) {
                $stats['valid']++;
                continue;
            }

            $stats['invalid']++;

            $errors = $result['errors'] ?? [];
            if (in_array('Invalid email format', $errors, true)) {
                $stats['format_errors']++;
                continue;
            }

            $smtpChecks = $result['smtp_checks'] ?? [];
            if ($smtpChecks !== [] && (!empty($smtpChecks['smtp_skipped']) || empty($smtpChecks['smtp_valid']))) {
                $stats['smtp_errors']++;
                continue;
            }

            $advancedErrors = $result['advanced_checks']['errors'] ?? [];
            if ($advancedErrors !== []) {
                $stats['advanced_errors']++;
                continue;
            }

            if (empty($result['domain_valid'])) {
                $stats['dns_errors']++;
            }
        }

        $stats['valid_percentage'] = $stats['total'] > 0
            ? round(($stats['valid'] / $stats['total']) * 100, 2)
            : 0;

        $stats['domain_valid_percentage'] = $stats['total'] > 0
            ? round(($stats['domain_valid'] / $stats['total']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * Normalizes domain for validation, handling IDN domains
     */
    private function normalizeDomainForValidation(string $domain): string
    {
        $domain = strtolower(trim($domain));

        // Convert IDN domains to ASCII for DNS validation
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                return $ascii;
            }
        }

        return $domain;
    }

    /**
     * Gets the configured data source
     *
     * @return string 'database' or 'json'
     */
    public function getDataSource(): string
    {
        return $this->config['data_source'] ?? 'database';
    }

    /**
     * Checks if database is configured as data source
     */
    public function useDatabase(): bool
    {
        return $this->getDataSource() === 'database';
    }

    /**
     * Checks if JSON file is configured as data source
     */
    public function useJsonFile(): bool
    {
        return $this->getDataSource() === 'json';
    }

    /**
     * Gets the JSON file path for data source
     */
    public function getJsonFilePath(): string
    {
        return $this->config['json_file_path'] ?? 'emails.json';
    }

    /**
     * Gets database configuration
     *
     * @return array<string, mixed>
     */
    public function getDatabaseConfig(): array
    {
        return $this->config['database'] ?? [];
    }

    /**
     * Gets a configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Gets all configuration
     *
     * @return array<string, mixed>
     */
    public function getAllConfig(): array
    {
        return $this->config;
    }

    /**
     * Gets PatternValidator instance
     */
    public function getPatternValidator(): PatternValidator
    {
        return $this->patternValidator;
    }

    /**
     * Validates multiple emails with pattern filtering
     *
     * @param array<string> $emails Emails to validate
     * @return array<string, mixed> Validation results
     */
    public function validateBulk(array $emails): array
    {
        $results = [];
        $stats = [
            'total' => count($emails),
            'valid' => 0,
            'invalid' => 0,
            'pattern_rejected' => 0,
        ];

        foreach ($emails as $email) {
            $result = $this->validate($email);
            $results[$email] = $result;

            if ($result['is_valid']) {
                $stats['valid']++;
            } else {
                $stats['invalid']++;
                if (isset($result['pattern_status']) && $result['pattern_status'] === 'rejected') {
                    $stats['pattern_rejected']++;
                }
            }
        }

        return [
            'results' => $results,
            'stats' => $stats,
        ];
    }
}
