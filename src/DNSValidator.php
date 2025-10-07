<?php

namespace KalimeroMK\EmailCheck;

use KalimeroMK\EmailCheck\Interfaces\DnsCheckerInterface;

class DNSValidator implements DnsCheckerInterface
{
    /** @var array<string, bool> */
    private array $cache = [];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = [])
    {
    }

    /**
     * Validates domain with DNS checks
     */
    /** @return array<string, mixed> */
    public function validateDomain(string $domain): array
    {
        $result = [
            'domain' => $domain,
            'has_mx' => false,
            'has_a' => false,
            'has_spf' => false,
            'has_dmarc' => false,
            'mx_records' => [],
            'a_records' => [],
            'response_time' => 0,
            'warnings' => [],
            'errors' => []
        ];

        $startTime = microtime(true);

        try {
            // Check for MX records
            if ($this->config['check_mx']) {
                $mxResult = $this->checkMXRecords($domain);
                $result['has_mx'] = $mxResult;
                $result['mx_records'] = [];
                // For now no details for MX records
            }

            // Check for A records (backup)
            if ($this->config['check_a'] && !$result['has_mx']) {
                $aResult = $this->checkARecords($domain);
                $result['has_a'] = $aResult;
                $result['a_records'] = [];
                // For now no details for A records
            }

            if (($this->config['check_spf'] ?? false)) {
                $spfResult = $this->checkSPFRecord($domain);
                $result['has_spf'] = $spfResult;

                if (!$spfResult) {
                    $result['warnings'][] = 'No SPF record found for domain';
                }
            }

            if (($this->config['check_dmarc'] ?? false)) {
                $dmarcResult = $this->checkDMARCRecord($domain);
                $result['has_dmarc'] = $dmarcResult;

                if (!$dmarcResult) {
                    $result['warnings'][] = 'No DMARC record found for domain';
                }
            }

        } catch (\Exception $exception) {
            $result['errors'][] = 'DNS Error: ' . $exception->getMessage();
        }

        $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
        
        return $result;
    }

    /**
     * Checks MX records for domain
     */
    public function checkMXRecords(string $domain): bool
    {
        // Check in cache
        $cacheKey = 'mx_' . $domain;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $hasMx = false;

        try {
            $mxRecords = [];
            $mxWeight = [];
            
            if (getmxrr($domain, $mxRecords, $mxWeight)) {
                $hasMx = true;
            }
        } catch (\Exception $exception) {
            // Ignore errors for now
        }

        // Save to cache
        $this->cache[$cacheKey] = $hasMx;
        return $hasMx;
    }

    /**
     * Checks A records for domain
     */
    public function checkARecords(string $domain): bool
    {
        // Check in cache
        $cacheKey = 'a_' . $domain;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $hasA = false;

        try {
            $ip = gethostbyname($domain);
            if ($ip !== $domain && filter_var($ip, FILTER_VALIDATE_IP)) {
                $hasA = true;
            }
        } catch (\Exception $exception) {
            // Ignore errors for now
        }

        // Save to cache
        $this->cache[$cacheKey] = $hasA;
        return $hasA;
    }

    /**
     * Checks SPF records (optional)
     */
    public function checkSPFRecord(string $domain): bool
    {
        $cacheKey = 'spf_' . $domain;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $hasSpf = false;

        try {
            $txtRecords = dns_get_record($domain, DNS_TXT);
            if ($txtRecords === false) {
                $txtRecords = [];
            }
            foreach ($txtRecords as $record) {
                if (str_starts_with((string) $record['txt'], 'v=spf1')) {
                    $hasSpf = true;
                    break;
                }
            }
        } catch (\Exception $exception) {
            // Ignore errors for now
        }

        $this->cache[$cacheKey] = $hasSpf;
        return $hasSpf;
    }

    /**
     * Checks DMARC records (optional)
     */
    public function checkDMARCRecord(string $domain): bool
    {
        $cacheKey = 'dmarc_' . $domain;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $hasDmarc = false;

        try {
            $dmarcDomain = '_dmarc.' . $domain;
            $txtRecords = dns_get_record($dmarcDomain, DNS_TXT);
            if ($txtRecords === false) {
                $txtRecords = [];
            }
            foreach ($txtRecords as $record) {
                if (str_starts_with((string) $record['txt'], 'v=DMARC1')) {
                    $hasDmarc = true;
                    break;
                }
            }
        } catch (\Exception $exception) {
            // Ignore errors for now
        }

        $this->cache[$cacheKey] = $hasDmarc;
        return $hasDmarc;
    }

    /**
     * Clears the cache
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Returns cache for statistics
     */
    /** @return array<string, mixed> */
    public function getCacheStats(): array
    {
        return [
            'cached_domains' => count($this->cache),
            'cache_keys' => array_keys($this->cache)
        ];
    }
}
