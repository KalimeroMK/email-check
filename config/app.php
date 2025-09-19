<?php

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
        
        // SMTP validation settings
        'smtp_validation' => false, // Enable for real validation (risky)
        'smtp_timeout' => 10,
        'smtp_max_connections' => 3, // Smaller number to not overload server
        'smtp_max_checks' => 50, // Maximum number of SMTP checks before falling back to DNS
        'smtp_rate_limit_delay' => 3, // Seconds between SMTP checks
        
        // Local SMTP validation settings (safe)
        'local_smtp_validation' => true, // Enable local SMTP validation
        'local_smtp_host' => 'localhost',
        'local_smtp_port' => 1025,
        
        'from_email' => 'test@example.com',
        'from_name' => 'Email Validator',
        
        // Batch Processing settings
        'batch_size' => 100,
        'max_concurrent' => 10,
        'async_chunk_size' => 100,
        'async_timeout' => 30,
        'async_sleep_time' => 50000,
        'memory_limit' => '512M',
        'max_execution_time' => 300
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