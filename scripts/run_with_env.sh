#!/bin/bash

# Set environment variables for production
export DB_HOST="192.168.142.41"
export DB_PORT="3307"
export DB_DATABASE="iwinback_baj7f"
export DB_USERNAME="baj7f_external_bi"
export DB_PASSWORD="W@2Tv5x9mY&D@J%srn!k"
export DB_CHARSET="utf8mb4"
export DB_COLLATION="utf8mb4_unicode_ci"

# Set email validation settings
export SMTP_MAX_CONNECTIONS="3"
export SMTP_MAX_CHECKS="50000"
export SMTP_RATE_LIMIT_DELAY="1"
export LOCAL_SMTP_VALIDATION="true"
export LOCAL_SMTP_HOST="localhost"
export LOCAL_SMTP_PORT="1025"
export FROM_EMAIL="test@example.com"
export FROM_NAME="Email Validator"

# Set batch processing settings
export BATCH_SIZE="1000"
export MAX_CONCURRENT="10"
export ASYNC_CHUNK_SIZE="100"
export ASYNC_TIMEOUT="30"
export ASYNC_SLEEP_TIME="50000"
export MEMORY_LIMIT="2G"
export MAX_EXECUTION_TIME="3600"

# Run the script passed as argument
php "$@"
