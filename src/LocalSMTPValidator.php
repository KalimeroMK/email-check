<?php

namespace App;

use Spatie\Async\Pool;
use Throwable;

class LocalSMTPValidator
{
    private string $smtpHost;

    private int $smtpPort;

    private int $timeout;

    private int $maxConnections;

    private string $fromEmail;

    private string $fromName;

    private int $asyncConcurrency;

    private int $asyncChunkSize;

    private int $asyncSleepTime;

    private int $asyncTimeout;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
        $this->smtpHost = $this->config['smtp_host'] ?? 'localhost';
        $this->smtpPort = $this->config['smtp_port'] ?? 1025;
        $this->timeout = $this->config['timeout'] ?? 10;
        $this->maxConnections = $this->config['max_connections'] ?? 5;
        $this->fromEmail = $this->config['from_email'] ?? 'test@example.com';
        $this->fromName = $this->config['from_name'] ?? 'Email Validator';
        $this->asyncConcurrency = max(1, (int)($this->config['max_connections'] ?? 5));
        $chunkSize = (int)($this->config['async_chunk_size'] ?? ($this->asyncConcurrency * 2));
        $this->asyncChunkSize = max(1, $chunkSize);
        $sleepTime = (int)($this->config['async_sleep_time'] ?? 50000);
        $this->asyncSleepTime = max(1, $sleepTime);
        $timeout = (int)($this->config['async_timeout'] ?? $this->timeout);
        $this->asyncTimeout = max(1, $timeout);
    }

    /**
     * Validates email address via local SMTP server
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
            'smtp_server' => $this->smtpHost . ':' . $this->smtpPort,
            'local_validation' => true
        ];

        try {
            // Basic format validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['error'] = 'Invalid email format';
                return $result;
            }

            // Check the domain
            $atPos = strrchr((string) $email, "@");
            $domain = $atPos ? substr($atPos, 1) : '';
            if ($domain === '' || $domain === '0') {
                $result['error'] = 'No domain found';
                return $result;
            }

            // Simulate SMTP validation with local server
            $smtpResult = $this->simulateSMTPValidation($email, $domain);

            if ($smtpResult['is_valid']) {
                $result['is_valid'] = true;
                $result['smtp_valid'] = true;
                $result['smtp_response'] = $smtpResult['response'];
            } else {
                $result['error'] = $smtpResult['error'];
                $result['smtp_response'] = $smtpResult['response'];
            }

        } catch (\Exception $exception) {
            $result['error'] = 'Local SMTP validation error: ' . $exception->getMessage();
        }

        return $result;
    }

    /**
     * Simulates SMTP validation with local server
     * @return mixed[]
     */
    private function simulateSMTPValidation(string $email, string $domain): array
    {
        $result = [
            'is_valid' => false,
            'response' => '',
            'error' => null
        ];

        try {
            // Open connection to local SMTP server
            $socket = @fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, $this->timeout);

            if (!$socket) {
                $result['error'] = sprintf('Cannot connect to local SMTP server: %s (%d)', $errstr, $errno);
                return $result;
            }

            // Read welcome message
            $response = $this->readResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                fclose($socket);
                $result['error'] = 'Local SMTP server rejected connection: ' . $response;
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

            // RCPT TO command - this is the key for validation
            fwrite($socket, "RCPT TO:<" . $email . ">\r\n");
            $response = $this->readResponse($socket);

            $result['response'] = $response;

            // For local server, simulate validation based on domain and email address
            if ($this->isPositiveResponse($response)) {
                // If server accepts, check if domain is valid
                if ($this->isValidDomain($domain)) {
                    // Additionally check if email address exists (simulation)
                    if ($this->emailExists($email)) {
                        $result['is_valid'] = true;
                    } else {
                        $result['error'] = 'Email address does not exist: ' . $email;
                    }
                } else {
                    $result['error'] = 'Domain validation failed for: ' . $domain;
                }
            } else {
                $result['error'] = 'RCPT TO failed: ' . $response;
            }

            // QUIT command
            fwrite($socket, "QUIT\r\n");
            $this->readResponse($socket);

            fclose($socket);

        } catch (\Exception $exception) {
            $result['error'] = 'Local SMTP validation exception: ' . $exception->getMessage();
        }

        return $result;
    }

    /**
     * Checks if domain is valid (simulation)
     */
    private function isValidDomain(string $domain): bool
    {
        // For unknown domains, check MX records
        return getmxrr($domain, $mxHosts);
    }

    /**
     * Simulates check if email address exists
     */
    private function emailExists(string $email): bool
    {
        // Simulate check based on email address
        // This is simulation - in reality it should be checked with real SMTP servers

        // Extract username from email address
        $atPos = strpos($email, '@');
        $username = $atPos !== false ? substr($email, 0, $atPos) : '';

        // Simulate that certain usernames don't exist
        $nonExistentPatterns = [
            'nonexistent', 'fake', 'invalid', 'dummy', 'testuser', 'fakeuser', 
            'invaliduser', 'testmail', 'dummyuser', 'fakeemail'
        ];

        // Check if username contains non-existent patterns
        foreach ($nonExistentPatterns as $pattern) {
            if (stripos($username, $pattern) !== false) {
                return false; // Email address doesn't exist
            }
        }

        // Simulate that some combinations don't exist
        $nonExistentCombinations = [
            '123@gmail.com', '456@yahoo.com', '789@outlook.com', '999@hotmail.com',
            '111@live.com', '222@aol.com', '333@icloud.com', '444@protonmail.com',
            '555@zoho.com', '666@yandex.com'
        ];
        // For the rest, simulate that they exist
        return !in_array($email, $nonExistentCombinations);
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
        if ($emails === []) {
            return [];
        }

        $results = [];
        $concurrency = max(1, $maxConcurrent ?? $this->asyncConcurrency);
        $chunkSize = max(1, (int)($this->asyncChunkSize * ($concurrency / max(1, $this->asyncConcurrency))));

        foreach (array_chunk($emails, $chunkSize, true) as $chunk) {
            // Local validations are executed concurrently via the async pool instead of sequential loops.
            $pool = Pool::create()
                ->concurrency($concurrency)
                ->timeout($this->asyncTimeout)
                ->sleepTime($this->asyncSleepTime);

            foreach ($chunk as $index => $email) {
                // Dispatch every email to the pool so local SMTP checks scale with available processes.
                $pool->add(function () use ($email): array {
                    return $this->validate($email);
                })->then(function (array $result) use (&$results, $index): void {
                    $results[$index] = $result;
                })->catch(function (Throwable $throwable) use (&$results, $index, $email): void {
                    $results[$index] = $this->createAsyncErrorResult(
                        $email,
                        'Async local SMTP validation error: ' . $throwable->getMessage()
                    );
                });
            }

            $pool->wait();
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * Statistics for local SMTP validation
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

    /**
     * @return array<string, mixed>
     */
    private function createAsyncErrorResult(string $email, string $message): array
    {
        return [
            'email' => $email,
            'is_valid' => false,
            'smtp_valid' => false,
            'smtp_response' => '',
            'error' => $message,
            'smtp_server' => $this->smtpHost . ':' . $this->smtpPort,
            'local_validation' => true,
        ];
    }
}
