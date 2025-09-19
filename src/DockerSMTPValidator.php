<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Lightweight SMTP validator that can run inside a Docker container.
 */
class DockerSMTPValidator
{
    private const DEFAULT_SMTP_PORT = 25;

    /**
     * @param string      $heloDomain      Name used for EHLO/HELO command when opening an SMTP session
     * @param string      $fromEmail       Mailbox used for the MAIL FROM command
     * @param int         $connectTimeout  Timeout in seconds for establishing the TCP connection
     * @param int         $readTimeout     Timeout in seconds when waiting for responses from the SMTP server
     * @param int         $smtpPort        Port used for the SMTP conversation (default 25)
     * @param string|null $sourceIp        Optional IP address of the Docker container interface to bind to
     */
    public function __construct(
        private string $heloDomain = 'localhost',
        private string $fromEmail = 'validator@example.com',
        private int $connectTimeout = 10,
        private int $readTimeout = 10,
        private int $smtpPort = self::DEFAULT_SMTP_PORT,
        private ?string $sourceIp = null,
    ) {
    }

    /**
     * Attempt to verify a mailbox by connecting directly to its SMTP server.
     */
    public function validateSmtp(string $email): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        [$localPart, $domain] = explode('@', $email, 2);
        if ($domain === '') {
            return false;
        }

        $mxRecords = $this->lookupMxRecords($domain);
        if ($mxRecords === []) {
            // RFC 5321 states that we can fall back to the domain itself when no MX exists.
            $mxRecords = [[
                'host' => $domain,
                'priority' => PHP_INT_MAX,
            ]];
        }

        foreach ($mxRecords as $record) {
            $smtpHost = $record['host'];

            try {
                $socket = $this->openSmtpSocket($smtpHost);
            } catch (RuntimeException) {
                continue; // Try the next MX host.
            }

            try {
                $greeting = $this->readResponse($socket);
                if (!$this->isPositiveResponse($greeting)) {
                    throw new RuntimeException('SMTP server rejected the connection: ' . $greeting);
                }

                $isValid = $this->performValidationDialogue($socket, $email);
            } catch (RuntimeException) {
                fclose($socket);
                continue;
            }

            fclose($socket);

            if ($isValid) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{host: string, priority: int}>
     */
    private function lookupMxRecords(string $domain): array
    {
        $records = dns_get_record($domain, DNS_MX) ?: [];

        $mxRecords = [];
        foreach ($records as $record) {
            if (!isset($record['target'])) {
                continue;
            }

            $target = rtrim($record['target'], '.');
            if ($target === '') {
                continue;
            }

            $mxRecords[] = [
                'host' => $target,
                'priority' => isset($record['pri']) ? (int) $record['pri'] : PHP_INT_MAX,
            ];
        }

        usort(
            $mxRecords,
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']
        );

        return $mxRecords;
    }

    /**
     * @return resource
     */
    private function openSmtpSocket(string $host)
    {
        $context = stream_context_create([
            'socket' => [
                // Binding to 0.0.0.0 ensures the container can use whichever outbound IP Docker assigns.
                'bindto' => ($this->sourceIp ?? '0.0.0.0') . ':0',
            ],
        ]);

        $remote = sprintf('tcp://%s:%d', $host, $this->smtpPort);

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->connectTimeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException(
                sprintf('Unable to connect to SMTP host %s: %s (%d)', $remote, $errstr ?: 'unknown error', $errno)
            );
        }

        stream_set_timeout($socket, $this->readTimeout);

        return $socket;
    }

    private function performValidationDialogue($socket, string $email): bool
    {
        $this->sendCommand($socket, sprintf("EHLO %s\r\n", $this->heloDomain));
        $response = $this->readResponse($socket);
        if (!$this->isPositiveResponse($response)) {
            // Some servers expect HELO when EHLO fails.
            $this->sendCommand($socket, sprintf("HELO %s\r\n", $this->heloDomain));
            $response = $this->readResponse($socket);

            if (!$this->isPositiveResponse($response)) {
                throw new RuntimeException('EHLO/HELO rejected: ' . $response);
            }
        }

        $this->sendCommand($socket, sprintf("MAIL FROM:<%s>\r\n", $this->fromEmail));
        $response = $this->readResponse($socket);
        if (!$this->isPositiveResponse($response)) {
            throw new RuntimeException('MAIL FROM rejected: ' . $response);
        }

        $this->sendCommand($socket, sprintf("RCPT TO:<%s>\r\n", $email));
        $response = $this->readResponse($socket);
        $isDeliverable = $this->isPositiveResponse($response);

        // Always close the conversation politely.
        $this->sendCommand($socket, "QUIT\r\n");
        $this->readResponse($socket);

        return $isDeliverable;
    }

    private function sendCommand($socket, string $command): void
    {
        $bytes = @fwrite($socket, $command);
        if ($bytes === false || $bytes === 0) {
            throw new RuntimeException('Failed to write to SMTP socket.');
        }
    }

    private function readResponse($socket): string
    {
        $response = '';

        while (true) {
            $line = fgets($socket);
            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                if (!empty($meta['timed_out'])) {
                    throw new RuntimeException('SMTP read timed out.');
                }

                break;
            }

            $response .= $line;

            // Multi-line replies contain a hyphen after the status code.
            if (!preg_match('/^\d{3}-/', $line)) {
                break;
            }
        }

        return trim($response);
    }

    private function isPositiveResponse(string $response): bool
    {
        return (bool) preg_match('/^2\d{2}/', $response);
    }
}
