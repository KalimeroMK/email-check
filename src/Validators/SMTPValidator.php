<?php

namespace KalimeroMK\EmailCheck\Validators;

use Exception;
use Socket;

class SMTPValidator
{
    /** @var array<string, mixed> */
    private array $config;

    private readonly int $timeout;

    private readonly int $maxConnections;

    private readonly int $maxChecks;

    private readonly int $rateLimitDelay;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => 10,
            'max_connections' => 3,
            'max_checks' => 50,
            'rate_limit_delay' => 3,
            'local_smtp_host' => 'localhost',
            'local_smtp_port' => 1025,
            'from_email' => 'test@example.com',
            'from_name' => 'Email Validator',
        ], $config);

        $this->timeout = (int)$this->config['timeout'];
        $this->maxConnections = (int)$this->config['max_connections'];
        $this->maxChecks = (int)$this->config['max_checks'];
        $this->rateLimitDelay = (int)$this->config['rate_limit_delay'];
    }

    /**
     * Validates email using SMTP connection
     * 
     * @param string $email Email address to validate
     * @return array<string, mixed> Validation result
     */
    public function validate(string $email): array
    {
        $result = [
            'email' => $email,
            'is_valid' => false,
            'smtp_valid' => false,
            'smtp_status_code' => 'disabled',
            'errors' => [],
            'warnings' => [],
            'smtp_response' => null,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        try {
            // Extract domain from email
            $domain = $this->extractDomain($email);
            if ($domain === '' || $domain === '0') {
                $result['errors'][] = 'Invalid email format';
                $result['smtp_status_code'] = 'invalid_format';
                return $result;
            }

            // Get MX records for domain
            $mxRecords = $this->getMxRecords($domain);
            if ($mxRecords === []) {
                $result['errors'][] = 'No MX records found for domain';
                $result['smtp_status_code'] = 'no_mx_records';
                return $result;
            }

            // Try SMTP validation
            $smtpResult = $this->performSmtpValidation($email, $mxRecords);
            
            $result['smtp_valid'] = $smtpResult['valid'];
            $result['smtp_response'] = $smtpResult['response'];
            $result['smtp_status_code'] = $smtpResult['status_code'] ?? 'unknown';
            
            if ($smtpResult['valid']) {
                $result['is_valid'] = true;
            } else {
                $result['errors'][] = $smtpResult['error'] ?? 'SMTP validation failed';
            }

        } catch (Exception $exception) {
            $result['errors'][] = 'SMTP validation error: ' . $exception->getMessage();
            $result['smtp_status_code'] = 'connection_failure';
        }

        return $result;
    }

    /**
     * Performs SMTP validation against MX servers
     * 
     * @param string $email Email address to validate
     * @param array<string> $mxRecords MX records for the domain
     * @return array<string, mixed> SMTP validation result
     */
    private function performSmtpValidation(string $email, array $mxRecords): array
    {
        $result = [
            'valid' => false,
            'response' => null,
            'error' => null,
            'status_code' => 'unknown',
        ];

        foreach ($mxRecords as $mxRecord) {
            try {
                $smtpResult = $this->connectToSmtpServer($mxRecord, $email);
                
                if ($smtpResult['valid']) {
                    $result['valid'] = true;
                    $result['response'] = $smtpResult['response'];
                    $result['status_code'] = $smtpResult['status_code'] ?? 'success';
                    break;
                }
                
                $result['response'] = $smtpResult['response'];
                $result['error'] = $smtpResult['error'];
                $result['status_code'] = $smtpResult['status_code'] ?? 'server_error';
                
            } catch (Exception $e) {
                $result['error'] = 'Connection failed: ' . $e->getMessage();
                $result['status_code'] = 'connection_failure';
                continue;
            }
        }

        return $result;
    }

    /**
     * Connects to SMTP server and validates email
     * 
     * @param string $mxRecord MX record hostname
     * @param string $email Email address to validate
     * @return array<string, mixed> Connection result
     */
    private function connectToSmtpServer(string $mxRecord, string $email): array
    {
        $result = [
            'valid' => false,
            'response' => null,
            'error' => null,
            'status_code' => 'unknown',
        ];

        $socket = null;
        
        try {
            // Create socket connection
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket === false) {
                throw new Exception('Failed to create socket');
            }

            // Set socket timeout
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

            // Connect to SMTP server
            $connected = socket_connect($socket, $mxRecord, 25);
            if (!$connected) {
                throw new Exception('Failed to connect to SMTP server');
            }

            // Read initial response
            $response = $this->readSocketResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                throw new Exception('SMTP server rejected connection: ' . $response);
            }

            // Send HELO command
            $this->sendCommand($socket, 'HELO ' . $this->config['local_smtp_host']);
            $response = $this->readSocketResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                throw new Exception('HELO command failed: ' . $response);
            }

            // Send MAIL FROM command
            $this->sendCommand($socket, 'MAIL FROM: <' . $this->config['from_email'] . '>');
            $response = $this->readSocketResponse($socket);
            if (!$this->isPositiveResponse($response)) {
                throw new Exception('MAIL FROM command failed: ' . $response);
            }

            // Send RCPT TO command
            $this->sendCommand($socket, 'RCPT TO: <' . $email . '>');
            $response = $this->readSocketResponse($socket);
            
            $result['response'] = $response;
            $result['status_code'] = $this->analyzeSmtpResponse($response);
            
            if ($this->isPositiveResponse($response)) {
                $result['valid'] = true;
            } else {
                $result['error'] = 'RCPT TO command failed: ' . $response;
            }

            // Send QUIT command
            $this->sendCommand($socket, 'QUIT');
            $this->readSocketResponse($socket);

        } catch (Exception $exception) {
            $result['error'] = $exception->getMessage();
            $result['status_code'] = 'connection_failure';
        } finally {
            if ($socket !== null && $socket !== false) {
                socket_close($socket);
            }
        }

        return $result;
    }

    /**
     * Analyzes SMTP response and returns appropriate status code
     * 
     * @param string $response SMTP server response
     * @return string Status code
     */
    private function analyzeSmtpResponse(string $response): string
    {
        $response = trim($response);
        
        // Extract status code (first 3 digits)
        if (preg_match('/^(\d{3})/', $response, $matches)) {
            $statusCode = (int)$matches[1];
            
            switch ($statusCode) {
                case 250:
                    return 'success';
                    
                case 550:
                    // Check for specific 550 error messages
                    if (str_contains($response, 'NoSuchUser') || 
                        str_contains($response, 'does not exist') ||
                        str_contains($response, 'User unknown') ||
                        str_contains($response, 'Invalid recipient')) {
                        return 'mailbox_not_found';
                    }
                    return 'server_error';
                    
                case 551:
                case 552:
                case 553:
                case 554:
                    return 'server_error';
                    
                case 421:
                case 450:
                case 451:
                    return 'server_error';
                    
                case 452:
                case 453:
                    return 'server_error';
                    
                default:
                    if ($statusCode >= 200 && $statusCode < 300) {
                        return 'success';
                    } elseif ($statusCode >= 400 && $statusCode < 500) {
                        return 'server_error';
                    } elseif ($statusCode >= 500 && $statusCode < 600) {
                        return 'server_error';
                    }
                    break;
            }
        }
        
        // Check for catch-all behavior (server accepts any email)
        if (str_contains($response, '250') || str_contains($response, 'OK')) {
            return 'catch_all';
        }
        
        return 'unknown';
    }

    /**
     * Sends command to SMTP server
     *
     * @param Socket $socket Socket connection
     * @param string $command Command to send
     */
    private function sendCommand(Socket $socket, string $command): void
    {
        $command .= "\r\n";
        socket_write($socket, $command, strlen($command));
    }

    /**
     * Reads response from SMTP server
     * 
     * @param Socket $socket Socket connection
     * @return string Server response
     */
    private function readSocketResponse(Socket $socket): string
    {
        $response = '';
        while (($data = socket_read($socket, 1024)) !== false) {
            $response .= $data;
            if (str_ends_with($data, "\r\n")) {
                break;
            }
        }

        return trim($response);
    }

    /**
     * Checks if SMTP response is positive
     * 
     * @param string $response SMTP response
     * @return bool True if response is positive
     */
    private function isPositiveResponse(string $response): bool
    {
        $code = substr($response, 0, 3);
        return in_array($code, ['250', '220', '221'], true);
    }

    /**
     * Gets MX records for domain
     * 
     * @param string $domain Domain name
     * @return array<string> MX records
     */
    private function getMxRecords(string $domain): array
    {
        $mxRecords = [];
        $mxWeights = [];
        
        if (getmxrr($domain, $mxRecords, $mxWeights)) {
            // Sort by priority (weight)
            array_multisort($mxWeights, SORT_ASC, $mxRecords);
            return $mxRecords;
        }
        
        return [];
    }

    /**
     * Extracts domain from email address
     * 
     * @param string $email Email address
     * @return string Domain name
     */
    private function extractDomain(string $email): string
    {
        $atPos = strrchr($email, "@");
        return $atPos ? substr($atPos, 1) : '';
    }

    /**
     * Validates multiple emails using SMTP
     * 
     * @param array<string> $emails Array of email addresses
     * @return array<array<string, mixed>> Array of validation results
     */
    public function validateBatch(array $emails): array
    {
        $results = [];
        $connectionCount = 0;
        $checkCount = 0;

        foreach ($emails as $email) {
            // Rate limiting
            if ($checkCount >= $this->maxChecks) {
                $results[] = [
                    'email' => $email,
                    'is_valid' => false,
                    'smtp_valid' => false,
                    'errors' => ['Rate limit exceeded'],
                    'warnings' => [],
                    'smtp_response' => null,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                continue;
            }

            // Connection limiting
            if ($connectionCount >= $this->maxConnections) {
                sleep($this->rateLimitDelay);
                $connectionCount = 0;
            }

            $results[] = $this->validate($email);
            $connectionCount++;
            $checkCount++;

            // Small delay between checks
            usleep(100000); // 0.1 second
        }

        return $results;
    }

    /**
     * Gets configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Sets configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public function setConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }
}
