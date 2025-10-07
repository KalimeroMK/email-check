<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\DomainSuggestion;
use PHPUnit\Framework\TestCase;

class DomainSuggestionTest extends TestCase
{
    private DomainSuggestion $suggestion;

    protected function setUp(): void
    {
        $this->suggestion = new DomainSuggestion();
    }

    public function testSuggestCorrectionWithValidDomain(): void
    {
        $domain = 'gmail.com';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        $this->assertNull($result); // Valid domain should not have suggestions
    }

    public function testSuggestCorrectionWithTypo(): void
    {
        $domain = 'gmal.com';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        $this->assertIsString($result);
        $this->assertEquals('test@gmail.com', $result);
    }

    public function testSuggestCorrectionWithCommonTypos(): void
    {
        $testCases = [
            'yahooo.com' => 'test@yahoo.com',
            'hotmial.com' => 'test@hotmail.com',
            'outlok.com' => 'test@outlook.com',
            'gmial.com' => 'test@gmail.com',
            'yaho.com' => 'test@yahoo.com',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->suggestion->suggestDomainCorrection('test@' . $input);
            $this->assertEquals($expected, $result, "Failed for input: $input");
        }
    }

    public function testSuggestCorrectionWithUnknownDomain(): void
    {
        $domain = 'unknown-domain-12345.com';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        $this->assertNull($result);
    }

    public function testSuggestCorrectionWithEmptyDomain(): void
    {
        $domain = '';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        $this->assertNull($result);
    }

    public function testSuggestCorrectionWithSubdomain(): void
    {
        $domain = 'mail.gmal.com';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        // DomainSuggestion doesn't support subdomain corrections
        $this->assertNull($result);
    }

    public function testSuggestCorrectionWithInvalidFormat(): void
    {
        $domain = 'invalid-domain-format';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        $this->assertNull($result);
    }

    public function testSuggestCorrectionCaseInsensitive(): void
    {
        $domain = 'GMAL.COM';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        // DomainSuggestion is case sensitive, so uppercase domains don't match
        $this->assertNull($result);
    }

    public function testSuggestCorrectionWithNumbers(): void
    {
        $domain = 'test123.com';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        $this->assertNull($result); // No suggestions for domains with numbers
    }

    public function testSuggestCorrectionWithHyphens(): void
    {
        $domain = 'test-domain.com';
        $result = $this->suggestion->suggestDomainCorrection('test@' . $domain);
        
        $this->assertNull($result); // No suggestions for domains with hyphens
    }
}
