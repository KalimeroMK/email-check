<?php

namespace KalimeroMK\EmailCheck\Tests;

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testSuggestDomainCorrectionWithValidEmail(): void
    {
        $email = 'test@gmail.com';
        $suggestion = suggestDomainCorrection($email);

        $this->assertNull($suggestion); // Valid email should not have suggestions
    }

    public function testSuggestDomainCorrectionWithTypo(): void
    {
        $email = 'test@gmal.com';
        $suggestion = suggestDomainCorrection($email);

        $this->assertIsString($suggestion);
        $this->assertEquals('test@gmail.com', $suggestion);
    }

    public function testSuggestDomainCorrectionWithInvalidFormat(): void
    {
        $email = 'invalid-email';
        $suggestion = suggestDomainCorrection($email);

        $this->assertNull($suggestion);
    }

    public function testSuggestDomainCorrectionWithEmptyEmail(): void
    {
        $email = '';
        $suggestion = suggestDomainCorrection($email);

        $this->assertNull($suggestion);
    }

    public function testSuggestDomainCorrectionWithCommonTypos(): void
    {
        $testCases = [
            'test@yahooo.com' => 'test@yahoo.com',
            'test@hotmial.com' => 'test@hotmail.com',
            'test@outlok.com' => 'test@outlook.com',
            'test@gmial.com' => 'test@gmail.com',
        ];

        foreach ($testCases as $input => $expected) {
            $suggestion = suggestDomainCorrection($input);
            $this->assertEquals($expected, $suggestion, "Failed for input: $input");
        }
    }

    public function testSuggestDomainCorrectionWithUnknownDomain(): void
    {
        $email = 'test@unknown-domain-12345.com';
        $suggestion = suggestDomainCorrection($email);

        $this->assertNull($suggestion);
    }

    public function testSuggestDomainCorrectionWithSubdomain(): void
    {
        $email = 'test@mail.gmal.com';
        $suggestion = suggestDomainCorrection($email);

        // DomainSuggestion doesn't support subdomain corrections
        $this->assertNull($suggestion);
    }
}
