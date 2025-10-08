<?php

namespace KalimeroMK\EmailCheck\Validators;

use KalimeroMK\EmailCheck\Interfaces\DnsCheckerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Enhanced DNS validator with persistent caching support and telemetry
 * Wraps the basic DNSValidator with PSR-16 cache for better performance
 */
class CachedDnsValidator implements DnsCheckerInterface
{
    private readonly DnsCheckerInterface $dnsValidator;

    private readonly CacheInterface $cache;

    private readonly int $cacheTtl;

    private readonly string $cacheDriver;

    /** @var array<string, int> */
    private array $telemetry = [
        'hits' => 0,
        'misses' => 0,
        'errors' => 0,
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        array $config = [],
        ?DnsCheckerInterface $dnsValidator = null,
        ?CacheInterface $cache = null,
        ?int $cacheTtl = null,
        ?string $cacheDriver = null
    ) {
        $this->dnsValidator = $dnsValidator ?? new DNSValidator($config);

        // Load configuration from environment or parameters
        $this->cacheTtl = $cacheTtl ?? $this->loadCacheTtlFromEnv();
        $this->cacheDriver = $cacheDriver ?? $this->loadCacheDriverFromEnv();

        // Initialize cache based on driver
        $this->cache = $cache ?? $this->createCacheInstance($config);
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
                $this->telemetry['hits']++;
                $cached['from_cache'] = true;
                $cached['cache_driver'] = $this->cacheDriver;
                return $cached;
            }
        } catch (\Throwable) {
            $this->telemetry['errors']++;
            // Fallback to direct validation if cache fails
        }

        $this->telemetry['misses']++;
        $result = $this->dnsValidator->validateDomain($domain);
        $result['from_cache'] = false;
        $result['cache_driver'] = $this->cacheDriver;

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable) {
            $this->telemetry['errors']++;
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
                $this->telemetry['hits']++;
                return $cached;
            }
        } catch (\Throwable) {
            $this->telemetry['errors']++;
            // Fallback to direct check
        }

        $this->telemetry['misses']++;
        $result = $this->dnsValidator->checkMXRecords($domain);

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable) {
            $this->telemetry['errors']++;
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
                $this->telemetry['hits']++;
                return $cached;
            }
        } catch (\Throwable) {
            $this->telemetry['errors']++;
            // Fallback to direct check
        }

        $this->telemetry['misses']++;
        $result = $this->dnsValidator->checkARecords($domain);

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable) {
            $this->telemetry['errors']++;
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
                $this->telemetry['hits']++;
                return $cached;
            }
        } catch (\Throwable) {
            $this->telemetry['errors']++;
            // Fallback to direct check
        }

        $this->telemetry['misses']++;
        $result = $this->dnsValidator->checkSPFRecord($domain);

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable) {
            $this->telemetry['errors']++;
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
                $this->telemetry['hits']++;
                return $cached;
            }
        } catch (\Throwable) {
            $this->telemetry['errors']++;
            // Fallback to direct check
        }

        $this->telemetry['misses']++;
        $result = $this->dnsValidator->checkDMARCRecord($domain);

        try {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Throwable) {
            $this->telemetry['errors']++;
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
        } catch (\Throwable) {
            $this->telemetry['errors']++;
            // Continue even if cache clear fails
        }
    }

    /**
     * Returns enhanced cache statistics with telemetry
     *
     * @return array<string, mixed>
     */
    public function getCacheStats(): array
    {
        $stats = $this->dnsValidator->getCacheStats();
        $stats['cache_type'] = 'persistent';
        $stats['cache_driver'] = $this->cacheDriver;
        $stats['cache_ttl'] = $this->cacheTtl;

        // Add telemetry data
        $stats['telemetry'] = $this->telemetry;
        $stats['hit_rate'] = $this->calculateHitRate();

        return $stats;
    }

    /**
     * Gets telemetry data for monitoring
     *
     * @return array<string, int|float>
     */
    public function getTelemetry(): array
    {
        return array_merge($this->telemetry, [
            'hit_rate' => $this->calculateHitRate(),
            'total_requests' => $this->telemetry['hits'] + $this->telemetry['misses'],
        ]);
    }

    /**
     * Resets telemetry counters
     */
    public function resetTelemetry(): void
    {
        $this->telemetry = [
            'hits' => 0,
            'misses' => 0,
            'errors' => 0,
        ];
    }

    /**
     * Loads cache TTL from environment variable
     */
    private function loadCacheTtlFromEnv(): int
    {
        $ttl = $_ENV['EMAIL_DNS_CACHE_TTL'] ?? $_SERVER['EMAIL_DNS_CACHE_TTL'] ?? 3600;
        return (int) $ttl;
    }

    /**
     * Loads cache driver from environment variable with fallback
     */
    private function loadCacheDriverFromEnv(): string
    {
        $driver = $_ENV['EMAIL_DNS_CACHE_DRIVER'] ?? $_SERVER['EMAIL_DNS_CACHE_DRIVER'] ?? 'file';
        $driver = strtolower(trim((string) $driver));

        // Validate driver and fallback to array if invalid
        $validDrivers = ['file', 'redis', 'array', 'null'];
        if (!in_array($driver, $validDrivers, true)) {
            return 'array'; // Safe fallback
        }

        return $driver;
    }

    /**
     * Creates cache instance based on driver configuration with fallback
     *
     * @param array<string, mixed> $config
     */
    private function createCacheInstance(array $config): CacheInterface
    {
        try {
            return match ($this->cacheDriver) {
                'redis' => $this->createRedisCache($config),
                'array' => new Psr16Cache(new ArrayAdapter($this->cacheTtl)),
                'null' => new Psr16Cache(new ArrayAdapter(0)),
                default => new Psr16Cache(new FilesystemAdapter('dns_cache', $this->cacheTtl)),
            };
        } catch (\Throwable) {
            // Fallback to ArrayAdapter if any driver fails
            return new Psr16Cache(new ArrayAdapter($this->cacheTtl));
        }
    }

    /**
     * Creates Redis cache instance
     *
     * @param array<string, mixed> $config
     */
    private function createRedisCache(array $config): CacheInterface
    {
        $redisHost = $config['redis_host'] ?? $_ENV['REDIS_HOST'] ?? $_SERVER['REDIS_HOST'] ?? '127.0.0.1';
        $redisPort = $config['redis_port'] ?? $_ENV['REDIS_PORT'] ?? $_SERVER['REDIS_PORT'] ?? 6379;
        $redisPassword = $config['redis_password'] ?? $_ENV['REDIS_PASSWORD'] ?? $_SERVER['REDIS_PASSWORD'] ?? null;

        $dsn = sprintf('redis://%s:%s', $redisHost, $redisPort);
        if ($redisPassword) {
            $dsn = sprintf('redis://:%s@%s:%s', $redisPassword, $redisHost, $redisPort);
        }

        $redis = RedisAdapter::createConnection($dsn);
        $adapter = new RedisAdapter($redis, 'dns_cache', $this->cacheTtl);

        return new Psr16Cache($adapter);
    }

    /**
     * Calculates cache hit rate percentage
     */
    private function calculateHitRate(): float
    {
        $total = $this->telemetry['hits'] + $this->telemetry['misses'];
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->telemetry['hits'] / $total) * 100, 2);
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
