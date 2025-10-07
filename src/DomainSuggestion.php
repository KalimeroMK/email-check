<?php

namespace KalimeroMK\EmailCheck;

class DomainSuggestion
{
    /** @var array<string> */
    private array $commonDomains = [
        'gmail.com',
        'yahoo.com',
        'hotmail.com',
        'outlook.com',
        'aol.com',
        'icloud.com',
        'live.com',
        'msn.com',
        'yandex.com',
        'mail.ru',
        'protonmail.com',
        'zoho.com',
        'fastmail.com',
        'tutanota.com',
        'gmx.com',
        'web.de',
        't-online.de',
        'freenet.de',
        'arcor.de',
        'gmx.de',
        'mail.com',
        'gmx.net',
        'tiscali.it',
        'libero.it',
        'virgilio.it',
        'alice.it',
        'tin.it',
        'email.it',
        'inwind.it',
        'fastwebnet.it',
        'windtre.it',
        'tim.it',
        'vodafone.it',
        'tiscali.it',
        'libero.it',
        'virgilio.it',
        'alice.it',
        'tin.it',
        'email.it',
        'inwind.it',
        'fastwebnet.it',
        'windtre.it',
        'tim.it',
        'vodafone.it'
    ];

    /** @var array<string, string> */
    private array $commonTypos = [
        'gmal.com' => 'gmail.com',
        'gmial.com' => 'gmail.com',
        'gmail.co' => 'gmail.com',
        'gmail.cm' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gmaill.com' => 'gmail.com',
        'gmail.co.uk' => 'gmail.com',
        'yahooo.com' => 'yahoo.com',
        'yaho.com' => 'yahoo.com',
        'yahoo.co' => 'yahoo.com',
        'yahoo.cm' => 'yahoo.com',
        'hotmial.com' => 'hotmail.com',
        'hotmai.com' => 'hotmail.com',
        'hotmail.co' => 'hotmail.com',
        'hotmail.cm' => 'hotmail.com',
        'outlok.com' => 'outlook.com',
        'outlook.co' => 'outlook.com',
        'outlook.cm' => 'outlook.com',
        'outloo.com' => 'outlook.com',
        'aol.co' => 'aol.com',
        'aol.cm' => 'aol.com',
        'iclod.com' => 'icloud.com',
        'icloud.co' => 'icloud.com',
        'icloud.cm' => 'icloud.com',
        'liv.com' => 'live.com',
        'live.co' => 'live.com',
        'live.cm' => 'live.com',
        'msn.co' => 'msn.com',
        'msn.cm' => 'msn.com',
        'yandx.com' => 'yandex.com',
        'yandex.co' => 'yandex.com',
        'yandex.cm' => 'yandex.com',
        'mail.r' => 'mail.ru',
        'mail.ru' => 'mail.ru',
        'protonmai.com' => 'protonmail.com',
        'protonmail.co' => 'protonmail.com',
        'protonmail.cm' => 'protonmail.com',
        'zoh.com' => 'zoho.com',
        'zoho.co' => 'zoho.com',
        'zoho.cm' => 'zoho.com',
        'fastmai.com' => 'fastmail.com',
        'fastmail.co' => 'fastmail.com',
        'fastmail.cm' => 'fastmail.com',
        'tutanot.com' => 'tutanota.com',
        'tutanota.co' => 'tutanota.com',
        'tutanota.cm' => 'tutanota.com',
        'gm.com' => 'gmx.com',
        'gmx.co' => 'gmx.com',
        'gmx.cm' => 'gmx.com',
        'web.d' => 'web.de',
        'web.de' => 'web.de',
        't-online.d' => 't-online.de',
        't-online.de' => 't-online.de',
        'freenet.d' => 'freenet.de',
        'freenet.de' => 'freenet.de',
        'arcor.d' => 'arcor.de',
        'arcor.de' => 'arcor.de',
        'gmx.d' => 'gmx.de',
        'gmx.de' => 'gmx.de',
        'mail.co' => 'mail.com',
        'mail.cm' => 'mail.com',
        'gmx.et' => 'gmx.net',
        'gmx.net' => 'gmx.net',
        'tiscali.t' => 'tiscali.it',
        'tiscali.it' => 'tiscali.it',
        'libero.t' => 'libero.it',
        'libero.it' => 'libero.it',
        'virgilio.t' => 'virgilio.it',
        'virgilio.it' => 'virgilio.it',
        'alice.t' => 'alice.it',
        'alice.it' => 'alice.it',
        'tin.t' => 'tin.it',
        'tin.it' => 'tin.it',
        'email.t' => 'email.it',
        'email.it' => 'email.it',
        'inwind.t' => 'inwind.it',
        'inwind.it' => 'inwind.it',
        'fastwebnet.t' => 'fastwebnet.it',
        'fastwebnet.it' => 'fastwebnet.it',
        'windtre.t' => 'windtre.it',
        'windtre.it' => 'windtre.it',
        'tim.t' => 'tim.it',
        'tim.it' => 'tim.it',
        'vodafone.t' => 'vodafone.it',
        'vodafone.it' => 'vodafone.it'
    ];

    /**
     * Suggest a corrected domain for a given email address
     */
    public function suggestDomainCorrection(string $email): ?string
    {
        // Extract domain from email
        $atPos = strrchr($email, "@");
        if (!$atPos) {
            return null;
        }
        
        $domain = substr($atPos, 1);
        $localPart = substr($email, 0, strpos($email, "@"));
        
        // First, check for exact typo matches
        if (isset($this->commonTypos[$domain])) {
            return $localPart . '@' . $this->commonTypos[$domain];
        }
        
        // If no exact match, try to find similar domains using Levenshtein distance
        $suggestion = $this->findSimilarDomain($domain);
        
        if ($suggestion && $suggestion !== $domain) {
            return $localPart . '@' . $suggestion;
        }
        
        return null;
    }

    /**
     * Find similar domain using Levenshtein distance
     */
    private function findSimilarDomain(string $domain): ?string
    {
        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;
        $maxDistance = 3; // Maximum allowed distance for suggestions
        
        foreach ($this->commonDomains as $commonDomain) {
            $distance = levenshtein($domain, $commonDomain);
            
            if ($distance <= $maxDistance && $distance < $bestDistance) {
                $bestMatch = $commonDomain;
                $bestDistance = $distance;
            }
        }
        
        return $bestMatch;
    }

    /**
     * Get all common domains for reference
     */
    public function getCommonDomains(): array
    {
        return $this->commonDomains;
    }

    /**
     * Get all common typos for reference
     */
    public function getCommonTypos(): array
    {
        return $this->commonTypos;
    }

    /**
     * Add a new domain to the common domains list
     */
    public function addCommonDomain(string $domain): void
    {
        if (!in_array($domain, $this->commonDomains, true)) {
            $this->commonDomains[] = $domain;
        }
    }

    /**
     * Add a new typo correction
     */
    public function addTypoCorrection(string $typo, string $correction): void
    {
        $this->commonTypos[$typo] = $correction;
    }
}
