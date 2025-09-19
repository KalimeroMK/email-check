<?php

namespace App;

class SMTPValidator
{
    private int $timeout;

    private int $maxConnections;

    private string $fromEmail;

    private string $fromName;

    private int $rateLimitDelay;

    private int $maxSmtpChecks;

    private int $smtpCheckCount = 0;

    private ?int $lastSmtpCheck = null;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
        $this->timeout = $this->config['timeout'] ?? 10;
        $this->maxConnections = $this->config['max_connections'] ?? 5;
        $this->fromEmail = $this->config['from_email'] ?? 'test@example.com';
        $this->fromName = $this->config['from_name'] ?? 'Email Validator';
        $this->rateLimitDelay = $this->config['rate_limit_delay'] ?? 2; // seconds between checks
        $this->maxSmtpChecks = $this->config['max_smtp_checks'] ?? 100; // maximum number of SMTP checks
    }

    /**
     * Validates email address via SMTP
     * @return mixed[]
     */
    public function validate(string $email): array
    {
        $result = [
            'email' => $email,
            'is_valid' => false,
            'smtp_valid' => false,
            'smtp_response' => '',
            'error' => null,
            'mx_records' => [],
            'smtp_server' => null,
            'smtp_skipped' => false
        ];

        try {
            // Check if we've reached maximum number of SMTP checks
            if ($this->smtpCheckCount >= $this->maxSmtpChecks) {
                $result['error'] = 'Maximum SMTP checks reached, skipping SMTP validation';
                $result['smtp_skipped'] = true;
                return $result;
            }

            // Rate limiting - wait between checks
            $currentTime = time();
            if ($this->lastSmtpCheck > 0 && ($currentTime - $this->lastSmtpCheck) < $this->rateLimitDelay) {
                $waitTime = $this->rateLimitDelay - ($currentTime - $this->lastSmtpCheck);
                sleep($waitTime);
            }

            // First check the domain
            $atPos = strrchr((string) $email, "@");
            $domain = $atPos ? substr($atPos, 1) : '';
            $mxRecords = $this->getMXRecords($domain);

            if (empty($mxRecords)) {
                $result['error'] = 'No MX records found';
                return $result;
            }

            $result['mx_records'] = $mxRecords;

            // Try with each MX record
            foreach ($mxRecords as $mxRecord) {
                $this->smtpCheckCount++;
                $this->lastSmtpCheck = time();

                $smtpResult = $this->smtpValidation($email, $mxRecord['host']);

                if ($smtpResult['is_valid']) {
                    $result['is_valid'] = true;
                    $result['smtp_valid'] = true;
                    $result['smtp_response'] = $smtpResult['response'];
                    $result['smtp_server'] = $mxRecord['host'];
                    break;
                } else {
                    $result['smtp_response'] = $smtpResult['response'];
                    $result['error'] = $smtpResult['error'];
                }
            }

        } catch (\Exception $exception) {
            $result['error'] = 'SMTP validation error: ' . $exception->getMessage();
        }

        return $result;
    }

    /**
     * Gets MX records for domain
     * @return list<array{host: mixed, priority: mixed}>
     */
    private function getMXRecords(string $domain): array
    {
        $mxRecords = [];

        if (getmxrr($domain, $mxHosts, $mxWeights)) {
            $counter = count($mxHosts);
            for ($i = 0; $i < $counter; $i++) {
                $mxRecords[] = [
                    'host' => $mxHosts[$i],
                    'priority' => $mxWeights[$i]
                ];
            }
            // Sort by priority (lower value = higher priority)
            usort($mxRecords, fn(array $a, array $b): int|float => $a['priority'] - $b['priority']);
        }

        return $mxRecords;
    }

    /**
     * SMTP validation for specific MX server
     * @return mixed[]
     */
    private function smtpValidation(string $email, string $mxHost): array
    {
        $result = [
            'is_valid' => false,
            'response' => '',
            'error' => null
        ];

        try {
            // Open SMTP connection
            $socket = @fsockopen($mxHost, 25, $errno, $errstr, $this->timeout);

            if (!$socket) {
                $result['error'] = sprintf('Cannot connect to %s: %s (%d)', $mxHost, $errstr, $errno);
                return $result;
            }

            // Read welcome message
            $response = $this->readResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                fclose($socket);
                $result['error'] = 'SMTP server rejected connection: ' . $response;
                return $result;
            }

            // EHLO command
            fwrite($socket, "EHLO " . $this->fromName . "\r\n");
            $response = $this->readResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                fclose($socket);
                $result['error'] = 'EHLO failed: ' . $response;
                return $result;
            }

            // MAIL FROM command
            fwrite($socket, "MAIL FROM:<" . $this->fromEmail . ">\r\n");
            $response = $this->readResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                fclose($socket);
                $result['error'] = 'MAIL FROM failed: ' . $response;
                return $result;
            }

            // RCPT TO command (this is the key for validation)
            fwrite($socket, "RCPT TO:<" . $email . ">\r\n");
            $response = $this->readResponse($socket);

            $result['response'] = $response;

            if ($this->isPositiveResponse($response)) {
                $result['is_valid'] = true;
            } else {
                $result['error'] = 'RCPT TO failed: ' . $response;
            }

            // QUIT command
            fwrite($socket, "QUIT\r\n");
            $this->readResponse($socket);

            fclose($socket);

        } catch (\Exception $exception) {
            $result['error'] = 'SMTP validation exception: ' . $exception->getMessage();
        }

        return $result;
    }

    /**
     * Reads response from SMTP server
     */
    private function readResponse(mixed $socket): string
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
     * Checks if response is positive (2xx code)
     */
    private function isPositiveResponse(string $response): bool
    {
        return (bool) preg_match('/^2\d\d/', (string) $response);
    }

    /**
     * Validates multiple emails in parallel
     * @return mixed[]
     */
    /** 
     * @param array<int, string> $emails 
     * @return array<int, array<string, mixed>>
     */
    public function validateBatch(array $emails, ?int $maxConcurrent = null): array
    {
        $maxConcurrent ??= $this->maxConnections;
        $results = [];
        $chunks = array_chunk($emails, max(1, $maxConcurrent));

        foreach ($chunks as $chunk) {
            $chunkResults = [];

            foreach ($chunk as $email) {
                $chunkResults[] = $this->validate($email);
            }

            $results = array_merge($results, $chunkResults);

            // Short pause between groups to not overload server
            usleep(100000); // 0.1 second
        }

        return $results;
    }

    /**
     * Statistics for SMTP validation
     */
    /** 
     * @param array<int, array<string, mixed>> $results 
     * @return array<string, mixed>
     */
    public function getStats(array $results): array
    {
        $total = count($results);
        $valid = 0;
        $invalid = 0;
        $errors = 0;

        foreach ($results as $result) {
            if ($result['smtp_valid']) {
                $valid++;
            } elseif ($result['error']) {
                $errors++;
            } else {
                $invalid++;
            }
        }

        return [
            'total' => $total,
            'valid' => $valid,
            'invalid' => $invalid,
            'errors' => $errors,
            'valid_percentage' => $total > 0 ? round(($valid / $total) * 100, 2) : 0,
            'invalid_percentage' => $total > 0 ? round(($invalid / $total) * 100, 2) : 0,
            'error_percentage' => $total > 0 ? round(($errors / $total) * 100, 2) : 0
        ];
    }
}
