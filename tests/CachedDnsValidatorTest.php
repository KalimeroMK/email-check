<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\CachedDnsValidator;
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
            $this->mockCache
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
            ->with('domain_validation_' . md5($domain), array_merge($expectedResult, ['from_cache' => false]));

        $result = $this->cachedValidator->validateDomain($domain);

        $this->assertEquals($domain, $result['domain']);
        $this->assertFalse($result['from_cache']);
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
}