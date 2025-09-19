<?php

namespace App;

use Spatie\Async\Pool;
use Throwable;

class EmailValidator
{
    private readonly DNSValidator $dnsValidator;

    private ?SMTPValidator $smtpValidator = null;

    private ?LocalSMTPValidator $localSmtpValidator = null;

    /** @var array<string, mixed> */
    private array $config;

    private int $asyncConcurrency;

    private int $asyncChunkSize;

    private int $asyncTimeout;

    private int $asyncSleepTime;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => 5,
            'dns_servers' => ['8.8.8.8', '1.1.1.1'],
            'check_mx' => true,
            'check_a' => true,
            'check_spf' => false,
            'check_dmarc' => false,
            'smtp_validation' => false,
            'smtp_timeout' => 10,
            'smtp_max_connections' => 5,
            'smtp_max_checks' => 100,
            'smtp_rate_limit_delay' => 2,
            'from_email' => 'test@example.com',
            'from_name' => 'Email Validator',
            'local_smtp_validation' => false,
            'local_smtp_host' => 'localhost',
            'local_smtp_port' => 1025,
            'use_advanced_validation' => true,
            'use_strict_rfc' => false,
            'max_concurrent' => 10,
            'async_chunk_size' => 100,
            'async_timeout' => 30,
            'async_sleep_time' => 50000,
        ], $config);

        $this->dnsValidator = new DNSValidator($this->config);

        if ($this->config['smtp_validation']) {
            $this->smtpValidator = new SMTPValidator([
                'timeout' => $this->config['smtp_timeout'],
                'max_connections' => $this->config['smtp_max_connections'],
                'max_smtp_checks' => $this->config['smtp_max_checks'],
                'rate_limit_delay' => $this->config['smtp_rate_limit_delay'],
                'from_email' => $this->config['from_email'],
                'from_name' => $this->config['from_name']
            ]);
        }

        if ($this->config['local_smtp_validation']) {
            $this->localSmtpValidator = new LocalSMTPValidator([
                'timeout' => $this->config['smtp_timeout'],
                'max_connections' => $this->config['smtp_max_connections'],
                'smtp_host' => $this->config['local_smtp_host'],
                'smtp_port' => $this->config['local_smtp_port'],
                'from_email' => $this->config['from_email'],
                'from_name' => $this->config['from_name'],
                'check_mx' => $this->config['check_mx'],
                'check_a' => $this->config['check_a'],
            ], $this->dnsValidator);
        }

        $this->asyncConcurrency = max(1, (int)($this->config['max_concurrent'] ?? 10));
        $chunkSize = (int)($this->config['async_chunk_size'] ?? ($this->asyncConcurrency * 5));
        $this->asyncChunkSize = max(1, $chunkSize);
        $timeout = (int)($this->config['async_timeout'] ?? ($this->config['timeout'] ?? 30));
        $this->asyncTimeout = max(1, $timeout);
        $sleepTime = (int)($this->config['async_sleep_time'] ?? 50000);
        $this->asyncSleepTime = max(1, $sleepTime);
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

        // 3. DNS checks
        $dnsResult = $this->dnsValidator->validateDomain($domain);
        $result['dns_checks'] = $dnsResult;

        // 4. Advanced checks
        if ($this->config['use_advanced_validation']) {
            $advancedResult = $this->performAdvancedValidation($email);
            $result['advanced_checks'] = $advancedResult;
        }

        // 5. SMTP validation (if enabled)
        if ($this->config['local_smtp_validation'] && $this->localSmtpValidator) {
            // Use local SMTP validator (safe)
            $smtpResult = $this->localSmtpValidator->validate($email);
            $result['smtp_checks'] = $smtpResult;

            if ($smtpResult['smtp_valid']) {
                $result['is_valid'] = true;
            } else {
                $result['errors'][] = 'Local SMTP validation failed: ' . ($smtpResult['error'] ?? 'Unknown error');
            }
        } elseif ($this->config['smtp_validation'] && $this->smtpValidator) {
            // Use real SMTP validator (risky)
            $smtpResult = $this->smtpValidator->validate($email);
            $result['smtp_checks'] = $smtpResult;

            if ($smtpResult['smtp_valid']) {
                $result['is_valid'] = true;
            } elseif ($smtpResult['smtp_skipped']) {
                // If SMTP is skipped, use DNS result
                if ($dnsResult['has_mx'] || $dnsResult['has_a']) {
                    $result['is_valid'] = true;
                    $result['errors'][] = 'SMTP validation skipped, using DNS result';
                } else {
                    $result['errors'][] = 'SMTP validation skipped and DNS validation failed';
                }
            } elseif ($dnsResult['has_mx'] || $dnsResult['has_a']) {
                // If SMTP validation fails, try with DNS
                $result['is_valid'] = true;
                $result['errors'][] = 'SMTP validation failed, using DNS result';
            } else {
                $result['errors'][] = 'SMTP validation failed: ' . ($smtpResult['error'] ?? 'Unknown error');
            }
        } elseif ($dnsResult['has_mx'] || $dnsResult['has_a']) {
            // DNS validation only
            $result['is_valid'] = true;
        } else {
            $result['errors'][] = 'Domain has no valid MX or A records';
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
            'errors' => [],
            'dns_checks' => [],
            'smtp_checks' => [],
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
            'mx_records' => [],
            'a_records' => [],
            'response_time' => 0,
            'errors' => [$message],
        ];
        $result['smtp_checks'] = [
            'email' => $email,
            'is_valid' => false,
            'smtp_valid' => false,
            'smtp_response' => '',
            'error' => $message,
            'mx_records' => [],
            'smtp_server' => null,
            'smtp_skipped' => true,
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

        foreach (array_chunk($emails, $this->asyncChunkSize, true) as $chunk) {
            // Create a bounded async pool so each chunk is processed concurrently without exhausting memory.
            $pool = Pool::create()
                ->concurrency($this->asyncConcurrency)
                ->timeout($this->asyncTimeout)
                ->sleepTime($this->asyncSleepTime);

            foreach ($chunk as $index => $email) {
                // Dispatch every validation as an async task instead of running a sequential foreach loop.
                $pool->add(function () use ($email): array {
                    return $this->validate($email);
                })->then(function (array $result) use (&$results, $index): void {
                    $results[$index] = $result;
                })->catch(function (Throwable $throwable) use (&$results, $index, $email): void {
                    // Record failures as structured results so batch processing can continue gracefully.
                    $results[$index] = $this->createAsyncErrorResult(
                        $email,
                        'Async validation error: ' . $throwable->getMessage()
                    );
                });
            }

            $pool->wait();
        }

        ksort($results);

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
            'invalid' => 0,
            'dns_errors' => 0,
            'smtp_errors' => 0,
            'format_errors' => 0,
            'advanced_errors' => 0
        ];

        foreach ($results as $result) {
            if ($result['is_valid']) {
                $stats['valid']++;
            } else {
                $stats['invalid']++;

                if (in_array('Invalid email format', $result['errors'])) {
                    $stats['format_errors']++;
                } elseif (str_contains(implode(' ', $result['errors']), 'SMTP validation failed')) {
                    $stats['smtp_errors']++;
                } elseif (str_contains(implode(' ', $result['errors']), 'Advanced validation error')) {
                    $stats['advanced_errors']++;
                } else {
                    $stats['dns_errors']++;
                }
            }
        }

        $stats['valid_percentage'] = $stats['total'] > 0
            ? round(($stats['valid'] / $stats['total']) * 100, 2)
            : 0;

        return $stats;
    }
}

