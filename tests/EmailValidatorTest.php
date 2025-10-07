<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\EmailValidator;
use KalimeroMK\EmailCheck\Interfaces\DnsCheckerInterface;
use KalimeroMK\EmailCheck\Validators\DNSValidator;
use KalimeroMK\EmailCheck\Detectors\DisposableEmailDetector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmailValidatorTest extends TestCase
{
    private EmailValidator $validator;
    private MockObject|DnsCheckerInterface $mockDnsValidator;

    protected function setUp(): void
    {
        $this->mockDnsValidator = $this->createMock(DnsCheckerInterface::class);
        $this->validator = new EmailValidator([], $this->mockDnsValidator);
    }

    public function testValidateValidEmail(): void
    {
        $email = 'test@example.com';
        
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with('example.com')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'has_a' => true,
                'has_spf' => false,
                'has_dmarc' => false,
                'response_time' => 50.0
            ]);

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
        $this->assertTrue($result['domain_valid']);
        $this->assertEquals($email, $result['email']);
        $this->assertArrayHasKey('dns_checks', $result);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateInvalidEmailFormat(): void
    {
        $email = 'invalid-email';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithInvalidDomain(): void
    {
        $email = 'test@invalid-domain.com';
        
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with('invalid-domain.com')
            ->willReturn([
                'domain' => 'invalid-domain.com',
                'has_mx' => false,
                'has_a' => false,
                'has_spf' => false,
                'has_dmarc' => false,
                'response_time' => 30.0
            ]);

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['domain_valid']);
        $this->assertContains('Domain has no valid MX or A records', $result['errors']);
    }

    public function testValidateEmailWithMissingDomain(): void
    {
        $email = 'test@';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateEmailWithSPFAndDMARCWarnings(): void
    {
        $email = 'test@example.com';
        $validator = new EmailValidator([
            'check_spf' => true,
            'check_dmarc' => true
        ], $this->mockDnsValidator);
        
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with('example.com')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'has_a' => true,
                'has_spf' => false,
                'has_dmarc' => false,
                'response_time' => 50.0
            ]);

        $result = $validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
        $this->assertContains('Domain is missing SPF record', $result['warnings']);
        $this->assertContains('Domain is missing DMARC record', $result['warnings']);
    }

    public function testValidateEmailWithAdvancedValidation(): void
    {
        $email = 'test@example.com';
        $validator = new EmailValidator([
            'use_advanced_validation' => true
        ], $this->mockDnsValidator);
        
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'has_a' => true,
                'response_time' => 50.0
            ]);

        $result = $validator->validate($email);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('advanced_checks', $result);
        $this->assertTrue($result['is_valid']);
    }

    public function testValidateEmailTooLong(): void
    {
        // Create an email longer than 254 characters
        $longLocalPart = str_repeat('a', 250);
        $email = $longLocalPart . '@example.com';

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateEmailWithIDNDomain(): void
    {
        $email = 'test@example.com';  // Using regular domain for testing
        
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
    }

    public function testValidateBatchEmails(): void
    {
        $emails = [
            'valid@example.com',
            'invalid-email',
            'test@invalid-domain.com'
        ];

        $this->mockDnsValidator->expects($this->exactly(2))
            ->method('validateDomain')
            ->willReturnOnConsecutiveCalls(
                [
                    'domain' => 'example.com',
                    'has_mx' => true,
                    'has_a' => true,
                    'response_time' => 50.0
                ],
                [
                    'domain' => 'invalid-domain.com',
                    'has_mx' => false,
                    'has_a' => false,
                    'response_time' => 30.0
                ]
            );

        $result = $this->validator->validateBatch($emails);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertTrue($result[0]['is_valid']);
        $this->assertFalse($result[1]['is_valid']);
        $this->assertFalse($result[2]['is_valid']);
    }


    public function testValidateWithStrictRFC(): void
    {
        $validator = new EmailValidator([
            'use_strict_rfc' => true
        ], $this->mockDnsValidator);

        $email = 'test@example.com';
        
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'response_time' => 50.0
            ]);

        $result = $validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
    }

    public function testValidateWithCustomDnsServers(): void
    {
        $validator = new EmailValidator([
            'dns_servers' => ['1.1.1.1', '8.8.8.8'],
            'timeout' => 10
        ], $this->mockDnsValidator);

        $email = 'test@example.com';
        
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'response_time' => 50.0
            ]);

        $result = $validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
    }

    public function testValidateEmailWithDotInLocalPart(): void
    {
        $email = 'test.email@example.com';
        
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'response_time' => 50.0
            ]);

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
    }

    public function testValidateEmailWithPlusInLocalPart(): void
    {
        $email = 'test+tag@example.com';
        
        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->willReturn([
                'domain' => 'example.com',
                'has_mx' => true,
                'response_time' => 50.0
            ]);

        $result = $this->validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_valid']);
    }

    public function testConstructorWithDefaults(): void
    {
        $validator = new EmailValidator();
        
        $this->assertInstanceOf(EmailValidator::class, $validator);
        
        // Test with a real domain
        $result = $validator->validate('test@google.com');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testValidateEmailWithDisposableEmailStrictMode(): void
    {
        $email = 'test@10minutemail.com';
        $validator = new EmailValidator([
            'check_disposable' => true,
            'disposable_strict' => true
        ], $this->mockDnsValidator);

        $result = $validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_valid']);
        $this->assertTrue($result['is_disposable']);
        $this->assertContains('Disposable email address not allowed', $result['errors']);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithDisposableEmailWarningMode(): void
    {
        $email = 'test@guerrillamail.com';
        $validator = new EmailValidator([
            'check_disposable' => true,
            'disposable_strict' => false
        ], $this->mockDnsValidator);

        $result = $validator->validate($email);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_disposable']);
        $this->assertContains('Disposable email address detected', $result['warnings']);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithDisposableDetectionDisabled(): void
    {
        $email = 'test@mailinator.com';
        $validator = new EmailValidator([
            'check_disposable' => false
        ], $this->mockDnsValidator);

        $this->mockDnsValidator->expects($this->once())
            ->method('validateDomain')
            ->with('mailinator.com')
            ->willReturn([
                'domain' => 'mailinator.com',
                'has_mx' => true,
                'has_a' => true,
                'response_time' => 50.0
            ]);

        $result = $validator->validate($email);

        $this->assertIsArray($result);
        $this->assertFalse($result['is_disposable']);
        $this->assertTrue($result['is_valid']);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithSmtpValidationEnabled(): void
    {
        $email = 'test@gmail.com';
        
        $validator = new EmailValidator([
            'check_smtp' => true,
            'smtp_timeout' => 5,
            'smtp_from_email' => 'test@example.com',
            'smtp_from_name' => 'Test Validator',
        ]);
        
        $result = $validator->validate($email);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_valid', $result);
        $this->assertArrayHasKey('smtp_response', $result);
        $this->assertIsBool($result['smtp_valid']);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithSmtpValidationDisabled(): void
    {
        $email = 'test@gmail.com';
        
        $validator = new EmailValidator([
            'check_smtp' => false,
        ]);
        
        $result = $validator->validate($email);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_valid', $result);
        $this->assertArrayHasKey('smtp_response', $result);
        $this->assertNull($result['smtp_valid']);  // Should be null when disabled
        $this->assertNull($result['smtp_response']);  // Should be null when disabled
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithSmtpConfiguration(): void
    {
        $email = 'test@example.com';
        
        $validator = new EmailValidator([
            'check_smtp' => true,
            'smtp_timeout' => 10,
            'smtp_max_connections' => 5,
            'smtp_max_checks' => 100,
            'smtp_rate_limit_delay' => 2,
            'smtp_from_email' => 'validator@test.com',
            'smtp_from_name' => 'Email Validator Test',
        ]);
        
        $result = $validator->validate($email);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_valid', $result);
        $this->assertArrayHasKey('smtp_response', $result);
        $this->assertEquals($email, $result['email']);
    }

    public function testValidateEmailWithDefaultConfiguration(): void
    {
        $email = 'test@gmail.com';
        
        // Test with default configuration (no SMTP options specified)
        // Explicitly disable SMTP to ensure it's null
        $validator = new EmailValidator([
            'check_smtp' => false,
        ]);
        
        $result = $validator->validate($email);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_valid', $result);
        $this->assertArrayHasKey('smtp_response', $result);
        $this->assertNull($result['smtp_valid']);  // Should be null when explicitly disabled
        $this->assertNull($result['smtp_response']);  // Should be null when explicitly disabled
        $this->assertEquals($email, $result['email']);
    }
}