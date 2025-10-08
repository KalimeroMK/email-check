<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\EmailValidator;
use KalimeroMK\EmailCheck\Interfaces\DnsCheckerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmailValidatorEdgeCasesTest extends TestCase
{
    private EmailValidator $validator;
    private MockObject|DnsCheckerInterface $mockDnsValidator;

    protected function setUp(): void
    {
        $this->mockDnsValidator = $this->createMock(DnsCheckerInterface::class);
        $this->validator = new EmailValidator([], $this->mockDnsValidator);
    }

    public function testValidateEmailWithUnicodeCharacters(): void
    {
        $email = 'tëst@example.com';

        // Unicode characters are not supported by PHP's filter_var
        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateEmailWithMultipleAtSymbols(): void
    {
        $email = 'test@@example.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Multiple @ symbols', $result['errors']);
    }

    public function testValidateEmailWithLeadingDot(): void
    {
        $email = '.test@example.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Starts or ends with dot', $result['errors']);
    }

    public function testValidateEmailWithTrailingDot(): void
    {
        $email = 'test.@example.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Starts or ends with dot', $result['errors']);
    }

    public function testValidateEmailWithConsecutiveDots(): void
    {
        $email = 'test..test@example.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Multiple consecutive dots', $result['errors']);
    }

    public function testValidateEmailWithEmptyLocalPart(): void
    {
        $email = '@example.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Starts or ends with @', $result['errors']);
    }

    public function testValidateEmailWithEmptyDomain(): void
    {
        $email = 'test@';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Starts or ends with @', $result['errors']);
    }

    public function testValidateEmailWithOnlyAtSymbol(): void
    {
        $email = '@';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Starts or ends with @', $result['errors']);
    }

    public function testValidateEmailWithSpaces(): void
    {
        $email = 'test @example.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Contains spaces', $result['errors']);
    }

    public function testValidateEmailWithSpecialCharactersInDomain(): void
    {
        $email = 'test@example!.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateEmailWithHyphenAtStartOfDomain(): void
    {
        $email = 'test@-example.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateEmailWithHyphenAtEndOfDomain(): void
    {
        $email = 'test@example-.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateEmailWithMultipleDotsInDomain(): void
    {
        $email = 'test@example..com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Multiple consecutive dots', $result['errors']);
    }

    public function testValidateEmailWithValidSubdomain(): void
    {
        $email = 'test@mail.example.com';

        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with('mail.example.com')
            ->willReturn([
                'domain' => 'mail.example.com',
                'has_mx' => true,
                'has_a' => true,
                'response_time' => 50.0
            ]);

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithVeryLongDomain(): void
    {
        $longDomain = str_repeat('a', 250) . '.com';
        $email = 'test@' . $longDomain;

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Email matches invalid pattern: Domain part too long (over 253 chars)', $result['errors']);
    }

    public function testValidateEmailWithNumbersInLocalPart(): void
    {
        $email = 'test123@example.com';

        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with('example.com')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'has_a' => true,
                'response_time' => 50.0
            ]);

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithUnderscoreInLocalPart(): void
    {
        $email = 'test_user@example.com';

        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with('example.com')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'has_a' => true,
                'response_time' => 50.0
            ]);

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithHyphenInLocalPart(): void
    {
        $email = 'test-user@example.com';

        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with('example.com')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'has_a' => true,
                'response_time' => 50.0
            ]);

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
        $this->assertEquals($email, $result['email']);
    }
}
