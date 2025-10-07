<?php

require_once __DIR__ . '/DomainSuggestion.php';

use KalimeroMK\EmailCheck\DomainSuggestion;

/**
 * Global helper function for domain correction suggestions
 * 
 * @param string $email The email address to check for domain suggestions
 * @return string|null The corrected email address or null if no suggestion found
 */
function suggestDomainCorrection(string $email): ?string
{
    static $domainSuggestion = null;
    
    if ($domainSuggestion === null) {
        $domainSuggestion = new DomainSuggestion();
    }
    
    return $domainSuggestion->suggestDomainCorrection($email);
}

/**
 * Check if an email domain has a suggestion available
 * 
 * @param string $email The email address to check
 * @return bool True if a suggestion is available
 */
function hasDomainSuggestion(string $email): bool
{
    return suggestDomainCorrection($email) !== null;
}

/**
 * Get detailed domain suggestion information
 * 
 * @param string $email The email address to check
 * @return array|null Array with suggestion details or null if no suggestion
 */
function getDomainSuggestionDetails(string $email): ?array
{
    $suggestion = suggestDomainCorrection($email);
    
    if ($suggestion === null) {
        return null;
    }
    
    return [
        'original_email' => $email,
        'suggested_email' => $suggestion,
        'original_domain' => substr($email, strrpos($email, '@') + 1),
        'suggested_domain' => substr($suggestion, strrpos($suggestion, '@') + 1),
        'confidence' => 'high' // Could be enhanced with confidence scoring
    ];
}
