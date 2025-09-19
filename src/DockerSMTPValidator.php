<?php

namespace App;

use Throwable;

/**
 * Docker SMTP Validator
 * 
 * Designed to work from within a Docker container and connect to the public internet
 * to verify email addresses using real SMTP servers.
 */
class DockerSMTPValidator
{
    private int $timeout;
    private string $fromEmail;
    private string $fromName;
    private int $rateLimitDelay;
    private int $maxSmtpChecks;
    private int $smtpCheckCount = 0;
    private ?int $lastSmtpCheck = null;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
        $this->timeout = $this->config['timeout'] ?? 10;
        $this->fromEmail = $this->config['from_email'] ?? 'test@example.com';
        $this->fromName = $this->config['from_name'] ?? 'Email Validator';
        $this->rateLimitDelay = $this->config['rate_limit_delay'] ?? 2;
        $this->maxSmtpChecks = $this->config['max_smtp_checks'] ?? 100;
    }

    /**
     * Validates email address via SMTP
     * 
     * @param string $email The email address to validate
     * @return bool True if email is valid, false otherwise
     */
    public function validateSmtp(string $email): bool
    {
        try {
            // Basic format validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            // Extract domain
            $atPos = strrpos($email, '@');
            if ($atPos === false) {
                return false;
            }
            
            $domain = substr($email, $atPos + 1);
            if (empty($domain)) {
                return false;
            }

            // Perform DNS lookup for MX records
            $mxRecords = $this->getMXRecords($domain);
            if (empty($mxRecords)) {
                return false;
            }

            // Try to establish SMTP connection to each MX server
            foreach ($mxRecords as $mxRecord) {
                if ($this->checkSmtpServer($mxRecord, $email)) {
                    return true;
                }
            }

            return false;

        } catch (Throwable $e) {
            // Log error if needed
            error_log("SMTP validation error for {$email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets MX records for a domain
     * 
     * @param string $domain The domain to check
     * @return array<int, string> Array of MX records
     */
    private function getMXRecords(string $domain): array
    {
        $mxRecords = [];
        $mxWeights = [];

        if (getmxrr($domain, $mxRecords, $mxWeights)) {
            // Sort by priority (lower number = higher priority)
            array_multisort($mxWeights, SORT_ASC, $mxRecords);
            return $mxRecords;
        }

        return [];
    }

    /**
     * Checks SMTP server for email validity
     * 
     * @param string $mxServer The MX server to check
     * @param string $email The email address to validate
     * @return bool True if email is valid on this server
     */
    private function checkSmtpServer(string $mxServer, string $email): bool
    {
        // Rate limiting
        if ($this->smtpCheckCount >= $this->maxSmtpChecks) {
            return false;
        }

        $currentTime = time();
        if ($this->lastSmtpCheck > 0 && ($currentTime - $this->lastSmtpCheck) < $this->rateLimitDelay) {
            $waitTime = $this->rateLimitDelay - ($currentTime - $this->lastSmtpCheck);
            sleep($waitTime);
        }

        $this->smtpCheckCount++;
        $this->lastSmtpCheck = time();

        $socket = null;
        
        try {
            // Connect to SMTP server
            $socket = @fsockopen($mxServer, 25, $errno, $errstr, $this->timeout);
            
            if (!$socket) {
                return false;
            }

            // Read welcome message
            $response = $this->readResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                fclose($socket);
                return false;
            }

            // EHLO command
            fwrite($socket, "EHLO " . $this->fromName . "\r\n");
            $response = $this->readResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                fclose($socket);
                return false;
            }

            // MAIL FROM command
            fwrite($socket, "MAIL FROM:<" . $this->fromEmail . ">\r\n");
            $response = $this->readResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                fclose($socket);
                return false;
            }

            // RCPT TO command - this is the key for validation
            fwrite($socket, "RCPT TO:<" . $email . ">\r\n");
            $response = $this->readResponse($socket);
            
            $isValid = $this->isPositiveResponse($response);

            // QUIT command
            fwrite($socket, "QUIT\r\n");
            $this->readResponse($socket);

            fclose($socket);
            
            return $isValid;

        } catch (Throwable $e) {
            if ($socket) {
                fclose($socket);
            }
            return false;
        }
    }

    /**
     * Reads response from SMTP server
     * 
     * @param resource $socket The socket connection
     * @return string The response from server
     */
    private function readResponse($socket): string
    {
        $response = '';
        $timeout = time() + $this->timeout;

        while (time() < $timeout) {
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
     * Checks if SMTP response is positive (2xx code)
     * 
     * @param string $response The response to check
     * @return bool True if response is positive
     */
    private function isPositiveResponse(string $response): bool
    {
        return (bool) preg_match('/^2\d\d/', $response);
    }

    /**
     * Gets current SMTP check count
     * 
     * @return int Number of SMTP checks performed
     */
    public function getSmtpCheckCount(): int
    {
        return $this->smtpCheckCount;
    }

    /**
     * Resets SMTP check count
     */
    public function resetSmtpCheckCount(): void
    {
        $this->smtpCheckCount = 0;
        $this->lastSmtpCheck = null;
    }
}