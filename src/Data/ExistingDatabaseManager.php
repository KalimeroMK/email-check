<?php

namespace KalimeroMK\EmailCheck\Data;

use Illuminate\Database\Capsule\Manager as Capsule;

class ExistingDatabaseManager
{
    private \Illuminate\Database\Capsule\Manager $capsule;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
        $this->initializeDatabase();
    }

    /**
     * Иницијализира конекција со постоечката база
     */
    private function initializeDatabase(): void
    {
        $this->capsule = new Capsule();

        $this->capsule->addConnection([
            'driver' => $this->config['database']['driver'],
            'host' => $this->config['database']['host'],
            'port' => $this->config['database']['port'],
            'database' => $this->config['database']['database'],
            'username' => $this->config['database']['username'],
            'password' => $this->config['database']['password'],
            'charset' => $this->config['database']['charset'],
            'collation' => $this->config['database']['collation'],
            'prefix' => $this->config['database']['prefix'],
            'prefix_indexes' => $this->config['database']['prefix_indexes'],
            'strict' => $this->config['database']['strict'],
            'engine' => $this->config['database']['engine'],
        ]);

        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * Тестира конекција со базата
     */
    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        try {
            $result = $this->capsule->getConnection()->select('SELECT 1 as test');
            return [
                'success' => true,
                'message' => 'Connection successful',
                'test_result' => $result[0]->test
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $exception->getMessage()
            ];
        }
    }

    /**
     * Ги влекува валидни emails од check_emails табелата
     */
    /** @return array<string, mixed> */
    public function getValidEmails(int $limit = 1000, int $offset = 0): array
    {
        try {
            $emails = $this->capsule->table('check_emails')
                ->where('status', 'valid')
                ->limit($limit)
                ->offset($offset)
                ->get();

            return [
                'success' => true,
                'emails' => $emails,
                'count' => count($emails)
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to fetch valid emails: ' . $exception->getMessage(),
                'emails' => []
            ];
        }
    }

    /**
     * Ги влекува сите emails со различни статуси
     */
    /** @return array<string, mixed> */
    public function getAllEmails(int $limit = 1000, int $offset = 0): array
    {
        try {
            $emails = $this->capsule->table('check_emails')
                ->limit($limit)
                ->offset($offset)
                ->get();

            return [
                'success' => true,
                'emails' => $emails,
                'count' => count($emails)
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to fetch emails: ' . $exception->getMessage(),
                'emails' => []
            ];
        }
    }

    /**
     * Ги брои emails по статус
     */
    /** @return array<string, mixed> */
    public function getEmailStats(): array
    {
        try {
            $stats = $this->capsule->table('check_emails')
                ->select('status', $this->capsule->getConnection()->raw('count(*) as count'))
                ->groupBy('status')
                ->get();

            $total = $this->capsule->table('check_emails')->count();
            $valid = $this->capsule->table('check_emails')->where('status', 'valid')->count();

            return [
                'success' => true,
                'statistics' => [
                    'total' => $total,
                    'valid' => $valid,
                    'valid_percentage' => $total > 0 ? round(($valid / $total) * 100, 2) : 0,
                    'by_status' => $stats
                ]
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to get statistics: ' . $exception->getMessage()
            ];
        }
    }

    /**
     * Ги влекува emails со филтри
     */
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getEmailsWithFilters(array $filters = []): array
    {
        try {
            $query = $this->capsule->table('check_emails');

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['email'])) {
                $query->where('email', 'like', '%' . $filters['email'] . '%');
            }

            if (isset($filters['since'])) {
                $query->where('created_at', '>=', $filters['since']);
            }

            if (isset($filters['limit'])) {
                $query->limit($filters['limit']);
            }

            if (isset($filters['offset'])) {
                $query->offset($filters['offset']);
            }

            $emails = $query->get();

            return [
                'success' => true,
                'emails' => $emails,
                'count' => count($emails)
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to fetch emails with filters: ' . $exception->getMessage(),
                'emails' => []
            ];
        }
    }

    /**
     * Ажурира статус на email
     */
    /**
     * @param array<string, mixed> $additionalData
     * @return array<string, mixed>
     */
    public function updateEmailStatus(string $email, string $status, array $additionalData = []): array
    {
        try {
            $updateData = array_merge(['status' => $status], $additionalData);

            $result = $this->capsule->table('check_emails')
                ->where('email', $email)
                ->update($updateData);

            return [
                'success' => true,
                'updated' => $result,
                'message' => sprintf('Updated status for %s to %s', $email, $status)
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to update email status: ' . $exception->getMessage()
            ];
        }
    }

    /**
     * Додава нов email во табелата
     */
    /**
     * @param array<string, mixed> $additionalData
     * @return array<string, mixed>
     */
    public function addEmail(string $email, string $status = 'pending', array $additionalData = []): array
    {
        try {
            $data = array_merge([
                'email' => $email,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], $additionalData);

            $id = $this->capsule->table('check_emails')->insertGetId($data);

            return [
                'success' => true,
                'id' => $id,
                'message' => sprintf('Added email %s with status %s', $email, $status)
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to add email: ' . $exception->getMessage()
            ];
        }
    }

    /**
     * Експортира валидни emails во JSON
     */
    /** @return array<string, mixed> */
    public function exportValidEmails(string $format = 'json'): array
    {
        $result = $this->getValidEmails(10000); // Максимум 10,000 emails

        if (!$result['success']) {
            return $result;
        }

        if ($format === 'csv') {
            $csv = "Email,Status,Created At,Updated At\n";
            foreach ($result['emails'] as $email) {
                $csv .= sprintf(
                    "%s,%s,%s,%s\n",
                    $email->email ?? '',
                    $email->status ?? '',
                    $email->created_at ?? '',
                    $email->updated_at ?? ''
                );
            }

            return ['success' => true, 'data' => $csv, 'format' => 'csv'];
        }

        $validEmails = array_map(fn ($email) => $email->email, $result['emails']->toArray());

        return [
            'success' => true,
            'data' => json_encode($validEmails, JSON_PRETTY_PRINT),
            'format' => 'json',
            'count' => count($validEmails)
        ];
    }

    /**
     * Експортира сите emails во JSON
     */
    /** @return array<string, mixed> */
    public function exportAllEmails(mixed $filters = []): array
    {
        $result = $this->getEmailsWithFilters($filters);

        if (!$result['success']) {
            return $result;
        }

        $exportData = [
            'export_info' => [
                'timestamp' => date('Y-m-d H:i:s'),
                'total_records' => $result['count'],
                'filters_applied' => $filters
            ],
            'emails' => $result['emails']
        ];

        return [
            'success' => true,
            'data' => json_encode($exportData, JSON_PRETTY_PRINT),
            'count' => $result['count']
        ];
    }

    /**
     * Ги влекува структурата на табелата
     */
    /** @return array<string, mixed> */
    public function getTableStructure(): array
    {
        try {
            $columns = $this->capsule->getConnection()->select("DESCRIBE check_emails");

            return [
                'success' => true,
                'columns' => $columns
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to get table structure: ' . $exception->getMessage()
            ];
        }
    }

    /**
     * Извршува custom SQL query
     */
    /** @return array<string, mixed> */
    public function executeCustomQuery(string $sql, int $limit = 1000): array
    {
        try {
            // Безбедност - дозволуваме само SELECT queries
            $sql = trim($sql);
            if (!preg_match('/^SELECT\s+/i', $sql)) {
                return [
                    'success' => false,
                    'message' => 'Only SELECT queries are allowed'
                ];
            }

            // Додаваме LIMIT ако не постои
            if (!preg_match('/\bLIMIT\b/i', $sql)) {
                $sql .= " LIMIT " . $limit;
            }

            $results = $this->capsule->getConnection()->select($sql);

            return [
                'success' => true,
                'results' => $results,
                'count' => count($results),
                'sql_executed' => $sql
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Query execution failed: ' . $exception->getMessage(),
                'sql_attempted' => $sql
            ];
        }
    }

    /**
     * Специјална функција за твојот query
     */
    /** @return array<string, mixed> */
    public function getValidEmailsSimple(int $limit = 1000): array
    {
        try {
            $results = $this->capsule->getConnection()->select("SELECT status, email FROM check_emails WHERE status = 'valid' LIMIT " . $limit);

            return [
                'success' => true,
                'results' => $results,
                'count' => count($results),
                'limit_applied' => $limit
            ];
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to execute query: ' . $exception->getMessage(),
                'results' => []
            ];
        }
    }
}
