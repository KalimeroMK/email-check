<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Validators\CachedDnsValidator;
use KalimeroMK\EmailCheck\Interfaces\DnsCheckerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class CachedDnsValidatorTest extends TestCase
{
    private CachedDnsValidator $cachedValidator;
    private MockObject|DnsCheckerInterface $mockDnsValidator;
    private MockObject|CacheInterface $mockCache;

    protected function setUp(): void
    {
        $this->mockDnsValidator = $this->createMock(DnsCheckerInterface::class);
        $this->mockCache = $this->createMock(CacheInterface::class);
        
        $this->cachedValidator = new CachedDnsValidator(
            [],
            $this->mockDnsValidator,
            $this->mockCache,
            3600,
            'test'
        );
    }

    public function testValidateDomainFromCache(): void
    {
        $domain = 'example.com';
        $expectedResult = [
            'domain' => $domain,
            'has_mx' => true,
            'has_a' => true,
            'response_time' => 50.5
        ];

        // Mock cache hit
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('domain_validation_' . md5($domain))
            ->willReturn($expectedResult);

        $this->mockDnsValidator->expects($this->never())
            ->method('validateDomain');

        $result = $this->cachedValidator->validateDomain($domain);

        $this->assertTrue($result['from_cache']);
        $this->assertEquals($expectedResult['domain'], $result['domain']);
        $this->assertEquals('test', $result['cache_driver']);
    }

    public function testValidateDomainFromDnsValidator(): void
    {
        $domain = 'example.com';
        $expectedResult = [
            'domain' => $domain,
            'has_mx' => true,
            'has_a' => true,
            'response_time' => 50.5
        ];

        // Mock cache miss
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('domain_validation_' . md5($domain))
            ->willReturn(null);

        // Mock DNS validator call
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with($domain)
            ->willReturn($expectedResult);

        // Mock cache set
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with('domain_validation_' . md5($domain), array_merge($expectedResult, ['from_cache' => false, 'cache_driver' => 'test']));

        $result = $this->cachedValidator->validateDomain($domain);

        $this->assertEquals($domain, $result['domain']);
        $this->assertFalse($result['from_cache']);
        $this->assertEquals('test', $result['cache_driver']);
    }

    public function testValidateDomainCacheException(): void
    {
        $domain = 'example.com';
        $expectedResult = [
            'domain' => $domain,
            'has_mx' => true,
            'response_time' => 50.5
        ];

        // Mock cache exception
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willThrowException(new \Exception('Cache error'));

        // Should fallback to DNS validator
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with($domain)
            ->willReturn($expectedResult);

        $result = $this->cachedValidator->validateDomain($domain);

        $this->assertEquals($domain, $result['domain']);
        $this->assertFalse($result['from_cache']);
    }

    public function testCheckMXRecordsFromCache(): void
    {
        $domain = 'example.com';

        // Mock cache hit
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('mx_' . md5($domain))
            ->willReturn(true);

        $this->mockDnsValidator->expects($this->never())
            ->method('checkMXRecords');

        $result = $this->cachedValidator->checkMXRecords($domain);

        $this->assertTrue($result);
    }

    public function testCheckMXRecordsFromDnsValidator(): void
    {
        $domain = 'example.com';

        // Mock cache miss
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('mx_' . md5($domain))
            ->willReturn(null);

        // Mock DNS validator call
        $this->mockDnsValidator->expects($this->once())
            ->method('checkMXRecords')
            ->with($domain)
            ->willReturn(true);

        // Mock cache set
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with('mx_' . md5($domain), true);

        $result = $this->cachedValidator->checkMXRecords($domain);

        $this->assertTrue($result);
    }

    public function testCheckARecordsFromCache(): void
    {
        $domain = 'example.com';

        // Mock cache hit
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('a_' . md5($domain))
            ->willReturn(false);

        $this->mockDnsValidator->expects($this->never())
            ->method('checkARecords');

        $result = $this->cachedValidator->checkARecords($domain);

        $this->assertFalse($result);
    }

    public function testCheckSPFRecordFromCache(): void
    {
        $domain = 'example.com';

        // Mock cache hit
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('spf_' . md5($domain))
            ->willReturn(true);

        $this->mockDnsValidator->expects($this->never())
            ->method('checkSPFRecord');

        $result = $this->cachedValidator->checkSPFRecord($domain);

        $this->assertTrue($result);
    }

    public function testCheckDMARCRecordFromCache(): void
    {
        $domain = 'example.com';

        // Mock cache hit
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('dmarc_' . md5($domain))
            ->willReturn(false);

        $this->mockDnsValidator->expects($this->never())
            ->method('checkDMARCRecord');

        $result = $this->cachedValidator->checkDMARCRecord($domain);

        $this->assertFalse($result);
    }

    public function testClearCache(): void
    {
        $this->mockDnsValidator->expects($this->once())
            ->method('clearCache');

        $this->cachedValidator->clearCache();
    }

    public function testGetCacheStats(): void
    {
        $expectedStats = [
            'cache_size' => 10,
            'hits' => 5,
            'misses' => 3
        ];

        $this->mockDnsValidator->expects($this->once())
            ->method('getCacheStats')
            ->willReturn($expectedStats);

        $result = $this->cachedValidator->getCacheStats();

        // Check that the result contains expected keys (may have additional keys)
        $this->assertArrayHasKey('cache_size', $result);
        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('misses', $result);
        $this->assertArrayHasKey('cache_driver', $result);
        $this->assertArrayHasKey('cache_ttl', $result);
        $this->assertArrayHasKey('telemetry', $result);
        $this->assertArrayHasKey('hit_rate', $result);
    }

    public function testDomainNormalization(): void
    {
        $domain = 'EXAMPLE.COM';
        $normalizedDomain = 'example.com';

        // Mock cache miss for normalized domain
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with('domain_validation_' . md5($normalizedDomain))
            ->willReturn(null);

        // Mock DNS validator call with normalized domain
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with($normalizedDomain);

        $this->cachedValidator->validateDomain($domain);
    }

    public function testCacheSetException(): void
    {
        $domain = 'example.com';
        $expectedResult = ['domain' => $domain, 'has_mx' => true];

        // Mock cache miss
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        // Mock DNS validator call
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->willReturn($expectedResult);

        // Mock cache set exception
        $this->mockCache->expects($this->once())
            ->method('set')
            ->willThrowException(new \Exception('Cache set error'));

        // Should not throw exception, should continue normally
        $result = $this->cachedValidator->validateDomain($domain);

        $this->assertEquals($domain, $result['domain']);
        $this->assertFalse($result['from_cache']);
    }

    public function testConstructorWithDefaults(): void
    {
        $validator = new CachedDnsValidator();
        
        // Test that it can be instantiated with default parameters
        $this->assertInstanceOf(CachedDnsValidator::class, $validator);
        
        // Test a basic operation
        $result = $validator->validateDomain('google.com');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('domain', $result);
    }

    public function testTelemetryTracking(): void
    {
        $domain = 'example.com';
        $expectedResult = ['domain' => $domain, 'has_mx' => true];

        // First call - cache miss
        $this->mockCache->expects($this->exactly(2))
            ->method('get')
            ->willReturn(null);

        $this->mockDnsValidator->expects($this->exactly(2))
            ->method('validateDomain')
            ->willReturn($expectedResult);

        $this->mockCache->expects($this->exactly(2))
            ->method('set');

        // First call - should be miss
        $this->cachedValidator->validateDomain($domain);
        
        // Second call - should be miss again (since we're mocking cache miss)
        $this->cachedValidator->validateDomain($domain);

        $telemetry = $this->cachedValidator->getTelemetry();
        
        $this->assertArrayHasKey('hits', $telemetry);
        $this->assertArrayHasKey('misses', $telemetry);
        $this->assertArrayHasKey('errors', $telemetry);
        $this->assertArrayHasKey('hit_rate', $telemetry);
        $this->assertArrayHasKey('total_requests', $telemetry);
        
        $this->assertEquals(0, $telemetry['hits']);
        $this->assertEquals(2, $telemetry['misses']);
        $this->assertEquals(0.0, $telemetry['hit_rate']);
    }

    public function testTelemetryReset(): void
    {
        $domain = 'example.com';
        $expectedResult = ['domain' => $domain, 'has_mx' => true];

        // Make some calls to generate telemetry
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->willReturn($expectedResult);

        $this->mockCache->expects($this->once())
            ->method('set');

        $this->cachedValidator->validateDomain($domain);

        // Reset telemetry
        $this->cachedValidator->resetTelemetry();

        $telemetry = $this->cachedValidator->getTelemetry();
        
        $this->assertEquals(0, $telemetry['hits']);
        $this->assertEquals(0, $telemetry['misses']);
        $this->assertEquals(0, $telemetry['errors']);
    }

    public function testCacheDriverConfiguration(): void
    {
        // Test with array driver
        $arrayValidator = new CachedDnsValidator([], null, null, 3600, 'array');
        $this->assertInstanceOf(CachedDnsValidator::class, $arrayValidator);

        // Test with file driver
        $fileValidator = new CachedDnsValidator([], null, null, 3600, 'file');
        $this->assertInstanceOf(CachedDnsValidator::class, $fileValidator);

        // Test with null driver
        $nullValidator = new CachedDnsValidator([], null, null, 3600, 'null');
        $this->assertInstanceOf(CachedDnsValidator::class, $nullValidator);
    }

    public function testCustomTTLConfiguration(): void
    {
        $customTtl = 7200; // 2 hours
        
        $validator = new CachedDnsValidator([], null, null, $customTtl, 'array');
        
        $stats = $validator->getCacheStats();
        $this->assertEquals($customTtl, $stats['cache_ttl']);
    }

    public function testInvalidDriverFallback(): void
    {
        // Test with invalid driver - should fallback to array
        $validator = new CachedDnsValidator([], null, null, 3600, 'invalid_driver');
        
        $this->assertInstanceOf(CachedDnsValidator::class, $validator);
        
        // Should work normally despite invalid driver
        $result = $validator->validateDomain('example.com');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('domain', $result);
    }

    public function testDriverFallbackOnException(): void
    {
        // Test that if Redis fails, it falls back to ArrayAdapter
        // This is hard to test directly, but we can test the constructor works
        $validator = new CachedDnsValidator([], null, null, 3600, 'redis');
        
        $this->assertInstanceOf(CachedDnsValidator::class, $validator);
        
        // Even if Redis is not available, it should fallback gracefully
        $stats = $validator->getCacheStats();
        $this->assertArrayHasKey('cache_driver', $stats);
    }
}