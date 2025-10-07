<?php

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Detectors\DisposableEmailDetector;
use PHPUnit\Framework\TestCase;

class DisposableEmailDetectorTest extends TestCase
{
    private DisposableEmailDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new DisposableEmailDetector();
    }

    public function testIsDisposableWithDisposableEmail(): void
    {
        $disposableEmails = [
            'test@10minutemail.com',
            'user@guerrillamail.com',
            'john@mailinator.com',
            'jane@tempmail.org',
            'temp@throwaway.email',
            'spam@yopmail.com',
        ];

        foreach ($disposableEmails as $email) {
            $this->assertTrue($this->detector->isDisposable($email), "Email $email should be detected as disposable");
        }
    }

    public function testIsDisposableWithNormalEmail(): void
    {
        $normalEmails = [
            'test@gmail.com',
            'user@yahoo.com',
            'john@hotmail.com',
            'jane@outlook.com',
            'business@company.com',
            'student@university.edu',
        ];

        foreach ($normalEmails as $email) {
            $this->assertFalse($this->detector->isDisposable($email), "Email $email should not be detected as disposable");
        }
    }

    public function testIsDisposableDomainWithDisposableDomain(): void
    {
        $disposableDomains = [
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com',
            'tempmail.org',
            'throwaway.email',
            'yopmail.com',
        ];

        foreach ($disposableDomains as $domain) {
            $this->assertTrue($this->detector->isDisposableDomain($domain), "Domain $domain should be detected as disposable");
        }
    }

    public function testIsDisposableDomainWithNormalDomain(): void
    {
        $normalDomains = [
            'gmail.com',
            'yahoo.com',
            'hotmail.com',
            'outlook.com',
            'company.com',
            'university.edu',
        ];

        foreach ($normalDomains as $domain) {
            $this->assertFalse($this->detector->isDisposableDomain($domain), "Domain $domain should not be detected as disposable");
        }
    }

    public function testCaseInsensitiveDetection(): void
    {
        $this->assertTrue($this->detector->isDisposable('test@10MINUTEMAIL.COM'));
        $this->assertTrue($this->detector->isDisposable('test@GuerrillaMail.COM'));
        $this->assertTrue($this->detector->isDisposable('test@MAILINATOR.COM'));
    }

    public function testAddDisposableDomain(): void
    {
        $customDomain = 'custom-disposable.com';
        
        // Initially should not be detected as disposable
        $this->assertFalse($this->detector->isDisposableDomain($customDomain));
        
        // Add the domain
        $this->detector->addDisposableDomain($customDomain);
        
        // Now should be detected as disposable
        $this->assertTrue($this->detector->isDisposableDomain($customDomain));
        $this->assertTrue($this->detector->isDisposable("test@$customDomain"));
    }

    public function testRemoveDisposableDomain(): void
    {
        $domain = '10minutemail.com';
        
        // Initially should be detected as disposable
        $this->assertTrue($this->detector->isDisposableDomain($domain));
        
        // Remove the domain
        $this->detector->removeDisposableDomain($domain);
        
        // Now should not be detected as disposable
        $this->assertFalse($this->detector->isDisposableDomain($domain));
        $this->assertFalse($this->detector->isDisposable("test@$domain"));
    }

    public function testGetDisposableDomains(): void
    {
        $domains = $this->detector->getDisposableDomains();
        
        $this->assertIsArray($domains);
        $this->assertGreaterThan(0, count($domains));
        $this->assertContains('10minutemail.com', $domains);
        $this->assertContains('guerrillamail.com', $domains);
        $this->assertContains('mailinator.com', $domains);
    }

    public function testGetDisposableDomainCount(): void
    {
        $count = $this->detector->getDisposableDomainCount();
        
        $this->assertIsInt($count);
        $this->assertGreaterThan(0, $count);
        $this->assertEquals(count($this->detector->getDisposableDomains()), $count);
    }

    public function testInvalidEmailFormat(): void
    {
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            '',
            'user@domain',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse($this->detector->isDisposable($email), "Invalid email $email should not be detected as disposable");
        }
    }

    public function testEmptyDomain(): void
    {
        $this->assertFalse($this->detector->isDisposableDomain(''));
        $this->assertFalse($this->detector->isDisposableDomain('   '));
    }

    public function testDomainWithWhitespace(): void
    {
        $this->assertTrue($this->detector->isDisposableDomain('  10minutemail.com  '));
        $this->assertTrue($this->detector->isDisposableDomain("\tguerrillamail.com\n"));
    }

    public function testDuplicateDomainAddition(): void
    {
        $domain = 'test-disposable.com';
        $initialCount = $this->detector->getDisposableDomainCount();
        
        // Add domain twice
        $this->detector->addDisposableDomain($domain);
        $this->detector->addDisposableDomain($domain);
        
        // Count should only increase by 1
        $this->assertEquals($initialCount + 1, $this->detector->getDisposableDomainCount());
    }
}
