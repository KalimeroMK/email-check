<?php

// Set environment variables for production
putenv('DB_HOST=192.168.142.41');
putenv('DB_PORT=3307');
putenv('DB_DATABASE=iwinback_baj7f');
putenv('DB_USERNAME=baj7f_external_bi');
putenv('DB_PASSWORD=W@2Tv5x9mY&D@J%srn!k');
putenv('DB_CHARSET=utf8mb4');
putenv('DB_COLLATION=utf8mb4_unicode_ci');

// Set email validation settings
putenv('SMTP_MAX_CONNECTIONS=3');
putenv('SMTP_MAX_CHECKS=50000');
putenv('SMTP_RATE_LIMIT_DELAY=1');
putenv('LOCAL_SMTP_VALIDATION=true');
putenv('LOCAL_SMTP_HOST=localhost');
putenv('LOCAL_SMTP_PORT=1025');
putenv('FROM_EMAIL=test@example.com');
putenv('FROM_NAME=Email Validator');

// Set batch processing settings
putenv('BATCH_SIZE=1000');
putenv('MAX_CONCURRENT=10');
putenv('ASYNC_CHUNK_SIZE=100');
putenv('ASYNC_TIMEOUT=30');
putenv('ASYNC_SLEEP_TIME=50000');
putenv('MEMORY_LIMIT=2G');
putenv('MAX_EXECUTION_TIME=3600');

echo "✅ Environment variables set successfully!\n";
echo "DB_HOST: " . getenv('DB_HOST') . "\n";
echo "DB_DATABASE: " . getenv('DB_DATABASE') . "\n";
echo "SMTP_MAX_CHECKS: " . getenv('SMTP_MAX_CHECKS') . "\n";
echo "MEMORY_LIMIT: " . getenv('MEMORY_LIMIT') . "\n\n";
