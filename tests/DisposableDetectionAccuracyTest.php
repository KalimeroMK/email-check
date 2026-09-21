<?php

declare(strict_types=1);

namespace KalimeroMK\EmailCheck\Tests;

use KalimeroMK\EmailCheck\Detectors\DisposableEmailDetector;
use PHPUnit\Framework\TestCase;

final class DisposableDetectionAccuracyTest extends TestCase
{
    public function testItLoadsTheExternalListRatherThanTheBuiltInFallback(): void
    {
        $detector = new DisposableEmailDetector();

        $this->assertTrue(
            $detector->hasExternalData(),
            'The curated list must be found; the built-in fallback covers a fraction of it.',
        );
        $this->assertGreaterThan(
            10000,
            $detector->getDisposableDomainCount(),
            'Falling back to the built-in list silently drops most of the coverage.',
        );
    }

    public function testItDetectsProvidersThatOnlyExistInTheExternalList(): void
    {
        $detector = new DisposableEmailDetector();

        foreach (['trashmail.de', 'yopmail.fr', 'spam4.me'] as $domain) {
            $this->assertTrue($detector->isDisposableDomain($domain), $domain . ' should be disposable');
        }
    }

    public function testItDetectsSubdomainsOfDisposableProviders(): void
    {
        $detector = new DisposableEmailDetector();

        $this->assertTrue($detector->isDisposableDomain('test.mailinator.com'));
        $this->assertTrue($detector->isDisposable('someone@anything.mailinator.com'));
    }

    public function testItDoesNotFlagLegitimateDomains(): void
    {
        $detector = new DisposableEmailDetector();

        foreach (['gmail.com', 'example.com', 'mail.google.com', 'zitcha.com'] as $domain) {
            $this->assertFalse($detector->isDisposableDomain($domain), $domain . ' must not be disposable');
        }
    }

    public function testSubdomainMatchingNeverFallsBackToABareTld(): void
    {
        $detector = new DisposableEmailDetector();

        $this->assertFalse($detector->isDisposableDomain('com'));
        $this->assertFalse($detector->isDisposableDomain('org'));
    }

    public function testLookupsAreNotLinearOverTheWholeList(): void
    {
        $detector = new DisposableEmailDetector();

        $start = microtime(true);
        for ($i = 0; $i < 5000; $i++) {
            $detector->isDisposableDomain('absent-domain-' . $i . '.example');
        }
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(
            100,
            $elapsedMs,
            'A linear scan of ~72k domains per lookup makes bulk validation unusable.',
        );
    }
}
