<?php

namespace KalimeroMK\EmailCheck\Data;

class ConfigManager
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @var array<string, string>|null */
    private static ?array $env = null;

    /**
     * Loads configuration from .env file and app.php
     */
    /** @return array<string, mixed> */
    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // Load .env file
        self::loadEnv();

        // Load base configuration
        $baseConfig = require __DIR__ . "/../config/app.php";

        // Update with .env values
        $config = self::mergeWithEnv($baseConfig);

        self::$config = $config;
        return $config;
    }

    /**
     * Loads .env file
     */
    private static function loadEnv(): void
    {
        if (self::$env !== null) {
            return;
        }

        $envFile = __DIR__ . "/../.env";
        if (!file_exists($envFile)) {
            self::$env = [];
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::$env = [];

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            // Ignore comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parse key=value pairs
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if they exist
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                self::$env[$key] = $value;
            }
        }
    }

    /**
     * Updates configuration with .env values
     */
    /** 
     * @param array<string, mixed> $baseConfig 
     * @return array<string, mixed>
     */
    private static function mergeWithEnv(array $baseConfig): array
    {
        $config = $baseConfig;

        // Database configuration
        if (isset(self::$env['DB_HOST'])) {
            $config['database']['host'] = self::$env['DB_HOST'];
        }

        if (isset(self::$env['DB_PORT'])) {
            $config['database']['port'] = (int)self::$env['DB_PORT'];
        }

        if (isset(self::$env['DB_DATABASE'])) {
            $config['database']['database'] = self::$env['DB_DATABASE'];
        }

        if (isset(self::$env['DB_USERNAME'])) {
            $config['database']['username'] = self::$env['DB_USERNAME'];
        }

        if (isset(self::$env['DB_PASSWORD'])) {
            $config['database']['password'] = self::$env['DB_PASSWORD'];
        }

        // Data source configuration
        if (isset(self::$env['DATA_SOURCE'])) {
            $config['data_source'] = self::$env['DATA_SOURCE'];
        }

        if (isset(self::$env['JSON_FILE_PATH'])) {
            $config['json_file_path'] = self::$env['JSON_FILE_PATH'];
        }

        // Validation configuration
        if (isset(self::$env['VALIDATION_METHOD'])) {
            $config['validation_method'] = self::$env['VALIDATION_METHOD'];
        }

        // Local SMTP configuration
        if (isset(self::$env['LOCAL_SMTP_HOST'])) {
            $config['settings']['local_smtp_host'] = self::$env['LOCAL_SMTP_HOST'];
        }

        if (isset(self::$env['LOCAL_SMTP_PORT'])) {
            $config['settings']['local_smtp_port'] = (int)self::$env['LOCAL_SMTP_PORT'];
        }

        // SMTP validation configuration
        if (isset(self::$env['CHECK_SMTP'])) {
            $config['check_smtp'] = filter_var(self::$env['CHECK_SMTP'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset(self::$env['SMTP_TIMEOUT'])) {
            $config['smtp_timeout'] = (int)self::$env['SMTP_TIMEOUT'];
        }

        if (isset(self::$env['SMTP_FROM_EMAIL'])) {
            $config['smtp_from_email'] = self::$env['SMTP_FROM_EMAIL'];
        }

        if (isset(self::$env['SMTP_FROM_NAME'])) {
            $config['smtp_from_name'] = self::$env['SMTP_FROM_NAME'];
        }

        // Batch processing configuration
        if (isset(self::$env['BATCH_SIZE'])) {
            $config['settings']['batch_size'] = (int)self::$env['BATCH_SIZE'];
        }

        if (isset(self::$env['MAX_CONCURRENT'])) {
            $config['settings']['max_concurrent'] = (int)self::$env['MAX_CONCURRENT'];
        }

        if (isset(self::$env['ASYNC_CHUNK_SIZE'])) {
            $config['settings']['async_chunk_size'] = (int)self::$env['ASYNC_CHUNK_SIZE'];
        }

        if (isset(self::$env['ASYNC_TIMEOUT'])) {
            $config['settings']['async_timeout'] = (int)self::$env['ASYNC_TIMEOUT'];
        }

        if (isset(self::$env['ASYNC_SLEEP_TIME'])) {
            $config['settings']['async_sleep_time'] = (int)self::$env['ASYNC_SLEEP_TIME'];
        }

        // Output configuration
        if (isset(self::$env['SAVE_RESULTS'])) {
            $config['save_results'] = filter_var(self::$env['SAVE_RESULTS'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset(self::$env['OUTPUT_DIR'])) {
            $config['output_dir'] = self::$env['OUTPUT_DIR'];
        }

        // Debug configuration
        if (isset(self::$env['DEBUG'])) {
            $config['debug'] = filter_var(self::$env['DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset(self::$env['VERBOSE'])) {
            $config['verbose'] = filter_var(self::$env['VERBOSE'], FILTER_VALIDATE_BOOLEAN);
        }

        return $config;
    }

    /**
     * Добива вредност од .env фајл
     */
    public static function getEnv(string $key, mixed $default = null): mixed
    {
        self::loadEnv();
        return self::$env[$key] ?? $default;
    }

    /**
     * Проверува дали користи база на податоци или JSON фајл
     */
    public static function useDatabase(): bool
    {
        $dataSource = self::getEnv('DATA_SOURCE', 'database');
        return $dataSource === 'database';
    }

    /**
     * Добива JSON фајл патека
     */
    public static function getJsonFilePath(): string
    {
        return self::getEnv('JSON_FILE_PATH', 'emails.json');
    }

    /**
     * Добива валидациски метод
     */
    public static function getValidationMethod(): string
    {
        return self::getEnv('VALIDATION_METHOD', 'advanced');
    }

    /**
     * Проверува дали е debug режим
     */
    public static function isDebug(): bool
    {
        return filter_var(self::getEnv('DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Проверува дали е verbose режим
     */
    public static function isVerbose(): bool
    {
        return filter_var(self::getEnv('VERBOSE', 'false'), FILTER_VALIDATE_BOOLEAN);
    }
}
