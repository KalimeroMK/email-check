<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Validators\SMTPValidator;
use PHPUnit\Framework\TestCase;

class SMTPValidatorTest extends TestCase
{
    private SMTPValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SMTPValidator([
            'timeout' => 5,
            'max_connections' => 2,
            'max_checks' => 10,
            'rate_limit_delay' => 1,
            'from_email' => 'test@example.com',
            'from_name' => 'Test Validator',
        ]);
    }

    public function testValidateWithValidEmail(): void
    {
        // This test will likely fail in real environment due to SMTP restrictions
        // but it tests the structure and error handling
        $result = $this->validator->validate('test@gmail.com');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertArrayHasKey('smtp_valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('smtp_response', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        $this->assertEquals('test@gmail.com', $result['email']);
        $this->assertIsBool($result['is_valid']);
        $this->assertIsBool($result['smtp_valid']);
        $this->assertIsArray($result['errors']);
        $this->assertIsArray($result['warnings']);
    }

    public function testValidateWithInvalidEmailFormat(): void
    {
        $result = $this->validator->validate('invalid-email');
        
        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['smtp_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateWithEmptyEmail(): void
    {
        $result = $this->validator->validate('');
        
        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['smtp_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateWithNonExistentDomain(): void
    {
        $result = $this->validator->validate('test@nonexistentdomain12345.com');
        
        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['smtp_valid']);
        $this->assertContains('No MX records found for domain', $result['errors']);
    }

    public function testValidateBatchWithMultipleEmails(): void
    {
        $emails = [
            'test@gmail.com',
            'invalid-email',
            'test@yahoo.com',
        ];
        
        $results = $this->validator->validateBatch($emails);
        
        $this->assertCount(3, $results);
        
        foreach ($results as $index => $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('email', $result);
            $this->assertEquals($emails[$index], $result['email']);
        }
    }

    public function testValidateBatchWithRateLimit(): void
    {
        // Create validator with very low limits for testing
        $limitedValidator = new SMTPValidator([
            'timeout' => 1,
            'max_connections' => 1,
            'max_checks' => 2,
            'rate_limit_delay' => 0,
            'from_email' => 'test@example.com',
            'from_name' => 'Test Validator',
        ]);
        
        $emails = [
            'test1@gmail.com',
            'test2@gmail.com',
            'test3@gmail.com', // This should hit rate limit
        ];
        
        $results = $limitedValidator->validateBatch($emails);
        
        $this->assertCount(3, $results);
        
        // The third email should have rate limit error
        $this->assertContains('Rate limit exceeded', $results[2]['errors']);
    }

    public function testGetConfig(): void
    {
        $timeout = $this->validator->getConfig('timeout');
        $this->assertEquals(5, $timeout);
        
        $default = $this->validator->getConfig('nonexistent', 'default');
        $this->assertEquals('default', $default);
    }

    public function testSetConfig(): void
    {
        $this->validator->setConfig('timeout', 15);
        $this->assertEquals(15, $this->validator->getConfig('timeout'));
        
        $this->validator->setConfig('custom_option', 'custom_value');
        $this->assertEquals('custom_value', $this->validator->getConfig('custom_option'));
    }

    public function testConstructorWithEmptyConfig(): void
    {
        $validator = new SMTPValidator();
        
        $this->assertInstanceOf(SMTPValidator::class, $validator);
        $this->assertEquals(10, $validator->getConfig('timeout')); // Default value
    }

    public function testConstructorWithPartialConfig(): void
    {
        $validator = new SMTPValidator([
            'timeout' => 20,
            'from_email' => 'custom@example.com',
        ]);
        
        $this->assertEquals(20, $validator->getConfig('timeout'));
        $this->assertEquals('custom@example.com', $validator->getConfig('from_email'));
        $this->assertEquals(3, $validator->getConfig('max_connections')); // Default value
    }

    public function testValidateWithSpecialCharacters(): void
    {
        $result = $this->validator->validate('test+tag@example.com');
        
        $this->assertIsArray($result);
        $this->assertEquals('test+tag@example.com', $result['email']);
    }

    public function testValidateWithSubdomain(): void
    {
        $result = $this->validator->validate('user@mail.google.com');
        
        $this->assertIsArray($result);
        $this->assertEquals('user@mail.google.com', $result['email']);
    }

    public function testValidateWithLongEmail(): void
    {
        $longEmail = 'verylongusername' . str_repeat('x', 200) . '@example.com';
        $result = $this->validator->validate($longEmail);
        
        $this->assertIsArray($result);
        $this->assertEquals($longEmail, $result['email']);
    }

    public function testValidateWithUnicodeDomain(): void
    {
        // This test checks if the validator handles unicode domains properly
        $result = $this->validator->validate('test@münchen.de');
        
        $this->assertIsArray($result);
        $this->assertEquals('test@münchen.de', $result['email']);
    }

    public function testValidateWithNumbersInDomain(): void
    {
        $result = $this->validator->validate('test@example123.com');
        
        $this->assertIsArray($result);
        $this->assertEquals('test@example123.com', $result['email']);
    }

    public function testValidateWithHyphensInDomain(): void
    {
        $result = $this->validator->validate('test@sub-domain.example.com');
        
        $this->assertIsArray($result);
        $this->assertEquals('test@sub-domain.example.com', $result['email']);
    }
}
