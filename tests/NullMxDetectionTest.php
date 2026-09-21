<?php

declare(strict_types=1);

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Validators\DNSValidator;
use PHPUnit\Framework\TestCase;

/**
 * RFC 7505: a domain publishing a null MX ("." with no host) states that it
 * accepts no mail at all, and receivers must not fall back to its A record.
 */
final class NullMxDetectionTest extends TestCase
{
    public function testANullMxDomainIsNotTreatedAsMailCapable(): void
    {
        $validator = new DNSValidator(['check_mx' => true, 'check_a' => true]);

        $result = $validator->validateDomain('throwaway.email');

        $this->assertTrue($result['has_null_mx'], 'throwaway.email publishes a null MX');
        $this->assertFalse($result['has_mx'], 'A null MX means the domain accepts no mail');
        $this->assertFalse($result['has_a'], 'RFC 7505 forbids falling back to the A record');
        $this->assertNotEmpty($result['errors']);
    }

    public function testANormalDomainIsStillMailCapable(): void
    {
        $validator = new DNSValidator(['check_mx' => true, 'check_a' => true]);

        $result = $validator->validateDomain('gmail.com');

        $this->assertFalse($result['has_null_mx']);
        $this->assertTrue($result['has_mx']);
    }
}
