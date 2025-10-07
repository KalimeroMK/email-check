<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Validators\DNSValidator;
use PHPUnit\Framework\TestCase;

class DNSValidatorTest extends TestCase
{
    private DNSValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DNSValidator([
            'check_mx' => true,
            'check_a' => true,
            'check_spf' => false,
            'check_dmarc' => false,
        ]);
    }

    public function testValidateDomainWithValidDomain(): void
    {
        $result = $this->validator->validateDomain('google.com');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('domain', $result);
        $this->assertArrayHasKey('has_mx', $result);
        $this->assertArrayHasKey('has_a', $result);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertEquals('google.com', $result['domain']);
        $this->assertTrue($result['has_mx']);
        $this->assertIsFloat($result['response_time']);
    }

    public function testValidateDomainWithInvalidDomain(): void
    {
        $result = $this->validator->validateDomain('invalid-domain-that-does-not-exist-12345.com');

        $this->assertIsArray($result);
        $this->assertEquals('invalid-domain-that-does-not-exist-12345.com', $result['domain']);
        $this->assertFalse($result['has_mx']);
        $this->assertFalse($result['has_a']);
    }

    public function testCheckMXRecordsWithValidDomain(): void
    {
        $result = $this->validator->checkMXRecords('google.com');
        $this->assertTrue($result);
    }

    public function testCheckMXRecordsWithInvalidDomain(): void
    {
        $result = $this->validator->checkMXRecords('invalid-domain-that-does-not-exist-12345.com');
        $this->assertFalse($result);
    }

    public function testCheckARecordsWithValidDomain(): void
    {
        $result = $this->validator->checkARecords('google.com');
        $this->assertTrue($result);
    }

    public function testCheckARecordsWithInvalidDomain(): void
    {
        $result = $this->validator->checkARecords('invalid-domain-that-does-not-exist-12345.com');
        $this->assertFalse($result);
    }

    public function testCheckSPFRecord(): void
    {
        $result = $this->validator->checkSPFRecord('google.com');
        $this->assertTrue($result);
    }

    public function testCheckDMARCRecord(): void
    {
        $result = $this->validator->checkDMARCRecord('google.com');
        $this->assertTrue($result);
    }

    public function testCacheImplementation(): void
    {
        // First call
        $result1 = $this->validator->checkMXRecords('google.com');
        
        // Second call should use cache
        $result2 = $this->validator->checkMXRecords('google.com');
        
        $this->assertEquals($result1, $result2);
    }

    public function testClearCache(): void
    {
        // Make a request to populate cache
        $this->validator->checkMXRecords('google.com');
        
        // Clear cache
        $this->validator->clearCache();
        
        // This should pass without errors
        $this->assertTrue(true);
    }

    public function testGetCacheStats(): void
    {
        $stats = $this->validator->getCacheStats();
        
        $this->assertIsArray($stats);
        // Check for keys that actually exist in the implementation
        $this->assertArrayHasKey('cached_domains', $stats);
        $this->assertArrayHasKey('cache_keys', $stats);
        $this->assertIsInt($stats['cached_domains']);
        $this->assertIsArray($stats['cache_keys']);
    }

    public function testValidateDomainWithSPFAndDMARCEnabled(): void
    {
        $validator = new DNSValidator([
            'check_mx' => true,
            'check_a' => true,
            'check_spf' => true,
            'check_dmarc' => true,
        ]);

        $result = $validator->validateDomain('google.com');

        $this->assertArrayHasKey('has_spf', $result);
        $this->assertArrayHasKey('has_dmarc', $result);
        $this->assertIsBool($result['has_spf']);
        $this->assertIsBool($result['has_dmarc']);
    }

    public function testValidateDomainWithEmptyString(): void
    {
        $result = $this->validator->validateDomain('');
        
        $this->assertIsArray($result);
        $this->assertEquals('', $result['domain']);
        $this->assertFalse($result['has_mx']);
        $this->assertFalse($result['has_a']);
    }

    public function testValidateDomainWithSpecialCharacters(): void
    {
        $result = $this->validator->validateDomain('test@invalid.domain');
        
        $this->assertIsArray($result);
        $this->assertFalse($result['has_mx']);
        $this->assertFalse($result['has_a']);
    }
}