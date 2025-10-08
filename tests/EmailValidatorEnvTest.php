<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\EmailValidator;
use PHPUnit\Framework\TestCase;

class EmailValidatorEnvTest extends TestCase
{
    private string $originalEnvFile;
    private string $testEnvFile;

    protected function setUp(): void
    {
        $this->testEnvFile = __DIR__ . '/../.env.test';
        $this->originalEnvFile = __DIR__ . '/../.env';
        
        // Backup original .env if it exists
        if (file_exists($this->originalEnvFile)) {
            copy($this->originalEnvFile, $this->originalEnvFile . '.backup');
        }
    }

    protected function tearDown(): void
    {
        // Restore original .env if it existed
        if (file_exists($this->originalEnvFile . '.backup')) {
            rename($this->originalEnvFile . '.backup', $this->originalEnvFile);
        }
        
        // Clean up test files
        if (file_exists($this->testEnvFile)) {
            unlink($this->testEnvFile);
        }
    }

    /**
     * @group smtp
     */
    public function testEmailValidatorReadsSmtpFromEnvFile(): void
    {
        // Create test .env file with SMTP enabled
        $envContent = "CHECK_SMTP=true\nSMTP_TIMEOUT=15\nSMTP_FROM_EMAIL=test@env.com\nSMTP_FROM_NAME=Env Test";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should read from .env)
        $validator = new EmailValidator();
        
        // Use reflection to access private config
        $reflection = new \ReflectionClass($validator);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($validator);
        
        $this->assertTrue($config['check_smtp'], 'SMTP should be enabled from .env file');
        $this->assertEquals(15, $config['smtp_timeout'], 'SMTP timeout should be read from .env file');
        $this->assertEquals('test@env.com', $config['smtp_from_email'], 'SMTP from email should be read from .env file');
        $this->assertEquals('Env Test', $config['smtp_from_name'], 'SMTP from name should be read from .env file');
    }

    public function testEmailValidatorReadsSmtpDisabledFromEnvFile(): void
    {
        // Create test .env file with SMTP disabled
        $envContent = "CHECK_SMTP=false\nSMTP_TIMEOUT=5\nSMTP_FROM_EMAIL=disabled@env.com";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should read from .env)
        $validator = new EmailValidator();
        
        // Use reflection to access private config
        $reflection = new \ReflectionClass($validator);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($validator);
        
        $this->assertFalse($config['check_smtp'], 'SMTP should be disabled from .env file');
        $this->assertEquals(5, $config['smtp_timeout'], 'SMTP timeout should be read from .env file');
        $this->assertEquals('disabled@env.com', $config['smtp_from_email'], 'SMTP from email should be read from .env file');
    }

    public function testEmailValidatorWorksWithoutEnvFile(): void
    {
        // Remove .env file if it exists
        if (file_exists($this->originalEnvFile)) {
            unlink($this->originalEnvFile);
        }
        
        // Create validator (should use defaults)
        $validator = new EmailValidator();
        
        // Use reflection to access private config
        $reflection = new \ReflectionClass($validator);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($validator);
        
        $this->assertFalse($config['check_smtp'], 'SMTP should be disabled by default when no .env file');
        $this->assertEquals(10, $config['smtp_timeout'], 'SMTP timeout should use default value');
        $this->assertEquals('test@example.com', $config['smtp_from_email'], 'SMTP from email should use default value');
    }

    /**
     * @group smtp
     */
    public function testEmailValidatorUserConfigOverridesEnvFile(): void
    {
        // Create test .env file with SMTP enabled
        $envContent = "CHECK_SMTP=true\nSMTP_TIMEOUT=15\nSMTP_FROM_EMAIL=env@test.com";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator with user config that overrides .env
        $validator = new EmailValidator([
            'check_smtp' => false,
            'smtp_timeout' => 20,
            'smtp_from_email' => 'user@override.com',
        ]);
        
        // Use reflection to access private config
        $reflection = new \ReflectionClass($validator);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $config = $configProperty->getValue($validator);
        
        // User config should override .env config
        $this->assertFalse($config['check_smtp'], 'User config should override .env file');
        $this->assertEquals(20, $config['smtp_timeout'], 'User config should override .env timeout');
        $this->assertEquals('user@override.com', $config['smtp_from_email'], 'User config should override .env email');
    }

    /**
     * @group smtp
     */
    public function testEmailValidatorValidatesWithEnvSmtpSettings(): void
    {
        // Create test .env file with SMTP enabled
        $envContent = "CHECK_SMTP=false\nSMTP_TIMEOUT=5\nSMTP_FROM_EMAIL=validator@test.com\nSMTP_FROM_NAME=Env Validator";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should read SMTP settings from .env)
        $validator = new EmailValidator();
        
        // Validate an email (SMTP should be enabled from .env)
        $result = $validator->validate('test@gmail.com');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('smtp_valid', $result);
        $this->assertArrayHasKey('smtp_response', $result);
        
        // Should be null since SMTP is disabled
        $this->assertNull($result['smtp_valid']);
        $this->assertNull($result['smtp_status_code']);
        $this->assertNull($result['smtp_response']);
    }
}
