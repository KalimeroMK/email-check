<?php

namespace App;

use Throwable;

/**
 * Production SMTP Validator
 * 
 * Optimized for validating millions of email addresses with:
 * - Fallback logic (SMTP -> DNS)
 * - Domain caching
 * - Timeout management
 * - Detailed logging
 * - Memory optimization
 */
class ProductionSMTPValidator
{
    private int $timeout;
    private string $fromEmail;
    private string $fromName;
    private int $rateLimitDelay;
    private int $maxSmtpChecks;
    private int $smtpCheckCount = 0;
    private ?int $lastSmtpCheck = null;
    
    /** @var array<string, bool> Domain cache for DNS results */
    private array $domainCache = [];
    
    /** @var array<string, array<string, mixed>> SMTP cache for email results */
    private array $smtpCache = [];
    
    private int $cacheHits = 0;
    private int $cacheMisses = 0;
    private int $dnsFallbacks = 0;
    private int $smtpFailures = 0;
    
    private $logFile = null;
    private bool $enableLogging = true;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
        $this->timeout = $this->config['timeout'] ?? 5; // Reduced timeout for speed
        $this->fromEmail = $this->config['from_email'] ?? 'test@example.com';
        $this->fromName = $this->config['from_name'] ?? 'Email Validator';
        $this->rateLimitDelay = (int)($this->config['rate_limit_delay'] ?? 1); // Reduced delay
        $this->maxSmtpChecks = $this->config['max_smtp_checks'] ?? 1000;
        $this->enableLogging = $this->config['enable_logging'] ?? true;
        
        if ($this->enableLogging) {
            $this->initLogging();
        }
    }

    /**
     * Validates email address via SMTP with fallback to DNS
     * 
     * @param string $email The email address to validate
     * @return bool True if email is valid, false otherwise
     */
    public function validateSmtp(string $email): bool
    {
        $startTime = microtime(true);
        
        try {
            // Basic format validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->log("INVALID_FORMAT", $email, 0, "Invalid email format");
                return false;
            }

            // Extract domain
            $atPos = strrpos($email, '@');
            if ($atPos === false) {
                $this->log("INVALID_FORMAT", $email, 0, "No @ symbol found");
                return false;
            }
            
            $domain = substr($email, $atPos + 1);
            if (empty($domain)) {
                $this->log("INVALID_FORMAT", $email, 0, "Empty domain");
                return false;
            }

            // Check cache first
            $cacheKey = md5($email);
            if (isset($this->smtpCache[$cacheKey])) {
                $this->cacheHits++;
                $this->log("CACHE_HIT", $email, 0, "Result from cache");
                return $this->smtpCache[$cacheKey]['is_valid'];
            }
            
            $this->cacheMisses++;

            // Check domain cache first
            $domainValid = $this->checkDomainCache($domain);
            if (!$domainValid) {
                $this->log("INVALID_DOMAIN", $email, 0, "Domain not found in cache");
                $this->cacheResult($cacheKey, false, "Domain not found");
                return false;
            }

            // Try SMTP validation first
            $smtpResult = $this->attemptSmtpValidation($email, $domain);
            
            if ($smtpResult['success']) {
                $this->log("SMTP_SUCCESS", $email, $smtpResult['time'], "SMTP validation successful");
                $this->cacheResult($cacheKey, true, "SMTP validation");
                return true;
            }

            // Fallback to DNS validation
            $this->dnsFallbacks++;
            $dnsResult = $this->attemptDnsValidation($email, $domain);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->log("DNS_FALLBACK", $email, $duration, "SMTP failed, using DNS: " . $dnsResult['reason']);
            
            $this->cacheResult($cacheKey, $dnsResult['is_valid'], "DNS fallback");
            return $dnsResult['is_valid'];

        } catch (Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->log("ERROR", $email, $duration, "Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks domain cache and validates domain if not cached
     */
    private function checkDomainCache(string $domain): bool
    {
        if (isset($this->domainCache[$domain])) {
            return $this->domainCache[$domain];
        }

        // Validate domain and cache result
        $isValid = $this->validateDomain($domain);
        $this->domainCache[$domain] = $isValid;
        
        // Limit cache size to prevent memory issues
        if (count($this->domainCache) > 10000) {
            $this->domainCache = array_slice($this->domainCache, -5000, null, true);
        }
        
        return $isValid;
    }

    /**
     * Validates domain using DNS
     */
    private function validateDomain(string $domain): bool
    {
        // Check MX records
        if (getmxrr($domain, $mxRecords)) {
            return !empty($mxRecords);
        }

        // Check A records as fallback
        if (function_exists('dns_get_record') && defined('DNS_A')) {
            $records = @dns_get_record($domain, DNS_A);
            return is_array($records) && !empty($records);
        }

        // Check using checkdnsrr
        if (function_exists('checkdnsrr')) {
            return checkdnsrr($domain, 'A');
        }

        return false;
    }

    /**
     * Attempts SMTP validation
     */
    private function attemptSmtpValidation(string $email, string $domain): array
    {
        $startTime = microtime(true);
        
        // Rate limiting
        if ($this->smtpCheckCount >= $this->maxSmtpChecks) {
            return ['success' => false, 'reason' => 'Max SMTP checks reached', 'time' => 0];
        }

        $currentTime = time();
        if ($this->lastSmtpCheck > 0 && ($currentTime - $this->lastSmtpCheck) < $this->rateLimitDelay) {
            $waitTime = $this->rateLimitDelay - ($currentTime - $this->lastSmtpCheck);
            usleep($waitTime * 1000000); // Convert to microseconds
        }

        $this->smtpCheckCount++;
        $this->lastSmtpCheck = time();

        // Get MX records
        $mxRecords = $this->getMXRecords($domain);
        if (empty($mxRecords)) {
            return ['success' => false, 'reason' => 'No MX records', 'time' => 0];
        }

        // Try each MX server with timeout
        foreach ($mxRecords as $mxServer) {
            $result = $this->checkSmtpServerWithTimeout($mxServer, $email);
            if ($result['success']) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                return ['success' => true, 'reason' => 'SMTP validation', 'time' => $duration];
            }
        }

        $this->smtpFailures++;
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        return ['success' => false, 'reason' => 'All SMTP servers failed', 'time' => $duration];
    }

    /**
     * Attempts DNS validation as fallback
     */
    private function attemptDnsValidation(string $email, string $domain): array
    {
        $startTime = microtime(true);
        
        // Check if domain has valid MX or A records
        $hasMx = getmxrr($domain, $mxRecords) && !empty($mxRecords);
        $hasA = false;
        
        if (function_exists('dns_get_record') && defined('DNS_A')) {
            $records = @dns_get_record($domain, DNS_A);
            $hasA = is_array($records) && !empty($records);
        } elseif (function_exists('checkdnsrr')) {
            $hasA = checkdnsrr($domain, 'A');
        }

        $isValid = $hasMx || $hasA;
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        return [
            'is_valid' => $isValid,
            'reason' => $hasMx ? 'MX record found' : ($hasA ? 'A record found' : 'No DNS records'),
            'time' => $duration
        ];
    }

    /**
     * Checks SMTP server with timeout management
     */
    private function checkSmtpServerWithTimeout(string $mxServer, string $email): array
    {
        $socket = null;
        $startTime = microtime(true);
        
        try {
            // Set connection timeout
            $context = stream_context_create([
                'socket' => [
                    'timeout' => $this->timeout
                ]
            ]);
            
            $socket = @stream_socket_client(
                "tcp://{$mxServer}:25",
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if (!$socket) {
                return ['success' => false, 'reason' => "Connection failed: {$errstr}"];
            }

            // Set stream timeout
            stream_set_timeout($socket, $this->timeout);

            // Read welcome message
            $response = $this->readResponseWithTimeout($socket);
            if (!$this->isPositiveResponse($response)) {
                return ['success' => false, 'reason' => "Welcome failed: {$response}"];
            }

            // EHLO command
            fwrite($socket, "EHLO " . $this->fromName . "\r\n");
            $response = $this->readResponseWithTimeout($socket);
            if (!$this->isPositiveResponse($response)) {
                return ['success' => false, 'reason' => "EHLO failed: {$response}"];
            }

            // MAIL FROM command
            fwrite($socket, "MAIL FROM:<" . $this->fromEmail . ">\r\n");
            $response = $this->readResponseWithTimeout($socket);
            if (!$this->isPositiveResponse($response)) {
                return ['success' => false, 'reason' => "MAIL FROM failed: {$response}"];
            }

            // RCPT TO command - this is the key for validation
            fwrite($socket, "RCPT TO:<" . $email . ">\r\n");
            $response = $this->readResponseWithTimeout($socket);
            
            $isValid = $this->isPositiveResponse($response);

            // QUIT command
            fwrite($socket, "QUIT\r\n");
            $this->readResponseWithTimeout($socket);

            return ['success' => $isValid, 'reason' => $isValid ? 'RCPT TO success' : "RCPT TO failed: {$response}"];

        } catch (Throwable $e) {
            return ['success' => false, 'reason' => "Exception: " . $e->getMessage()];
        } finally {
            if ($socket) {
                fclose($socket);
            }
        }
    }

    /**
     * Gets MX records for a domain
     */
    private function getMXRecords(string $domain): array
    {
        $mxRecords = [];
        $mxWeights = [];

        if (getmxrr($domain, $mxRecords, $mxWeights)) {
            array_multisort($mxWeights, SORT_ASC, $mxRecords);
            return $mxRecords;
        }

        return [];
    }

    /**
     * Reads response with timeout management
     */
    private function readResponseWithTimeout($socket): string
    {
        $response = '';
        $startTime = microtime(true);
        $timeout = $this->timeout;

        while ((microtime(true) - $startTime) < $timeout) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                break;
            }

            $response .= $line;

            // If line doesn't end with '-', then it's the last line
            if (!str_ends_with(rtrim($line), '-')) {
                break;
            }
        }

        return trim($response);
    }

    /**
     * Checks if SMTP response is positive
     */
    private function isPositiveResponse(string $response): bool
    {
        return (bool) preg_match('/^2\d\d/', $response);
    }

    /**
     * Caches validation result
     */
    private function cacheResult(string $cacheKey, bool $isValid, string $reason): void
    {
        $this->smtpCache[$cacheKey] = [
            'is_valid' => $isValid,
            'reason' => $reason,
            'timestamp' => time()
        ];

        // Limit cache size to prevent memory issues
        if (count($this->smtpCache) > 50000) {
            // Remove oldest 25% of entries
            $keys = array_keys($this->smtpCache);
            $removeCount = intval(count($keys) * 0.25);
            for ($i = 0; $i < $removeCount; $i++) {
                unset($this->smtpCache[$keys[$i]]);
            }
        }
    }

    /**
     * Initializes logging
     */
    private function initLogging(): void
    {
        $logFile = 'logs/smtp_validation_' . date('Y-m-d_H-i-s') . '.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->logFile = fopen($logFile, 'w');
        if ($this->logFile) {
            fwrite($this->logFile, "timestamp,type,email,duration_ms,message\n");
        }
    }

    /**
     * Logs validation events
     */
    private function log(string $type, string $email, float $duration, string $message): void
    {
        if (!$this->logFile) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logLine = "{$timestamp},{$type},{$email},{$duration},{$message}\n";
        fwrite($this->logFile, $logLine);
    }

    /**
     * Gets performance statistics
     */
    public function getStats(): array
    {
        return [
            'smtp_checks' => $this->smtpCheckCount,
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
            'dns_fallbacks' => $this->dnsFallbacks,
            'smtp_failures' => $this->smtpFailures,
            'cache_hit_rate' => $this->cacheHits + $this->cacheMisses > 0 
                ? round(($this->cacheHits / ($this->cacheHits + $this->cacheMisses)) * 100, 2) 
                : 0,
            'domain_cache_size' => count($this->domainCache),
            'smtp_cache_size' => count($this->smtpCache)
        ];
    }

    /**
     * Clears all caches
     */
    public function clearCaches(): void
    {
        $this->domainCache = [];
        $this->smtpCache = [];
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
    }

    /**
     * Closes logging file
     */
    public function __destruct()
    {
        if ($this->logFile) {
            fclose($this->logFile);
        }
    }
}
