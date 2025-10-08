<?php

namespace KalimeroMK\EmailCheck\Detectors;

class DisposableEmailDetector
{
    /** @var array<string> */
    private array $disposableDomains = [];

    private readonly string $dataFile;

    public function __construct()
    {
        $this->dataFile = __DIR__ . '/../data/disposable-domains.json';
        $this->loadDisposableDomains();
    }

    /**
     * Check if an email domain is disposable
     */
    public function isDisposable(string $email): bool
    {
        $domain = $this->extractDomain($email);
        return $this->isDisposableDomain($domain);
    }

    /**
     * Check if a domain is disposable
     */
    public function isDisposableDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        return in_array($domain, $this->disposableDomains, true);
    }

    /**
     * Extract domain from email
     */
    private function extractDomain(string $email): string
    {
        $atPos = strrchr($email, "@");
        if (!$atPos) {
            return '';
        }

        return substr($atPos, 1);
    }

    /**
     * Load disposable domains list from external sources
     */
    private function loadDisposableDomains(): void
    {
        // Try to load from external data file first
        if ($this->loadFromDataFile()) {
            return;
        }

        // Fallback to built-in list if external file is not available
        $this->loadBuiltInDomains();
    }

    /**
     * Load domains from external data file
     */
    private function loadFromDataFile(): bool
    {
        if (!file_exists($this->dataFile)) {
            return false;
        }

        try {
            $json = file_get_contents($this->dataFile);
            if ($json === false) {
                return false;
            }

            $data = json_decode($json, true);
            if (!is_array($data) || !isset($data['domains'])) {
                return false;
            }

            $this->disposableDomains = $data['domains'];
            return true;

        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Load built-in disposable domains (fallback)
     */
    private function loadBuiltInDomains(): void
    {
        // Basic list of common disposable email domains
        $this->disposableDomains = [
            '10minutemail.com',
            '10minutemail.co.uk',
            '10minutemail.de',
            '10minutemail.net',
            '10minutemail.org',
            'guerrillamail.com',
            'guerrillamail.de',
            'guerrillamail.net',
            'guerrillamail.org',
            'guerrillamailblock.com',
            'mailinator.com',
            'mailinator.net',
            'mailinator.org',
            'mailinator2.com',
            'tempmail.org',
            'tempmail.net',
            'tempmail.com',
            'throwaway.email',
            'throwaway.ml',
            'yopmail.com',
            'yopmail.net',
            'yopmail.org',
            'maildrop.cc',
            'sharklasers.com',
            'mailnesia.com',
            'mailcatch.com',
            'getnada.com',
            'mailtothis.com',
            'spamgourmet.com',
            'mailnull.com',
            'mailme.lv',
            'mailmetrash.com',
            'trashmail.net',
            'trashmail.com',
            'dispostable.com',
            'temp-mail.org',
            'temp-mail.ru',
            'temp-mail.net',
            'temp-mail.com',
            'tempmailaddress.com',
            'tempmaildemo.com',
            'tempmailer.com',
            'tempmailer.de',
            'tempmailer.net',
            'tempmailer.org',
            'tempmailer.info',
            'tempmailer.co.uk',
            'tempmailer.fr',
            'tempmailer.es',
            'tempmailer.it',
            'tempmailer.nl',
            'tempmailer.be',
            'tempmailer.ch',
            'tempmailer.at',
            'tempmailer.se',
            'tempmailer.no',
            'tempmailer.dk',
            'tempmailer.fi',
            'tempmailer.pl',
            'tempmailer.cz',
            'tempmailer.sk',
            'tempmailer.hu',
            'tempmailer.ro',
            'tempmailer.bg',
            'tempmailer.hr',
            'tempmailer.si',
            'tempmailer.rs',
            'tempmailer.ba',
            'tempmailer.me',
            'tempmailer.mk',
            'tempmailer.al',
            'tempmailer.mt',
            'tempmailer.cy',
            'tempmailer.gr',
            'tempmailer.tr',
            'tempmailer.ua',
            'tempmailer.by',
            'tempmailer.lt',
            'tempmailer.lv',
            'tempmailer.ee',
            'tempmailer.ru',
            'tempmailer.kz',
            'tempmailer.uz',
            'tempmailer.tj',
            'tempmailer.kg',
            'tempmailer.tm',
            'tempmailer.az',
            'tempmailer.am',
            'tempmailer.ge',
            'tempmailer.md',
            'tempmailer.mo',
            'tempmailer.hk',
            'tempmailer.tw',
            'tempmailer.jp',
            'tempmailer.kr',
            'tempmailer.cn',
            'tempmailer.in',
            'tempmailer.pk',
            'tempmailer.bd',
            'tempmailer.lk',
            'tempmailer.np',
            'tempmailer.bt',
            'tempmailer.mv',
            'tempmailer.th',
            'tempmailer.vn',
            'tempmailer.la',
            'tempmailer.kh',
            'tempmailer.mm',
            'tempmailer.my',
            'tempmailer.sg',
            'tempmailer.id',
            'tempmailer.ph',
            'tempmailer.bn',
            'tempmailer.tl',
            'tempmailer.fj',
            'tempmailer.pg',
            'tempmailer.sb',
            'tempmailer.vu',
            'tempmailer.nc',
            'tempmailer.pf',
            'tempmailer.ws',
            'tempmailer.to',
            'tempmailer.tv',
            'tempmailer.ki',
            'tempmailer.nr',
            'tempmailer.nu',
            'tempmailer.nz',
            'tempmailer.au',
            'tempmailer.pn',
            'tempmailer.tk',
            'tempmailer.wf',
            'tempmailer.yu',
            'tempmailer.zm',
            'tempmailer.zw',
            'tempmailer.za',
            'tempmailer.sz',
            'tempmailer.ls',
            'tempmailer.bw',
            'tempmailer.na',
            'tempmailer.ao',
            'tempmailer.zr',
            'tempmailer.cd',
            'tempmailer.cg',
            'tempmailer.cm',
            'tempmailer.cf',
            'tempmailer.td',
            'tempmailer.ne',
            'tempmailer.ng',
            'tempmailer.bj',
            'tempmailer.tg',
            'tempmailer.gh',
            'tempmailer.bf',
            'tempmailer.ci',
            'tempmailer.lr',
            'tempmailer.sl',
            'tempmailer.gn',
            'tempmailer.gw',
            'tempmailer.gm',
            'tempmailer.sn',
            'tempmailer.mr',
            'tempmailer.ml',
            'tempmailer.dz',
            'tempmailer.tn',
            'tempmailer.ly',
            'tempmailer.eg',
            'tempmailer.sd',
            'tempmailer.ss',
            'tempmailer.et',
            'tempmailer.er',
            'tempmailer.dj',
            'tempmailer.so',
            'tempmailer.ke',
            'tempmailer.ug',
            'tempmailer.rw',
            'tempmailer.bi',
            'tempmailer.tz',
            'tempmailer.mz',
            'tempmailer.mg',
            'tempmailer.mu',
            'tempmailer.sc',
            'tempmailer.km',
            'tempmailer.yt',
            'tempmailer.re',
            'tempmailer.mw',
        ];
    }

    /**
     * Get all disposable domains
     */
    public function getDisposableDomains(): array
    {
        return $this->disposableDomains;
    }

    /**
     * Add a disposable domain
     */
    public function addDisposableDomain(string $domain): void
    {
        $domain = strtolower(trim($domain));
        if (!in_array($domain, $this->disposableDomains, true)) {
            $this->disposableDomains[] = $domain;
        }
    }

    /**
     * Remove a domain from disposable list
     */
    public function removeDisposableDomain(string $domain): void
    {
        $domain = strtolower(trim($domain));
        $key = array_search($domain, $this->disposableDomains, true);
        if ($key !== false) {
            unset($this->disposableDomains[$key]);
            $this->disposableDomains = array_values($this->disposableDomains);
        }
    }

    /**
     * Get count of disposable domains
     */
    public function getDisposableDomainCount(): int
    {
        return count($this->disposableDomains);
    }

    /**
     * Get metadata about the disposable domains list
     */
    public function getDomainsMetadata(): array
    {
        if (!file_exists($this->dataFile)) {
            return [
                'source' => 'built-in',
                'total_domains' => count($this->disposableDomains),
                'last_updated' => null,
            ];
        }

        try {
            $json = file_get_contents($this->dataFile);
            if ($json === false) {
                return [
                    'source' => 'built-in',
                    'total_domains' => count($this->disposableDomains),
                    'last_updated' => null,
                ];
            }

            $data = json_decode($json, true);
            if (!is_array($data) || !isset($data['metadata'])) {
                return [
                    'source' => 'built-in',
                    'total_domains' => count($this->disposableDomains),
                    'last_updated' => null,
                ];
            }

            return [
                'source' => 'external',
                'total_domains' => $data['metadata']['total_domains'] ?? count($this->disposableDomains),
                'last_updated' => $data['metadata']['updated_at'] ?? null,
                'sources' => $data['metadata']['sources'] ?? [],
            ];

        } catch (\Throwable) {
            return [
                'source' => 'built-in',
                'total_domains' => count($this->disposableDomains),
                'last_updated' => null,
            ];
        }
    }

    /**
     * Check if external data file exists
     */
    public function hasExternalData(): bool
    {
        return file_exists($this->dataFile);
    }

    /**
     * Get path to external data file
     */
    public function getDataFilePath(): string
    {
        return $this->dataFile;
    }
}
