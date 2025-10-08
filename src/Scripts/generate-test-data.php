<?php

namespace KalimeroMK\EmailCheck\Scripts;

/**
 * Test Data Generator for Mass Email Validation
 * 
 * Generates sample email data for testing the mass validation system
 */
class TestDataGenerator
{
    private array $validDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com',
        'icloud.com', 'protonmail.com', 'zoho.com', 'mail.com', 'yandex.com',
        'live.com', 'msn.com', 'comcast.net', 'verizon.net', 'att.net',
        'sbcglobal.net', 'bellsouth.net', 'cox.net', 'charter.net', 'earthlink.net'
    ];
    
    private array $invalidDomains = [
        'nonexistent12345.com', 'invalid-domain-xyz.net', 'fake-email-domain.org',
        'test-invalid.co.uk', 'dummy-domain.info', 'not-real-domain.biz'
    ];
    
    private array $disposableDomains = [
        'mailinator.com', '10minutemail.com', 'guerrillamail.com', 'tempmail.org',
        'throwaway.email', 'yopmail.com', 'maildrop.cc', 'sharklasers.com'
    ];
    
    private array $commonNames = [
        'john', 'jane', 'mike', 'sarah', 'david', 'lisa', 'chris', 'amanda',
        'robert', 'jennifer', 'michael', 'jessica', 'william', 'ashley',
        'james', 'emily', 'richard', 'samantha', 'charles', 'stephanie'
    ];
    
    private array $commonSurnames = [
        'smith', 'johnson', 'williams', 'brown', 'jones', 'garcia', 'miller',
        'davis', 'rodriguez', 'martinez', 'hernandez', 'lopez', 'gonzalez',
        'wilson', 'anderson', 'thomas', 'taylor', 'moore', 'jackson', 'martin'
    ];
    
    /**
     * Generate test email data
     */
    public function generateTestData(int $count = 1000000): array
    {
        echo "🔄 Generating " . number_format($count) . " test emails...\n";
        
        $emails = [];
        $validCount = 0;
        $invalidCount = 0;
        $disposableCount = 0;
        
        // Distribution: 70% valid, 20% invalid, 10% disposable
        $validTarget = (int) ($count * 0.7);
        $invalidTarget = (int) ($count * 0.2);
        $disposableTarget = $count - $validTarget - $invalidTarget;
        
        // Generate valid emails
        for ($i = 0; $i < $validTarget; $i++) {
            $emails[] = $this->generateValidEmail();
            $validCount++;
            
            if ($i % 10000 === 0) {
                echo "   📧 Generated " . number_format($i) . " valid emails...\n";
            }
        }
        
        // Generate invalid emails
        for ($i = 0; $i < $invalidTarget; $i++) {
            $emails[] = $this->generateInvalidEmail();
            $invalidCount++;
            
            if ($i % 10000 === 0) {
                echo "   📧 Generated " . number_format($i) . " invalid emails...\n";
            }
        }
        
        // Generate disposable emails
        for ($i = 0; $i < $disposableTarget; $i++) {
            $emails[] = $this->generateDisposableEmail();
            $disposableCount++;
            
            if ($i % 10000 === 0) {
                echo "   📧 Generated " . number_format($i) . " disposable emails...\n";
            }
        }
        
        // Shuffle the array to randomize order
        shuffle($emails);
        
        echo "✅ Generated " . number_format(count($emails)) . " emails:\n";
        echo "   📧 Valid: " . number_format($validCount) . "\n";
        echo "   📧 Invalid: " . number_format($invalidCount) . "\n";
        echo "   📧 Disposable: " . number_format($disposableCount) . "\n";
        
        return $emails;
    }
    
    /**
     * Generate a valid email
     */
    private function generateValidEmail(): string
    {
        $name = $this->getRandomName();
        $domain = $this->validDomains[array_rand($this->validDomains)];
        
        // Add some variations
        $variations = [
            $name,
            $name . rand(1, 999),
            $name . '.' . $this->commonSurnames[array_rand($this->commonSurnames)],
            $name . '_' . rand(1, 99),
        ];
        
        $localPart = $variations[array_rand($variations)];
        
        return strtolower($localPart . '@' . $domain);
    }
    
    /**
     * Generate an invalid email
     */
    private function generateInvalidEmail(): string
    {
        $patterns = [
            // Missing @
            $this->getRandomName() . 'gmail.com',
            // Multiple @
            $this->getRandomName() . '@@' . $this->invalidDomains[array_rand($this->invalidDomains)],
            // Invalid domain
            $this->getRandomName() . '@' . $this->invalidDomains[array_rand($this->invalidDomains)],
            // Too long local part
            str_repeat('a', 70) . '@gmail.com',
            // Invalid characters
            $this->getRandomName() . '!@gmail.com',
            // Empty local part
            '@gmail.com',
            // Empty domain
            $this->getRandomName() . '@',
            // Spaces
            $this->getRandomName() . ' @gmail.com',
        ];
        
        return $patterns[array_rand($patterns)];
    }
    
    /**
     * Generate a disposable email
     */
    private function generateDisposableEmail(): string
    {
        $name = $this->getRandomName();
        $domain = $this->disposableDomains[array_rand($this->disposableDomains)];
        
        $variations = [
            $name,
            $name . rand(1, 999),
            'temp' . rand(1, 999),
            'test' . rand(1, 999),
        ];
        
        $localPart = $variations[array_rand($variations)];
        
        return strtolower($localPart . '@' . $domain);
    }
    
    /**
     * Get random name
     */
    private function getRandomName(): string
    {
        return $this->commonNames[array_rand($this->commonNames)];
    }
    
    /**
     * Save emails to JSON file
     */
    public function saveToFile(array $emails, string $filename): void
    {
        echo "💾 Saving emails to {$filename}...\n";
        
        $data = [
            'metadata' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'total_count' => count($emails),
                'generated_by' => 'TestDataGenerator',
                'description' => 'Test email data for mass validation testing',
            ],
            'emails' => $emails,
        ];
        
        $json = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($filename, $json);
        
        $fileSize = filesize($filename);
        echo "✅ Saved " . number_format(count($emails)) . " emails to {$filename}\n";
        echo "📁 File size: " . $this->formatBytes($fileSize) . "\n";
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    echo "Test Data Generator for Mass Email Validation\n";
    echo "==============================================\n\n";
    
    // Check command line arguments
    if ($argc < 2) {
        echo "Usage: php generate-test-data.php <count> [output_file]\n";
        echo "\nExamples:\n";
        echo "  php generate-test-data.php 1000000 emails_1m.json\n";
        echo "  php generate-test-data.php 9000000 emails_9m.json\n";
        echo "\nDefault output file: test_emails.json\n";
        exit(1);
    }
    
    $count = (int) $argv[1];
    $outputFile = $argv[2] ?? 'test_emails.json';
    
    if ($count <= 0) {
        echo "❌ Error: Count must be a positive number.\n";
        exit(1);
    }
    
    echo "📊 Generating " . number_format($count) . " test emails...\n";
    echo "📁 Output file: {$outputFile}\n\n";
    
    $generator = new TestDataGenerator();
    $emails = $generator->generateTestData($count);
    
    echo "\n";
    $generator->saveToFile($emails, $outputFile);
    
    echo "\n🎉 Test data generation completed!\n";
    echo "📧 You can now use this file with the mass email validator:\n";
    echo "   php mass-email-validator.php {$outputFile}\n";
}
