<?php

namespace KalimeroMK\EmailCheck;

use KalimeroMK\EmailCheck\Interfaces\DnsCheckerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Enhanced DNS validator with persistent caching support
 * Wraps the basic DNSValidator with PSR-16 cache for better performance
 */
class CachedDnsValidator implements DnsCheckerInterface
{
    private readonly DnsCheckerInterface $dnsValidator;
    private readonly CacheInterface $cache;
    private readonly int $cacheTtl;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config = [],
        ?DnsCheckerInterface $dnsValidator = null,
        ?CacheInterface $cache = null,
        int $cacheTtl = 3600
    ) {
        $this->dnsValidator = $dnsValidator ?? new DNSValidator($config);
        $this->cache = $cache ?? new Psr16Cache(new FilesystemAdapter('dns_cache', $cacheTtl));
        $this->cacheTtl = $cacheTtl;
    }

    /**
     * Validates domain with DNS checks, using cache for performance
     *
     * @param string $domain The domain to validate
     * @return array<string, mixed> Validation result
     */
    public function validateDomain(string $domain): array
    {
        // Normalize domain for consistent caching
        $domain = $this->normalizeDomain($domain);
        $cacheKey = 'domain_validation_' . md5($domain);

        try {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                $cached['from_cache'] = true;
                return $cached;
            }
        } catch (\Throwable $e) {
            // Fallback to direct validation if cache fails
        }

        $result = $this->dnsValidator->validateDomain($domain);
        $result['from_cache'] = false;

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable $e) {
            // Continue even if caching fails
        }

        return $result;
    }

    /**
     * Checks MX records with caching
     */
    public function checkMXRecords(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);
        $cacheKey = 'mx_' . md5($domain);

        try {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Fallback to direct check
        }

        $result = $this->dnsValidator->checkMXRecords($domain);

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable $e) {
            // Continue even if caching fails
        }

        return $result;
    }

    /**
     * Checks A records with caching
     */
    public function checkARecords(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);
        $cacheKey = 'a_' . md5($domain);

        try {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Fallback to direct check
        }

        $result = $this->dnsValidator->checkARecords($domain);

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable $e) {
            // Continue even if caching fails
        }

        return $result;
    }

    /**
     * Checks SPF records with caching
     */
    public function checkSPFRecord(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);
        $cacheKey = 'spf_' . md5($domain);

        try {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Fallback to direct check
        }

        $result = $this->dnsValidator->checkSPFRecord($domain);

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable $e) {
            // Continue even if caching fails
        }

        return $result;
    }

    /**
     * Checks DMARC records with caching
     */
    public function checkDMARCRecord(string $domain): bool
    {
        $domain = $this->normalizeDomain($domain);
        $cacheKey = 'dmarc_' . md5($domain);

        try {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        } catch (\Throwable $e) {
            // Fallback to direct check
        }

        $result = $this->dnsValidator->checkDMARCRecord($domain);

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable $e) {
            // Continue even if caching fails
        }

        return $result;
    }

    /**
     * Clears both internal cache and persistent cache
     */
    public function clearCache(): void
    {
        $this->dnsValidator->clearCache();
        
        try {
            $this->cache->clear();
        } catch (\Throwable $e) {
            // Continue even if cache clear fails
        }
    }

    /**
     * Returns enhanced cache statistics
     *
     * @return array<string, mixed>
     */
    public function getCacheStats(): array
    {
        $stats = $this->dnsValidator->getCacheStats();
        $stats['cache_type'] = 'persistent';
        $stats['cache_ttl'] = $this->cacheTtl;
        
        return $stats;
    }

    /**
     * Normalizes domain name for consistent caching
     * Handles IDN domains properly
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        
        // Convert IDN domains to ASCII for consistent caching
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $domain = $ascii;
            }
        }
        
        return $domain;
    }
}