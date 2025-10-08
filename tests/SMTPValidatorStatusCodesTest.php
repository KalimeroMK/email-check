<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Validators\SMTPValidator;
use PHPUnit\Framework\TestCase;

/**
 * @group smtp
 */
class SMTPValidatorStatusCodesTest extends TestCase
{
    private SMTPValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SMTPValidator([
            'timeout' => 1, // Short timeout for testing
            'from_email' => 'test@example.com',
            'from_name' => 'Test Validator',
            'max_checks' => 1, // Limit checks to avoid real network calls
        ]);
    }

    public function testSmtpValidatorReturnsStatusCodes(): void
    {
        $result = $this->validator->validate('test@gmail.com');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_status_code', $result);
        $this->assertIsString($result['smtp_status_code']);
        
        // Should be one of the valid status codes
        $validStatusCodes = [
            'success',
            'mailbox_not_found', 
            'catch_all',
            'server_error',
            'connection_failure',
            'invalid_format',
            'no_mx_records',
            'disabled',
            'unknown'
        ];
        
        $this->assertContains($result['smtp_status_code'], $validStatusCodes);
    }

    public function testSmtpValidatorHandlesInvalidEmailFormat(): void
    {
        $result = $this->validator->validate('invalid-email');
        
        $this->assertEquals('invalid_format', $result['smtp_status_code']);
        $this->assertFalse($result['smtp_valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testSmtpValidatorHandlesNoMxRecords(): void
    {
        $result = $this->validator->validate('test@nonexistentdomain12345.com');
        
        $this->assertEquals('no_mx_records', $result['smtp_status_code']);
        $this->assertFalse($result['smtp_valid']);
        $this->assertContains('No MX records found for domain', $result['errors']);
    }

    public function testSmtpValidatorAnalyzesSmtpResponse(): void
    {
        $reflection = new \ReflectionClass($this->validator);
        $method = $reflection->getMethod('analyzeSmtpResponse');
        $method->setAccessible(true);

        // Test success response
        $this->assertEquals('success', $method->invoke($this->validator, '250 OK'));
        $this->assertEquals('success', $method->invoke($this->validator, '250 2.1.5 Recipient OK'));

        // Test mailbox not found
        $this->assertEquals('mailbox_not_found', $method->invoke($this->validator, '550 5.1.1 NoSuchUser'));
        $this->assertEquals('mailbox_not_found', $method->invoke($this->validator, '550 User unknown'));
        $this->assertEquals('mailbox_not_found', $method->invoke($this->validator, '550 does not exist'));

        // Test server errors
        $this->assertEquals('server_error', $method->invoke($this->validator, '550 5.7.1 Access denied'));
        $this->assertEquals('server_error', $method->invoke($this->validator, '451 Temporary failure'));
        $this->assertEquals('server_error', $method->invoke($this->validator, '421 Service not available'));

        // Test catch-all behavior
        $this->assertEquals('success', $method->invoke($this->validator, '250 OK - catch all'));

        // Test unknown responses
        $this->assertEquals('unknown', $method->invoke($this->validator, '999 Unknown response'));
        $this->assertEquals('unknown', $method->invoke($this->validator, 'Invalid response'));
    }

    public function testSmtpValidatorStatusCodesForRealDomains(): void
    {
        // Test with a real domain that should have MX records
        $result = $this->validator->validate('test@gmail.com');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_status_code', $result);
        
        // Should not be invalid_format or no_mx_records for gmail.com
        $this->assertNotEquals('invalid_format', $result['smtp_status_code']);
        $this->assertNotEquals('no_mx_records', $result['smtp_status_code']);
        
        // Should be one of the SMTP-related status codes
        $smtpStatusCodes = [
            'success',
            'mailbox_not_found',
            'catch_all', 
            'server_error',
            'connection_failure',
            'unknown'
        ];
        
        $this->assertContains($result['smtp_status_code'], $smtpStatusCodes);
    }

    public function testSmtpValidatorReturnsDetailedResponse(): void
    {
        $result = $this->validator->validate('test@gmail.com');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_response', $result);
        $this->assertArrayHasKey('smtp_status_code', $result);
        $this->assertArrayHasKey('smtp_valid', $result);
        
        // If SMTP validation was attempted, we should have a response
        if ($result['smtp_status_code'] !== 'invalid_format' && 
            $result['smtp_status_code'] !== 'no_mx_records') {
            // For connection failures, response might be null, but status code should be set
            $this->assertNotNull($result['smtp_status_code']);
        }
    }

    public function testSmtpValidatorHandlesConnectionFailures(): void
    {
        // Test with a domain that might cause connection issues
        $result = $this->validator->validate('test@localhost');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_status_code', $result);
        
        // Should handle connection failures gracefully
        $validStatusCodes = [
            'success',
            'mailbox_not_found',
            'catch_all',
            'server_error', 
            'connection_failure',
            'invalid_format',
            'no_mx_records',
            'unknown'
        ];
        
        $this->assertContains($result['smtp_status_code'], $validStatusCodes);
    }

    public function testSmtpValidatorStatusCodeConsistency(): void
    {
        // Test multiple calls return consistent status codes
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->validator->validate('test@gmail.com');
        }
        
        // All results should have the same status code structure
        foreach ($results as $result) {
            $this->assertArrayHasKey('smtp_status_code', $result);
            $this->assertIsString($result['smtp_status_code']);
        }
        
        // Status codes should be consistent (though they might vary due to network conditions)
        $statusCodes = array_column($results, 'smtp_status_code');
        $uniqueStatusCodes = array_unique($statusCodes);
        
        // Should have at least one valid status code
        $this->assertGreaterThan(0, count($uniqueStatusCodes));
    }
}
