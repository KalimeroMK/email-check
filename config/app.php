<?php

// Load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

return [
    'settings' => [
        'displayErrorDetails' => true,
        'logErrors' => true,
        'logErrorDetails' => true,
        
        // Email Validator settings
        'timeout' => 5,
        'dns_servers' => ['8.8.8.8', '1.1.1.1'],
        'check_mx' => true,
        'check_a' => true,
        'check_spf' => false,
        'check_dmarc' => false,
        'use_advanced_validation' => true,
        'use_strict_rfc' => false,
    ],
    
    // Database settings
    'database' => [
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost',
        'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306',
        'database' => $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'email_check',
        'username' => $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '',
        'charset' => $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4',
        'collation' => $_ENV['DB_COLLATION'] ?? getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ],
    
    'app' => [
        'name' => 'Email Check App',
        'version' => '1.0.0',
        'url' => 'http://localhost:8000',
    ],
];