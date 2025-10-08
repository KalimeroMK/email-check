<?php

namespace KalimeroMK\EmailCheck\Scripts;

use Exception;
use Throwable;

/**
 * Disposable Email Domains Updater
 *
 * Automatically fetches and merges disposable email domains from multiple sources:
 * 1. andreis/disposable-email-domains (daily/weekly updates)
 * 2. kickboxio/disposable-email-domains (moderate updates)
 */
class DisposableEmailUpdater
{
    private const ANDREIS_URL = 'https://raw.githubusercontent.com/disposable/disposable-email-domains/master/domains.txt';

    private const ALTERNATIVE_URL = 'https://raw.githubusercontent.com/FGRibreau/mailchecker/master/list.txt';

    private const OUTPUT_FILE = __DIR__ . '/../data/disposable-domains.json';

    private const BACKUP_FILE = __DIR__ . '/../data/disposable-domains-backup.json';

    private array $domains = [];

    private array $sources = [];

    private int $totalDomains = 0;

    public function __construct()
    {
        $this->ensureDataDirectory();
    }

    /**
     * Main update process
     */
    public function update(): array
    {
        $this->log("Starting disposable email domains update...");

        try {
            // Fetch from Andreis (primary source)
            $andreisDomains = $this->fetchAndreisDomains();
            $this->log("Fetched " . count($andreisDomains) . " domains from Andreis");

            // Fetch from alternative source
            $alternativeDomains = $this->fetchAlternativeDomains();
            $this->log("Fetched " . count($alternativeDomains) . " domains from alternative source");

            // Merge and deduplicate
            $this->mergeDomains($andreisDomains, $alternativeDomains);

            // Save merged list
            $this->saveDomains();

            $this->log("Update completed successfully!");

            return [
                'success' => true,
                'total_domains' => $this->totalDomains,
                'andreis_count' => count($andreisDomains),
                'alternative_count' => count($alternativeDomains),
                'sources' => $this->sources,
                'output_file' => self::OUTPUT_FILE,
            ];

        } catch (Throwable $throwable) {
            $this->log("Error during update: " . $throwable->getMessage());
            return [
                'success' => false,
                'error' => $throwable->getMessage(),
            ];
        }
    }

    /**
     * Fetch domains from alternative source
     */
    private function fetchAlternativeDomains(): array
    {
        $this->log("Fetching from alternative source...");

        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Email-Check-Updater/1.0',
            ],
        ]);

        $txt = file_get_contents(self::ALTERNATIVE_URL, false, $context);
        if ($txt === false) {
            $this->log("Warning: Failed to fetch alternative domains, continuing with Andreis only");
            return [];
        }

        $lines = explode("\n", $txt);
        $domains = [];

        foreach ($lines as $line) {
            $domain = trim($line);
            if ($domain !== '' && $domain !== '0' && $this->isValidDomain($domain)) {
                $domains[] = strtolower($domain);
            }
        }

        $this->sources['alternative'] = [
            'url' => self::ALTERNATIVE_URL,
            'count' => count($domains),
            'fetched_at' => date('Y-m-d H:i:s'),
        ];

        return $domains;
    }

    /**
     * Fetch domains from Andreis source
     */
    private function fetchAndreisDomains(): array
    {
        $this->log("Fetching from Andreis...");

        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Email-Check-Updater/1.0',
            ],
        ]);

        $txt = file_get_contents(self::ANDREIS_URL, false, $context);
        if ($txt === false) {
            throw new Exception("Failed to fetch Andreis domains");
        }

        $lines = explode("\n", $txt);
        $domains = [];

        foreach ($lines as $line) {
            $domain = trim($line);
            if ($domain !== '' && $domain !== '0' && $this->isValidDomain($domain)) {
                $domains[] = strtolower($domain);
            }
        }

        $this->sources['andreis'] = [
            'url' => self::ANDREIS_URL,
            'count' => count($domains),
            'fetched_at' => date('Y-m-d H:i:s'),
        ];

        return $domains;
    }

    /**
     * Merge domains from both sources and deduplicate
     */
    private function mergeDomains(array $kickboxDomains, array $andreisDomains): void
    {
        $this->log("Merging and deduplicating domains...");

        // Combine all domains
        $allDomains = array_merge($kickboxDomains, $andreisDomains);

        // Remove duplicates
        $uniqueDomains = array_unique($allDomains);

        // Sort alphabetically
        sort($uniqueDomains);

        $this->domains = $uniqueDomains;
        $this->totalDomains = count($uniqueDomains);

        $this->log("Merged to " . $this->totalDomains . " unique domains");
    }

    /**
     * Save domains to JSON file
     */
    private function saveDomains(): void
    {
        $this->log("Saving domains to file...");

        // Create backup of existing file
        if (file_exists(self::OUTPUT_FILE)) {
            copy(self::OUTPUT_FILE, self::BACKUP_FILE);
        }

        $data = [
            'metadata' => [
                'updated_at' => date('Y-m-d H:i:s'),
                'total_domains' => $this->totalDomains,
                'sources' => $this->sources,
                'generated_by' => 'DisposableEmailUpdater',
                'version' => '1.0',
            ],
            'domains' => $this->domains,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new Exception("Failed to encode domains to JSON");
        }

        if (file_put_contents(self::OUTPUT_FILE, $json) === false) {
            throw new Exception("Failed to write domains file");
        }

        $this->log("Domains saved to " . self::OUTPUT_FILE);
    }

    /**
     * Validate domain format
     */
    private function isValidDomain(string $domain): bool
    {
        // Basic domain validation
        if ($domain === '' || $domain === '0' || strlen($domain) > 253) {
            return false;
        }

        // Check for valid domain characters
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
            return false;
        }

        // Must have at least one dot
        if (!str_contains($domain, '.')) {
            return false;
        }
        // Must not start or end with dot or hyphen
        return !(str_starts_with($domain, '.') || str_ends_with($domain, '.') ||
            str_starts_with($domain, '-') || str_ends_with($domain, '-'));
    }

    /**
     * Ensure data directory exists
     */
    private function ensureDataDirectory(): void
    {
        $dir = dirname(self::OUTPUT_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Log message
     */
    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }

    /**
     * Get current domains count
     */
    public function getCurrentCount(): int
    {
        if (!file_exists(self::OUTPUT_FILE)) {
            return 0;
        }

        $content = file_get_contents(self::OUTPUT_FILE);
        if ($content === false) {
            return 0;
        }

        $data = json_decode($content, true);
        return $data['metadata']['total_domains'] ?? 0;
    }

    /**
     * Get last update time
     */
    public function getLastUpdate(): ?string
    {
        if (!file_exists(self::OUTPUT_FILE)) {
            return null;
        }

        $content = file_get_contents(self::OUTPUT_FILE);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return $data['metadata']['updated_at'] ?? null;
    }

    /**
     * Restore from backup
     */
    public function restoreFromBackup(): bool
    {
        if (!file_exists(self::BACKUP_FILE)) {
            return false;
        }

        return copy(self::BACKUP_FILE, self::OUTPUT_FILE);
    }
}

// CLI usage
if (PHP_SAPI === 'cli') {
    $updater = new DisposableEmailUpdater();

    echo "Disposable Email Domains Updater\n";
    echo "================================\n\n";

    $currentCount = $updater->getCurrentCount();
    $lastUpdate = $updater->getLastUpdate();

    echo "Current domains: " . $currentCount . "\n";
    echo "Last update: " . ($lastUpdate ?? 'Never') . "\n\n";

    $result = $updater->update();

    if ($result['success']) {
        echo "\n✅ Update successful!\n";
        echo "Total domains: " . $result['total_domains'] . "\n";
        echo "Andreis domains: " . $result['andreis_count'] . "\n";
        echo "Alternative domains: " . $result['alternative_count'] . "\n";
        echo "Output file: " . $result['output_file'] . "\n";
    } else {
        echo "\n❌ Update failed: " . $result['error'] . "\n";
        exit(1);
    }
}
