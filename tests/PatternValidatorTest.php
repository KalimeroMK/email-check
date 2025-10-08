<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Validators\PatternValidator;
use PHPUnit\Framework\TestCase;

class PatternValidatorTest extends TestCase
{
    private PatternValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PatternValidator([
            'enable_pattern_filtering' => true,
            'pattern_strict_mode' => false,
        ]);
    }

    public function testPatternValidatorRejectsEmailsWithoutAtSymbol(): void
    {
        $emails = [
            'invalid-email',
            'test.example.com',
            'user',
            '123456',
        ];

        foreach ($emails as $email) {
            $result = $this->validator->validate($email);
            $this->assertFalse($result['pattern_valid'], "Email '$email' should be rejected");
            $this->assertEquals('rejected', $result['pattern_status']);
            $this->assertStringContainsString('Missing @ symbol', $result['errors'][0]);
        }
    }

    public function testPatternValidatorRejectsEmailsWithMultipleAtSymbols(): void
    {
        $emails = [
            'user@@example.com',
            'test@@@domain.com',
            '@@example.com',
            'user@domain@example.com',
        ];

        foreach ($emails as $email) {
            $result = $this->validator->validate($email);
            $this->assertFalse($result['pattern_valid'], "Email '$email' should be rejected");
            $this->assertEquals('rejected', $result['pattern_status']);
            $this->assertStringContainsString('Multiple @ symbols', $result['errors'][0]);
        }
    }

    public function testPatternValidatorRejectsEmailsWithMultipleDots(): void
    {
        $emails = [
            'user..name@example.com',
            'test...domain@example.com',
            'user@domain..com',
            'user@domain...com',
        ];

        foreach ($emails as $email) {
            $result = $this->validator->validate($email);
            $this->assertFalse($result['pattern_valid'], "Email '$email' should be rejected");
            $this->assertEquals('rejected', $result['pattern_status']);
            $this->assertStringContainsString('Multiple consecutive dots', $result['errors'][0]);
        }
    }

    public function testPatternValidatorRejectsEmailsStartingOrEndingWithDots(): void
    {
        $emails = [
            '.user@example.com',
            'user.@example.com',
            'user@.example.com',
            'user@example.com.',
        ];

        foreach ($emails as $email) {
            $result = $this->validator->validate($email);
            $this->assertFalse($result['pattern_valid'], "Email '$email' should be rejected");
            $this->assertEquals('rejected', $result['pattern_status']);
            $this->assertStringContainsString('Starts or ends with dot', $result['errors'][0]);
        }
    }

    public function testPatternValidatorRejectsEmailsWithSpaces(): void
    {
        $emails = [
            'user name@example.com',
            'user@example .com',
            'user @example.com',
            'user@ example.com',
        ];

        foreach ($emails as $email) {
            $result = $this->validator->validate($email);
            $this->assertFalse($result['pattern_valid'], "Email '$email' should be rejected");
            $this->assertEquals('rejected', $result['pattern_status']);
            $this->assertStringContainsString('Contains spaces', $result['errors'][0]);
        }
    }

    public function testPatternValidatorRejectsEmailsWithInvalidCharacters(): void
    {
        $emails = [
            'user<name@example.com',
            'user>name@example.com',
            'user"name@example.com',
            'user[name@example.com',
            'user]name@example.com',
            'user\\name@example.com',
        ];

        foreach ($emails as $email) {
            $result = $this->validator->validate($email);
            $this->assertFalse($result['pattern_valid'], "Email '$email' should be rejected");
            $this->assertEquals('rejected', $result['pattern_status']);
            $this->assertStringContainsString('Contains invalid characters', $result['errors'][0]);
        }
    }

    public function testPatternValidatorRejectsEmailsWithEmptyParts(): void
    {
        $emails = [
            '@example.com',
            'user@',
            '@',
        ];

        foreach ($emails as $email) {
            $result = $this->validator->validate($email);
            $this->assertFalse($result['pattern_valid'], "Email '$email' should be rejected");
            $this->assertEquals('rejected', $result['pattern_status']);
        }
    }

    public function testPatternValidatorRejectsEmailsThatAreTooLong(): void
    {
        // Create email longer than 254 characters
        $longLocal = str_repeat('a', 65); // 65 chars local part
        $longDomain = str_repeat('a', 200); // 200 chars domain
        $tooLongEmail = $longLocal . '@' . $longDomain . '.com';

        $result = $this->validator->validate($tooLongEmail);
        $this->assertFalse($result['pattern_valid'], "Email should be rejected for being too long");
        $this->assertEquals('rejected', $result['pattern_status']);
    }

    public function testPatternValidatorAcceptsValidEmails(): void
    {
        $emails = [
            'user@example.com',
            'test.email@domain.co.uk',
            'user+tag@example.org',
            'user_name@example-domain.com',
            '123@example.com',
            'a@b.co',
        ];

        foreach ($emails as $email) {
            $result = $this->validator->validate($email);
            $this->assertTrue($result['pattern_valid'], "Email '$email' should be accepted");
            $this->assertEquals('passed', $result['pattern_status']);
            $this->assertEmpty($result['errors']);
        }
    }

    public function testPatternValidatorStrictMode(): void
    {
        $strictValidator = new PatternValidator([
            'enable_pattern_filtering' => true,
            'pattern_strict_mode' => true,
        ]);

        // Test email that might trigger strict warnings
        $result = $strictValidator->validate('user@domain.com');
        $this->assertTrue($result['pattern_valid']);
        $this->assertEquals('passed', $result['pattern_status']);
    }

    public function testPatternValidatorDisabled(): void
    {
        $disabledValidator = new PatternValidator([
            'enable_pattern_filtering' => false,
        ]);

        $result = $disabledValidator->validate('invalid-email');
        $this->assertTrue($result['pattern_valid']);
        $this->assertEquals('passed', $result['pattern_status']);
        $this->assertEmpty($result['errors']);
    }

    public function testPatternValidatorBulkValidation(): void
    {
        $emails = [
            'valid@example.com',
            'invalid-email',
            'user@domain.com',
            'test@@example.com',
        ];

        $result = $this->validator->validateBulk($emails);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('stats', $result);

        $stats = $result['stats'];
        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['passed']); // valid@example.com, user@domain.com
        $this->assertEquals(2, $stats['rejected']); // invalid-email, test@@example.com
    }

    public function testPatternValidatorConfiguration(): void
    {
        $config = $this->validator->getConfig();
        
        $this->assertIsArray($config);
        $this->assertTrue($config['enable_pattern_filtering']);
        $this->assertFalse($config['pattern_strict_mode']);
        $this->assertGreaterThan(0, $config['invalid_patterns_count']);
        $this->assertGreaterThan(0, $config['strict_patterns_count']);
    }

    public function testPatternValidatorCustomPattern(): void
    {
        $this->validator->addCustomPattern('/^test/', 'Starts with test');
        
        $result = $this->validator->validate('test@example.com');
        $this->assertFalse($result['pattern_valid']);
        $this->assertEquals('rejected', $result['pattern_status']);
        $this->assertStringContainsString('Starts with test', $result['errors'][0]);
    }

    public function testPatternValidatorIsEnabled(): void
    {
        $this->assertTrue($this->validator->isEnabled());
        
        $disabledValidator = new PatternValidator(['enable_pattern_filtering' => false]);
        $this->assertFalse($disabledValidator->isEnabled());
    }

    public function testPatternValidatorIsStrictMode(): void
    {
        $this->assertFalse($this->validator->isStrictMode());
        
        $strictValidator = new PatternValidator(['pattern_strict_mode' => true]);
        $this->assertTrue($strictValidator->isStrictMode());
    }

    public function testPatternValidatorEdgeCases(): void
    {
        $edgeCases = [
            'a@b.c', // Minimal valid email
            'user@sub.domain.com', // Subdomain
            'user+tag@example.com', // Plus sign
            'user_name@example.com', // Underscore
            'user-name@example.com', // Hyphen
            'user.name@example.com', // Dot
        ];

        foreach ($edgeCases as $email) {
            $result = $this->validator->validate($email);
            $this->assertTrue($result['pattern_valid'], "Edge case '$email' should be valid");
            $this->assertEquals('passed', $result['pattern_status']);
        }
    }
}
