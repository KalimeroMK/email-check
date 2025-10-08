<?php

namespace KalimeroMK\EmailCheck\Validators;

class PatternValidator
{
    /** @var array<string> */
    private array $invalidPatterns = [
        // No @ symbol
        '/^[^@]*$/',
        
        // Multiple @ symbols
        '/@.*@/',
        
        // Multiple consecutive dots
        '/\.{2,}/',
        
        // Starts or ends with dot
        '/^\.|\.@|@\.|\.$/',

        // Starts or ends with @
        '/^@|@$/',
        
        // Empty local part
        '/^@/',
        
        // Empty domain part
        '/@$/',
        
        // Spaces in email
        '/\s/',
        
        // Invalid characters
        '/[<>"\[\]\\\\]/',
        
        // Too many dots in domain
        '/\.{3,}/',
        
        // Domain with only numbers
        '/@\d+$/',
        
        // Local part with only dots
        '/^\.+$/',
        
        // Domain with only dots
        '/@\.+$/',
        
        // Invalid TLD patterns
        '/@.*\.\d+$/',  // TLD with numbers
        
        // Too long local part (over 64 chars)
        '/^.{65,}@/',
        
        // Too long domain part (over 253 chars)
        '/@.{254,}$/',
        
        // Too long overall (over 254 chars)
        '/^.{255,}$/',
    ];

    /** @var array<string> */
    private readonly array $strictPatterns;

    /** @var array<string, string> */
    private array $patternDescriptions = [];

    private readonly bool $enablePatternFiltering;

    private readonly bool $strictMode;

    public function __construct(array $config = [])
    {
        $this->enablePatternFiltering = $config['enable_pattern_filtering'] ?? true;
        $this->strictMode = $config['pattern_strict_mode'] ?? false;

        // Strict patterns (additional validation)
        $this->strictPatterns = [
            // Invalid characters in local part
            '/^[^a-zA-Z0-9._+-]+@/',
            
            // Invalid characters in domain
            '/@[^a-zA-Z0-9.-]+/',
            
            // Domain without TLD
            '/@[^.]*$/',
            
            // TLD too short
            '/@.*\.\w{1}$/',
            
            // TLD too long
            '/@.*\.\w{64,}$/',
            
            // Invalid TLD characters
            '/@.*\.[^a-zA-Z]+$/',
            
            // Consecutive special characters
            '/[._+-]{2,}/',
            
            // Starts with special character
            '/^[._+-]/',
            
            // Ends with special character
            '/[._+-]$/',
        ];
    }

    /**
     * Validates email against known invalid patterns
     * 
     * @param string $email Email to validate
     * @return array<string, mixed> Validation result
     */
    public function validate(string $email): array
    {
        $result = [
            'email' => $email,
            'is_valid' => true,
            'pattern_valid' => true,
            'pattern_status' => 'passed',
            'matched_pattern' => null,
            'errors' => [],
            'warnings' => [],
        ];

        if (!$this->enablePatternFiltering) {
            return $result;
        }

        // Check basic invalid patterns
        foreach ($this->invalidPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                $result['is_valid'] = false;
                $result['pattern_valid'] = false;
                $result['pattern_status'] = 'rejected';
                $result['matched_pattern'] = $pattern;
                $result['errors'][] = 'Email matches invalid pattern: ' . $this->getPatternDescription($pattern);
                return $result;
            }
        }

        // Check strict patterns if strict mode is enabled
        if ($this->strictMode) {
            foreach ($this->strictPatterns as $pattern) {
                if (preg_match($pattern, $email)) {
                    $result['pattern_status'] = 'warning';
                    $result['matched_pattern'] = $pattern;
                    $result['warnings'][] = 'Email matches strict pattern: ' . $this->getPatternDescription($pattern);
                }
            }
        }

        return $result;
    }

    /**
     * Gets human-readable description for pattern
     * 
     * @param string $pattern Regex pattern
     * @return string Description
     */
    private function getPatternDescription(string $pattern): string
    {
        $descriptions = [
            '/^[^@]*$/' => 'Missing @ symbol',
            '/@.*@/' => 'Multiple @ symbols',
            '/\.{2,}/' => 'Multiple consecutive dots',
            '/^\.|\.@|@\.|\.$/' => 'Starts or ends with dot',
            '/^@|@$/' => 'Starts or ends with @',
            '/^@/' => 'Empty local part',
            '/@$/' => 'Empty domain part',
            '/\s/' => 'Contains spaces',
            '/[<>"\[\]\\\\]/' => 'Contains invalid characters',
            '/\.{3,}/' => 'Too many dots in domain',
            '/@\d+$/' => 'Domain with only numbers',
            '/^\.+$/' => 'Local part with only dots',
            '/@\.+$/' => 'Domain with only dots',
            '/@.*\.\d+$/' => 'TLD with numbers',
            '/^.{65,}@/' => 'Local part too long (over 64 chars)',
            '/@.{254,}$/' => 'Domain part too long (over 253 chars)',
            '/^.{255,}$/' => 'Email too long (over 254 chars)',
            '/^[^a-zA-Z0-9._+-]+@/' => 'Invalid characters in local part',
            '/@[^a-zA-Z0-9.-]+/' => 'Invalid characters in domain',
            '/@[^.]*$/' => 'Domain without TLD',
            '/@.*\.\w{1}$/' => 'TLD too short',
            '/@.*\.\w{64,}$/' => 'TLD too long',
            '/@.*\.[^a-zA-Z]+$/' => 'Invalid TLD characters',
            '/[._+-]{2,}/' => 'Consecutive special characters',
            '/^[._+-]/' => 'Starts with special character',
            '/[._+-]$/' => 'Ends with special character',
        ];

        return $descriptions[$pattern] ?? $this->patternDescriptions[$pattern] ?? 'Unknown pattern';
    }

    /**
     * Validates multiple emails against patterns
     * 
     * @param array<string> $emails Emails to validate
     * @return array<string, mixed> Validation results
     */
    public function validateBulk(array $emails): array
    {
        $results = [];
        $stats = [
            'total' => count($emails),
            'passed' => 0,
            'rejected' => 0,
            'warnings' => 0,
        ];

        foreach ($emails as $email) {
            $result = $this->validate($email);
            $results[$email] = $result;

            switch ($result['pattern_status']) {
                case 'passed':
                    $stats['passed']++;
                    break;
                case 'rejected':
                    $stats['rejected']++;
                    break;
                case 'warning':
                    $stats['warnings']++;
                    break;
            }
        }

        return [
            'results' => $results,
            'stats' => $stats,
        ];
    }

    /**
     * Gets configuration
     * 
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'enable_pattern_filtering' => $this->enablePatternFiltering,
            'pattern_strict_mode' => $this->strictMode,
            'invalid_patterns_count' => count($this->invalidPatterns),
            'strict_patterns_count' => count($this->strictPatterns),
        ];
    }

    /**
     * Adds custom invalid pattern
     *
     * @param string $pattern Regex pattern
     * @param string $description Human-readable description
     */
    public function addCustomPattern(string $pattern, string $description = ''): void
    {
        $this->invalidPatterns[] = $pattern;
        if ($description !== '' && $description !== '0') {
            $this->patternDescriptions[$pattern] = $description;
        }
    }

    /**
     * Checks if pattern filtering is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enablePatternFiltering;
    }

    /**
     * Checks if strict mode is enabled
     */
    public function isStrictMode(): bool
    {
        return $this->strictMode;
    }
}
