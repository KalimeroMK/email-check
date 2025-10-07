<?php

namespace KalimeroMK\EmailCheck;

use KalimeroMK\EmailCheck\Interfaces\DnsCheckerInterface;
use Throwable;

class EmailValidator
{
    private readonly DnsCheckerInterface $dnsValidator;


    /** @var array<string, mixed> */
    private array $config;


    /** @param array<string, mixed> $config */
    public function __construct(array $config = [], ?DnsCheckerInterface $dnsValidator = null)
    {
        $this->config = array_merge([
            'timeout' => 5,
            'dns_servers' => ['8.8.8.8', '1.1.1.1'],
            'check_mx' => true,
            'check_a' => true,
            'check_spf' => false,
            'check_dmarc' => false,
            'use_advanced_validation' => true,
            'use_strict_rfc' => false,
        ], $config);

        $this->dnsValidator = $dnsValidator ?? new DNSValidator($this->config);
    }

    /**
     * Validates an email address with advanced checks
     * @return mixed[]
     */
    public function validate(string $email): array
    {
        $result = $this->createBaseResult($email);

        // 1. Basic format validation
        if (!$this->isValidFormat($email)) {
            $result['errors'][] = 'Invalid email format';
            return $result;
        }

        // 2. Extract domain
        $atPos = strrchr((string) $email, "@");
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

        // 5. Final validation result
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
        if (strlen((string) $email) > 254) {
            return false;
        }

        // Check that it has @ symbol
        if (substr_count((string) $email, '@') !== 1) {
            return false;
        }

        // Split into local and domain parts
        [$local, $domain] = explode('@', (string) $email);

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
        if (preg_match('/[<>]/', (string) $email)) {
            return false;
        }
        // Check for consecutive dots
        return !str_contains((string) $email, '..');
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
            $advancedResult['length_validation'] = strlen((string) $email) <= 254;

            // Domain validation
            $atPos = strrchr((string) $email, "@");
            $domain = $atPos ? substr($atPos, 1) : '';
            $advancedResult['domain_validation'] = $this->validateDomainFormat($domain);

            // Local part validation
            $atPos = strpos((string) $email, "@");
            $local = $atPos !== false ? substr((string) $email, 0, $atPos) : '';
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
            'errors' => [],
            'warnings' => [],
            'dns_checks' => [],
            'advanced_checks' => [],
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
}

