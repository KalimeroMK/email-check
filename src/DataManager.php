<?php

namespace App;

use App\ConfigManager;
use App\ExistingDatabaseManager;
use App\QueryManager;

class DataManager
{
    /** @var array<string, mixed> */
    private array $config;

    private ?\App\ExistingDatabaseManager $databaseManager = null;

    private ?\App\QueryManager $queryManager = null;

    private bool $useDatabase;

    /** @param array<string, mixed>|null $config */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?: ConfigManager::load();
        $this->useDatabase = ConfigManager::useDatabase();

        if ($this->useDatabase) {
            $this->databaseManager = new ExistingDatabaseManager($this->config);
            $this->queryManager = new QueryManager();
        }
    }

    /**
     * Gets list of emails for validation
     */
    /** @return array<int, mixed> */
    public function getEmails(?int $limit = null, int $offset = 0): array
    {
        if ($this->useDatabase) {
            return $this->getEmailsFromDatabase($limit, $offset);
        }
        return $this->getEmailsFromJson($limit, $offset);
    }

    /**
     * Gets emails from database
     */
    /** @return array<int, mixed> */
    private function getEmailsFromDatabase(?int $limit = null, int $offset = 0): array
    {
        // Use QueryManager to get correct query
        $query = $this->queryManager->getQuery();

        // If limit/offset parameters are passed, add them
        if (($limit || $offset) && $limit) {
            $query .= ' LIMIT ' . $limit;
            if ($offset > 0) {
                $query .= ' OFFSET ' . $offset;
            }
        }

        $result = $this->databaseManager->executeCustomQuery($query);

        if (!$result['success']) {
            throw new \Exception("Database error: " . $result['message']);
        }

        return $result['results'];
    }

    /**
     * Gets emails from JSON file
     * @return list<object{email: mixed, status: 'valid'}>
     */
    private function getEmailsFromJson(?int $limit = null, int $offset = 0): array
    {
        $jsonFile = ConfigManager::getJsonFilePath();

        if (!file_exists($jsonFile)) {
            throw new \Exception('JSON file not found: ' . $jsonFile);
        }

        $content = file_get_contents($jsonFile);
        if ($content === false) {
            throw new \Exception('Could not read JSON file: ' . $jsonFile);
        }
        $emails = json_decode($content, true);
        if ($emails === null) {
            throw new \Exception('Invalid JSON in file: ' . $jsonFile);
        }

        if (!is_array($emails)) {
            throw new \Exception('Invalid JSON format in file: ' . $jsonFile);
        }

        // Apply limit and offset
        if ($offset > 0) {
            $emails = array_slice($emails, $offset);
        }

        if ($limit) {
            $emails = array_slice($emails, 0, $limit);
        }

        // Convert to objects for compatibility with database results
        $result = [];
        foreach ($emails as $email) {
            $result[] = (object)[
                'email' => $email,
                'status' => 'valid' // Assume all are valid
            ];
        }

        return $result;
    }

    /**
     * Counts total number of emails
     */
    public function countEmails(): int
    {
        if ($this->useDatabase) {
            return $this->countEmailsFromDatabase();
        }
        return $this->countEmailsFromJson();
    }

    /**
     * Counts emails from database
     */
    private function countEmailsFromDatabase(): int
    {
        $countQuery = $this->queryManager->getCountQuery();
        $result = $this->databaseManager->executeCustomQuery($countQuery);

        if (!$result['success']) {
            throw new \Exception("Database error: " . $result['message']);
        }

        return $result['results'][0]->total;
    }

    /**
     * Counts emails from JSON file
     */
    private function countEmailsFromJson(): int
    {
        $jsonFile = ConfigManager::getJsonFilePath();

        if (!file_exists($jsonFile)) {
            throw new \Exception('JSON file not found: ' . $jsonFile);
        }

        $content = file_get_contents($jsonFile);
        if ($content === false) {
            throw new \Exception('Could not read JSON file: ' . $jsonFile);
        }
        $emails = json_decode($content, true);
        if ($emails === null) {
            throw new \Exception('Invalid JSON in file: ' . $jsonFile);
        }

        if (!is_array($emails)) {
            throw new \Exception('Invalid JSON format in file: ' . $jsonFile);
        }

        return count($emails);
    }

    /**
     * Checks if using database
     */
    public function isUsingDatabase(): bool
    {
        return $this->useDatabase;
    }

    /**
     * Gets data source
     */
    public function getDataSource(): string
    {
        return $this->useDatabase ? 'database' : 'json';
    }

    /**
     * Gets file/table name
     */
    public function getSourceName(): string
    {
        if ($this->useDatabase) {
            return 'check_emails table';
        }
        return basename((string) ConfigManager::getJsonFilePath());
    }

    /**
     * Gets query information
     */
    /** @return array<string, mixed> */
    public function getQueryInfo(): array
    {
        if ($this->useDatabase && $this->queryManager) {
            return $this->queryManager->getQueryInfo();
        }

        return [
            'type' => 'json',
            'query' => 'N/A',
            'count_query' => 'N/A',
            'limit' => 0,
            'offset' => 0,
            'description' => 'JSON file source'
        ];
    }

    /**
     * Gets description of current query
     */
    public function getQueryDescription(): string
    {
        if ($this->useDatabase && $this->queryManager) {
            return $this->queryManager->getQueryDescription();
        }

        return 'JSON file source';
    }
}
