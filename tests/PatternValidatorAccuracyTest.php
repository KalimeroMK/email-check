<?php

declare(strict_types=1);

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Validators\PatternValidator;
use PHPUnit\Framework\TestCase;

final class PatternValidatorAccuracyTest extends TestCase
{
    public function testStrictModeDoesNotWarnAboutPunycodeDomains(): void
    {
        $validator = new PatternValidator(['pattern_strict_mode' => true]);

        $result = $validator->validate('user@example.xn--p1ai');

        $this->assertTrue($result['pattern_valid']);
        $this->assertSame(
            [],
            $result['warnings'],
            'The double hyphen in an xn-- label is required punycode, not a suspicious character run.',
        );
    }

    public function testStrictModeStillWarnsAboutRunsInTheLocalPart(): void
    {
        $validator = new PatternValidator(['pattern_strict_mode' => true]);

        $result = $validator->validate('john..doe@example.com');

        $this->assertNotSame([], $result['errors'] + $result['warnings']);
    }

    public function testOrdinaryAddressesPassStrictModeCleanly(): void
    {
        $validator = new PatternValidator(['pattern_strict_mode' => true]);

        foreach ([
            'john.doe+tag@example.com',
            'first.last@sub.example.co.uk',
            'user_name@my-site.example.com',
        ] as $email) {
            $result = $validator->validate($email);
            $this->assertTrue($result['pattern_valid'], $email . ' must not be rejected');
            $this->assertSame([], $result['warnings'], $email . ' must not warn');
        }
    }
}
