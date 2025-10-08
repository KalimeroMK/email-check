<?php

namespace KalimeroMK\EmailCheck\Data;

use KalimeroMK\EmailCheck\Data\ConfigManager;

class QueryManager
{
    public function __construct()
    {
        // QueryManager doesn't need constructor, uses static methods
    }

    /**
     * Gets SQL query based on configuration
     */
    public function getQuery(): string
    {
        $queryType = ConfigManager::getEnv('QUERY_TYPE', 'valid_emails');

        return match ($queryType) {
            'valid_emails' => $this->getValidEmailsQuery(),
            'all_emails' => $this->getAllEmailsQuery(),
            'invalid_emails' => $this->getInvalidEmailsQuery(),
            'custom' => $this->getCustomQuery(),
            default => $this->getValidEmailsQuery(),
        };
    }

    /**
     * Gets query for valid emails
     */
    private function getValidEmailsQuery(): string
    {
        $baseQuery = ConfigManager::getEnv(
            'VALID_EMAILS_QUERY',
            "SELECT status, email FROM check_emails WHERE status = 'valid'"
        );
        return $this->addLimitAndOffset($baseQuery);
    }

    /**
     * Gets query for all emails
     */
    private function getAllEmailsQuery(): string
    {
        $baseQuery = ConfigManager::getEnv(
            'ALL_EMAILS_QUERY',
            "SELECT status, email FROM check_emails"
        );
        return $this->addLimitAndOffset($baseQuery);
    }

    /**
     * Gets query for invalid emails
     */
    private function getInvalidEmailsQuery(): string
    {
        $baseQuery = ConfigManager::getEnv(
            'INVALID_EMAILS_QUERY',
            "SELECT status, email FROM check_emails WHERE status = 'invalid'"
        );
        return $this->addLimitAndOffset($baseQuery);
    }

    /**
     * Gets custom query
     */
    private function getCustomQuery(): string
    {
        $baseQuery = ConfigManager::getEnv(
            'CUSTOM_QUERY',
            "SELECT status, email FROM check_emails WHERE status = 'valid'"
        );
        return $this->addLimitAndOffset($baseQuery);
    }

    /**
     * Adds LIMIT and OFFSET to query
     */
    private function addLimitAndOffset(string $query): string
    {
        $limit = (int)ConfigManager::getEnv('QUERY_LIMIT', '0');
        $offset = (int)ConfigManager::getEnv('QUERY_OFFSET', '0');

        if ($limit > 0) {
            $query .= ' LIMIT ' . $limit;
            if ($offset > 0) {
                $query .= ' OFFSET ' . $offset;
            }
        }

        return $query;
    }

    /**
     * Gets query for counting results
     */
    public function getCountQuery(): string
    {
        $queryType = ConfigManager::getEnv('QUERY_TYPE', 'valid_emails');

        switch ($queryType) {
            case 'valid_emails':
            default:
                return "SELECT COUNT(*) as total FROM check_emails WHERE status = 'valid'";
            case 'all_emails':
                return "SELECT COUNT(*) as total FROM check_emails";
            case 'invalid_emails':
                return "SELECT COUNT(*) as total FROM check_emails WHERE status = 'invalid'";
            case 'custom':
                // For custom query, we'll make a COUNT version
                $customQuery = ConfigManager::getEnv(
                    'CUSTOM_QUERY',
                    "SELECT status, email FROM check_emails WHERE status = 'valid'"
                );
                return $this->convertToCountQuery($customQuery);
        }
    }

    /**
     * Converts SELECT query to COUNT query
     */
    private function convertToCountQuery(string $query): string
    {
        // Find the SELECT part and replace it with COUNT
        if (preg_match('/SELECT\s+.*?\s+FROM\s+(.+)/i', $query, $matches)) {
            $fromPart = $matches[1];
            return 'SELECT COUNT(*) as total FROM ' . $fromPart;
        }

        // Fallback if it can't be parsed
        return "SELECT COUNT(*) as total FROM check_emails WHERE status = 'valid'";
    }

    /**
     * Gets description of current query
     */
    public function getQueryDescription(): string
    {
        $queryType = ConfigManager::getEnv('QUERY_TYPE', 'valid_emails');
        $limit = (int)ConfigManager::getEnv('QUERY_LIMIT', '0');
        $offset = (int)ConfigManager::getEnv('QUERY_OFFSET', '0');

        $descriptions = [
            'valid_emails' => 'Valid emails only',
            'all_emails' => 'All emails',
            'invalid_emails' => 'Invalid emails only',
            'custom' => 'Custom query'
        ];

        $description = $descriptions[$queryType] ?? 'Unknown query type';

        if ($limit > 0) {
            $description .= ' (limit: ' . $limit;
            if ($offset > 0) {
                $description .= ', offset: ' . $offset;
            }

            $description .= ")";
        }

        return $description;
    }

    /**
     * Gets query parameters for debugging
     */
    /** @return array<string, mixed> */
    public function getQueryInfo(): array
    {
        return [
            'type' => ConfigManager::getEnv('QUERY_TYPE', 'valid_emails'),
            'query' => $this->getQuery(),
            'count_query' => $this->getCountQuery(),
            'limit' => (int)ConfigManager::getEnv('QUERY_LIMIT', '0'),
            'offset' => (int)ConfigManager::getEnv('QUERY_OFFSET', '0'),
            'description' => $this->getQueryDescription()
        ];
    }
}
