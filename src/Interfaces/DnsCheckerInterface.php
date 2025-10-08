<?php

namespace KalimeroMK\EmailCheck\Interfaces;

/**
 * Interface for DNS checking functionality
 * Allows for mocking during testing and easy implementation swapping
 */
interface DnsCheckerInterface
{
    /**
     * Validates domain with DNS checks
     *
     * @param string $domain The domain to validate
     * @return array<string, mixed> Validation result containing DNS check information
     */
    public function validateDomain(string $domain): array;

    /**
     * Checks if domain has MX records
     *
     * @param string $domain The domain to check
     * @return bool True if MX records exist
     */
    public function checkMXRecords(string $domain): bool;

    /**
     * Checks if domain has A records
     *
     * @param string $domain The domain to check
     * @return bool True if A records exist
     */
    public function checkARecords(string $domain): bool;

    /**
     * Checks if domain has SPF record
     *
     * @param string $domain The domain to check
     * @return bool True if SPF record exists
     */
    public function checkSPFRecord(string $domain): bool;

    /**
     * Checks if domain has DMARC record
     *
     * @param string $domain The domain to check
     * @return bool True if DMARC record exists
     */
    public function checkDMARCRecord(string $domain): bool;

    /**
     * Clears the cache
     */
    public function clearCache(): void;

    /**
     * Returns cache statistics
     *
     * @return array<string, mixed> Cache statistics
     */
    public function getCacheStats(): array;
}
