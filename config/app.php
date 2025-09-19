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
        'memory_limit' => '512M',
        'max_execution_time' => 300
    ],
    
    // Database settings
    'database' => [
        'driver' => 'mysql',
        'host' => '192.168.142.41',
        'port' => '3307',
        'database' => 'iwinback_baj7f',
        'username' => 'baj7f_external_bi',
        'password' => 'W@2Tv5x9mY&D@J%srn!k',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
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