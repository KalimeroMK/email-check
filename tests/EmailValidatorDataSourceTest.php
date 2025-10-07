<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\EmailValidator;
use PHPUnit\Framework\TestCase;

class EmailValidatorDataSourceTest extends TestCase
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

    public function testEmailValidatorReadsDataSourceFromEnvFile(): void
    {
        // Create test .env file with JSON data source
        $envContent = "DATA_SOURCE=json\nJSON_FILE_PATH=custom_emails.json";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should read from .env)
        $validator = new EmailValidator();
        
        $this->assertEquals('json', $validator->getDataSource());
        $this->assertTrue($validator->useJsonFile());
        $this->assertFalse($validator->useDatabase());
        $this->assertEquals('custom_emails.json', $validator->getJsonFilePath());
    }

    public function testEmailValidatorReadsDatabaseConfigFromEnvFile(): void
    {
        // Create test .env file with database configuration
        $envContent = "DATA_SOURCE=database\nDB_HOST=localhost\nDB_PORT=3306\nDB_DATABASE=test_db\nDB_USERNAME=test_user\nDB_PASSWORD=test_pass";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should read from .env)
        $validator = new EmailValidator();
        
        $this->assertEquals('database', $validator->getDataSource());
        $this->assertTrue($validator->useDatabase());
        $this->assertFalse($validator->useJsonFile());
        
        $dbConfig = $validator->getDatabaseConfig();
        $this->assertEquals('localhost', $dbConfig['host']);
        $this->assertEquals(3306, $dbConfig['port']);
        $this->assertEquals('test_db', $dbConfig['database']);
        $this->assertEquals('test_user', $dbConfig['username']);
        $this->assertEquals('test_pass', $dbConfig['password']);
    }

    public function testEmailValidatorDefaultsToDatabaseWhenNoEnvFile(): void
    {
        // Remove .env file if it exists
        if (file_exists($this->originalEnvFile)) {
            unlink($this->originalEnvFile);
        }
        
        // Create validator (should use defaults)
        $validator = new EmailValidator();
        
        $this->assertEquals('database', $validator->getDataSource());
        $this->assertTrue($validator->useDatabase());
        $this->assertFalse($validator->useJsonFile());
        $this->assertEquals('emails.json', $validator->getJsonFilePath());
    }

    public function testEmailValidatorUserConfigOverridesEnvFile(): void
    {
        // Create test .env file with JSON data source
        $envContent = "DATA_SOURCE=json\nJSON_FILE_PATH=env_emails.json";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator with user config that overrides .env
        $validator = new EmailValidator([
            'data_source' => 'database',
            'json_file_path' => 'user_emails.json',
            'database' => [
                'host' => 'user_host',
                'port' => 5432,
            ],
        ]);
        
        // User config should override .env config
        $this->assertEquals('database', $validator->getDataSource());
        $this->assertTrue($validator->useDatabase());
        $this->assertEquals('user_emails.json', $validator->getJsonFilePath());
        
        $dbConfig = $validator->getDatabaseConfig();
        $this->assertEquals('user_host', $dbConfig['host']);
        $this->assertEquals(5432, $dbConfig['port']);
    }

    public function testEmailValidatorReadsValidationMethodFromEnvFile(): void
    {
        // Create test .env file with validation method
        $envContent = "VALIDATION_METHOD=strict\nDEBUG=true\nVERBOSE=true";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should read from .env)
        $validator = new EmailValidator();
        
        $this->assertEquals('strict', $validator->getConfig('validation_method'));
        $this->assertTrue($validator->getConfig('debug'));
        $this->assertTrue($validator->getConfig('verbose'));
    }

    public function testEmailValidatorReadsDisposableConfigFromEnvFile(): void
    {
        // Create test .env file with disposable email configuration
        $envContent = "CHECK_DISPOSABLE=true\nDISPOSABLE_STRICT=false";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should read from .env)
        $validator = new EmailValidator();
        
        $this->assertTrue($validator->getConfig('check_disposable'));
        $this->assertFalse($validator->getConfig('disposable_strict'));
    }

    public function testEmailValidatorGetsAllConfig(): void
    {
        // Create test .env file with various configurations
        $envContent = "DATA_SOURCE=json\nJSON_FILE_PATH=test.json\nVALIDATION_METHOD=advanced\nDEBUG=true";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should read from .env)
        $validator = new EmailValidator();
        
        $allConfig = $validator->getAllConfig();
        
        $this->assertIsArray($allConfig);
        $this->assertEquals('json', $allConfig['data_source']);
        $this->assertEquals('test.json', $allConfig['json_file_path']);
        $this->assertEquals('advanced', $allConfig['validation_method']);
        $this->assertTrue($allConfig['debug']);
    }

    public function testEmailValidatorHandlesInvalidEnvValues(): void
    {
        // Create test .env file with invalid values
        $envContent = "DATA_SOURCE=invalid\nCHECK_DISPOSABLE=maybe\nDEBUG=yes";
        file_put_contents($this->testEnvFile, $envContent);
        
        // Copy test env to main .env
        copy($this->testEnvFile, $this->originalEnvFile);
        
        // Create validator (should handle invalid values gracefully)
        $validator = new EmailValidator();
        
        // Invalid DATA_SOURCE should fall back to default
        $this->assertEquals('invalid', $validator->getDataSource()); // Raw value is preserved
        
        // Invalid boolean values should be handled by filter_var
        $this->assertFalse($validator->getConfig('check_disposable')); // 'maybe' becomes false
        $this->assertTrue($validator->getConfig('debug')); // 'yes' becomes true (filter_var behavior)
    }
}
