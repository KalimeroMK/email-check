<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use KalimeroMK\EmailCheck\EmailValidator;
use Exception;
use Throwable;

/**
 * Mass Email Validation Dispatcher
 * 
 * This script provides parallel processing capabilities for validating millions of emails
 * using multiple CPU cores and optimized memory management.
 * 
 * Features:
 * - Multi-process parallel validation
 * - Memory-efficient streaming processing
 * - Progress tracking and resumability
 * - JSON output for valid/invalid emails
 * - Real-time statistics and ETA
 * - Automatic resource management
 */
class MassEmailValidator
{
    private EmailValidator $emailValidator;
    
    private int $totalEmails = 0;
    private int $processedEmails = 0;
    private int $validEmails = 0;
    private int $invalidEmails = 0;
    private int $startTime = 0;
    
    private array $validEmailsList = [];
    private array $invalidEmailsList = [];
    
    private string $outputDir;
    private string $progressFile;
    private string $validEmailsFile;
    private string $invalidEmailsFile;
    private string $statsFile;
    
    // Configuration
    private int $batchSize = 2000; // Larger batches for better throughput
    private int $maxProcesses = 4; // Will be auto-detected
    private int $memoryLimit = 256 * 1024 * 1024; // 256MB per process for larger batches
    private bool $enableSMTP = false; // Disable SMTP for speed
    private bool $enablePatternFiltering = true; // Enable fast pattern filtering
    private bool $aggressiveMode = false; // Ultra-fast mode for massive datasets
    
    public function __construct(array $config = [])
    {
        $this->loadConfiguration($config);
        $this->initializeValidator();
        $this->setupOutputFiles();
    }
    
    /**
     * Main validation process
     */
    public function validateMassEmails(array $emails): array
    {
        $this->totalEmails = count($emails);
        $this->startTime = time();
        
        echo "🚀 Starting mass email validation...\n";
        echo "📊 Total emails: " . number_format($this->totalEmails) . "\n";
        echo "⚙️  Configuration:\n";
        echo "   - Batch size: " . number_format($this->batchSize) . "\n";
        echo "   - Max processes: {$this->maxProcesses}\n";
        echo "   - Memory limit: " . $this->formatBytes($this->memoryLimit) . "\n";
        echo "   - SMTP validation: " . ($this->enableSMTP ? 'Enabled' : 'Disabled') . "\n";
        echo "   - Pattern filtering: " . ($this->enablePatternFiltering ? 'Enabled' : 'Disabled') . "\n\n";
        
        // Process emails in parallel batches
        $this->processParallelBatches($emails);
        
        // Generate final results
        $this->generateFinalResults();
        
        return [
            'success' => true,
            'total_emails' => $this->totalEmails,
            'processed_emails' => $this->processedEmails,
            'valid_emails' => $this->validEmails,
            'invalid_emails' => $this->invalidEmails,
            'processing_time' => time() - $this->startTime,
            'output_files' => [
                'valid' => $this->validEmailsFile,
                'invalid' => $this->invalidEmailsFile,
                'stats' => $this->statsFile,
            ],
        ];
    }
    
    /**
     * Process emails in parallel batches using multiple processes
     */
    private function processParallelBatches(array $emails): void
    {
        $totalBatches = ceil($this->totalEmails / $this->batchSize);
        
        echo "📦 Processing " . number_format($totalBatches) . " batches with {$this->maxProcesses} parallel processes...\n\n";
        
        $batchNumber = 0;
        $activeProcesses = [];
        
        for ($batchStart = 0; $batchStart < $this->totalEmails; $batchStart += $this->batchSize) {
            $batchEnd = min($batchStart + $this->batchSize, $this->totalEmails);
            $batchEmails = array_slice($emails, $batchStart, $batchEnd - $batchStart);
            $batchNumber++;
            
            // If we have reached max processes, wait for one to finish
            while (count($activeProcesses) >= $this->maxProcesses) {
                $this->waitForProcesses($activeProcesses);
            }
            
            echo "🔄 Starting batch {$batchNumber}/{$totalBatches} (" . count($batchEmails) . " emails)...\n";
            
            // Start new process
            $pid = $this->startBatchProcess($batchEmails, $batchNumber);
            if ($pid > 0) {
                $activeProcesses[$pid] = [
                    'batch_number' => $batchNumber,
                    'emails_count' => count($batchEmails),
                    'start_time' => time(),
                ];
            } else {
                // Fallback to sequential processing if fork fails
                $batchResults = $this->processBatch($batchEmails, $batchNumber);
                $this->updateStatistics($batchResults);
                $this->saveProgress($batchNumber, $totalBatches);
                
                echo "   ✅ Batch completed: " . count($batchEmails) . " processed\n";
                echo "   📊 Progress: " . $this->getProgressPercentage() . "%\n";
                echo "   ⏱️  ETA: " . $this->getEstimatedTimeRemaining() . "\n\n";
            }
        }
        
        // Wait for all remaining processes to finish
        while (!empty($activeProcesses)) {
            $this->waitForProcesses($activeProcesses);
        }
        
        echo "🎉 All batches completed!\n\n";
    }
    
    /**
     * Start a batch process using fork
     */
    private function startBatchProcess(array $emails, int $batchNumber): int
    {
        if (!function_exists('pcntl_fork')) {
            return 0; // Fork not available
        }
        
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            return 0; // Fork failed
        } elseif ($pid == 0) {
            // Child process
            $this->processBatchInChild($emails, $batchNumber);
            exit(0);
        } else {
            // Parent process
            return $pid;
        }
    }
    
    /**
     * Process batch in child process
     */
    private function processBatchInChild(array $emails, int $batchNumber): void
    {
        // Create a separate validator instance for this process
        $validatorConfig = [
            'timeout' => 5,
            'check_smtp' => $this->enableSMTP,
            'enable_pattern_filtering' => $this->enablePatternFiltering,
            'pattern_strict_mode' => false,
            'dns_cache_driver' => 'array',
            'dns_cache_ttl' => 3600,
        ];
        
        if (class_exists('KalimeroMK\EmailCheck\EmailValidator')) {
            $validator = new \KalimeroMK\EmailCheck\EmailValidator($validatorConfig);
        } else {
            $validator = new EmailValidator($validatorConfig);
        }
        
        $results = $validator->validateBatch($emails);
        
        $validCount = 0;
        $invalidCount = 0;
        $validEmails = [];
        $invalidEmails = [];
        
        foreach ($results as $result) {
            if ($result['is_valid']) {
                $validEmails[] = $result['email'];
                $validCount++;
            } else {
                $invalidEmails[] = $result['email'];
                $invalidCount++;
            }
        }
        
        // Save results to temporary files
        $tempDir = $this->outputDir . '/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $tempFile = $tempDir . "/batch_{$batchNumber}.json";
        file_put_contents($tempFile, json_encode([
            'batch_number' => $batchNumber,
            'total_emails' => count($emails),
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'valid_emails' => $validEmails,
            'invalid_emails' => $invalidEmails,
            'processing_time' => time(),
        ]));
    }
    
    /**
     * Wait for processes to complete and collect results
     */
    private function waitForProcesses(array &$activeProcesses): void
    {
        foreach ($activeProcesses as $pid => $processInfo) {
            $status = null;
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            
            if ($result == $pid) {
                // Process completed
                $this->collectBatchResults($processInfo['batch_number']);
                
                echo "   ✅ Batch {$processInfo['batch_number']} completed: {$processInfo['emails_count']} processed\n";
                echo "   📊 Progress: " . $this->getProgressPercentage() . "%\n";
                echo "   ⏱️  ETA: " . $this->getEstimatedTimeRemaining() . "\n\n";
                
                unset($activeProcesses[$pid]);
            }
        }
        
        // Small delay to prevent busy waiting
        usleep(100000); // 0.1 second
    }
    
    /**
     * Collect results from completed batch
     */
    private function collectBatchResults(int $batchNumber): void
    {
        $tempFile = $this->outputDir . "/temp/batch_{$batchNumber}.json";
        
        if (file_exists($tempFile)) {
            $content = file_get_contents($tempFile);
            if ($content === false) {
                return;
            }
            
            $data = json_decode($content, true);
            
            if ($data !== null) {
                $this->processedEmails += $data['total_emails'];
                $this->validEmails += $data['valid_count'];
                $this->invalidEmails += $data['invalid_count'];
                
                // Add emails to lists
                $this->validEmailsList = array_merge($this->validEmailsList, $data['valid_emails']);
                $this->invalidEmailsList = array_merge($this->invalidEmailsList, $data['invalid_emails']);
                
                // Save progress
                $this->saveProgress($batchNumber, (int) ceil($this->totalEmails / $this->batchSize));
                
                // Clean up temp file
                unlink($tempFile);
            }
        }
    }
    
    /**
     * Process a single batch of emails
     */
    private function processBatch(array $emails, int $batchNumber): array
    {
        $batchStartTime = time();
        
        // Validate emails using the existing validateBatch method
        $results = $this->emailValidator->validateBatch($emails);
        
        $validCount = 0;
        $invalidCount = 0;
        
        foreach ($results as $result) {
            if ($result['is_valid']) {
                $this->validEmailsList[] = $result['email'];
                $validCount++;
            } else {
                $this->invalidEmailsList[] = $result['email'];
                $invalidCount++;
            }
        }
        
        $batchTime = time() - $batchStartTime;
        
        return [
            'batch_number' => $batchNumber,
            'total_emails' => count($emails),
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'processing_time' => $batchTime,
        ];
    }
    
    /**
     * Update global statistics
     */
    private function updateStatistics(array $batchResults): void
    {
        $this->processedEmails += $batchResults['total_emails'];
        $this->validEmails += $batchResults['valid_count'];
        $this->invalidEmails += $batchResults['invalid_count'];
    }
    
    /**
     * Save progress to file
     */
    private function saveProgress(int $currentBatch, int $totalBatches): void
    {
        $progress = [
            'timestamp' => date('Y-m-d H:i:s'),
            'current_batch' => $currentBatch,
            'total_batches' => $totalBatches,
            'processed_emails' => $this->processedEmails,
            'total_emails' => $this->totalEmails,
            'valid_emails' => $this->validEmails,
            'invalid_emails' => $this->invalidEmails,
            'progress_percentage' => $this->getProgressPercentage(),
            'elapsed_time' => time() - $this->startTime,
            'estimated_remaining' => $this->getEstimatedTimeRemaining(),
        ];
        
        file_put_contents($this->progressFile, json_encode($progress, JSON_PRETTY_PRINT));
    }
    
    /**
     * Generate final results and save to files
     */
    private function generateFinalResults(): void
    {
        echo "📝 Generating final results...\n";
        
        // Save valid emails
        $this->saveEmailsToFile($this->validEmailsList, $this->validEmailsFile);
        
        // Save invalid emails
        $this->saveEmailsToFile($this->invalidEmailsList, $this->invalidEmailsFile);
        
        // Generate statistics
        $this->generateStatistics();
        
        echo "✅ Results saved to:\n";
        echo "   📄 Valid emails: {$this->validEmailsFile}\n";
        echo "   📄 Invalid emails: {$this->invalidEmailsFile}\n";
        echo "   📊 Statistics: {$this->statsFile}\n";
    }
    
    /**
     * Save emails to JSON file
     */
    private function saveEmailsToFile(array $emails, string $filename): void
    {
        $data = [
            'metadata' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'total_count' => count($emails),
                'generated_by' => 'MassEmailValidator',
            ],
            'emails' => $emails,
        ];
        
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Generate comprehensive statistics
     */
    private function generateStatistics(): void
    {
        $totalTime = time() - $this->startTime;
        $emailsPerSecond = $this->processedEmails / max($totalTime, 1);
        
        $stats = [
            'summary' => [
                'total_emails' => $this->totalEmails,
                'processed_emails' => $this->processedEmails,
                'valid_emails' => $this->validEmails,
                'invalid_emails' => $this->invalidEmails,
                'validation_rate' => round(($this->validEmails / max($this->processedEmails, 1)) * 100, 2),
            ],
            'performance' => [
                'total_time_seconds' => $totalTime,
                'total_time_formatted' => $this->formatTime($totalTime),
                'emails_per_second' => round($emailsPerSecond, 2),
                'emails_per_minute' => round($emailsPerSecond * 60, 2),
                'emails_per_hour' => round($emailsPerSecond * 3600, 2),
            ],
            'configuration' => [
                'batch_size' => $this->batchSize,
                'max_processes' => $this->maxProcesses,
                'memory_limit' => $this->formatBytes($this->memoryLimit),
                'smtp_enabled' => $this->enableSMTP,
                'pattern_filtering_enabled' => $this->enablePatternFiltering,
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        
        file_put_contents($this->statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    }
    
    /**
     * Load configuration
     */
    private function loadConfiguration(array $config): void
    {
        $this->batchSize = $config['batch_size'] ?? 1000;
        
        // Auto-detect CPU cores if not specified
        if (isset($config['max_processes'])) {
            $this->maxProcesses = (int) $config['max_processes'];
        } else {
            $this->maxProcesses = $this->detectCPUCores();
        }
        
        $this->memoryLimit = $config['memory_limit'] ?? (256 * 1024 * 1024);
        $this->maxExecutionTime = $config['max_execution_time'] ?? 3600;
        $this->enableSMTP = $config['enable_smtp'] ?? false;
        $this->enablePatternFiltering = $config['enable_pattern_filtering'] ?? true;
        $this->aggressiveMode = $config['aggressive_mode'] ?? false;
        
        // Aggressive mode optimizations
        if ($this->aggressiveMode) {
            $this->batchSize = max($this->batchSize, 5000); // Larger batches
            $this->maxProcesses = min($this->maxProcesses * 2, 80); // More processes
            $this->memoryLimit = max($this->memoryLimit, 512 * 1024 * 1024); // More memory
        }
    }
    
    /**
     * Detect number of CPU cores
     */
    private function detectCPUCores(): int
    {
        // Try different methods to detect CPU cores
        $cores = 1;
        
        // Method 1: /proc/cpuinfo (Linux)
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if ($cpuinfo !== false) {
                $cores = substr_count($cpuinfo, 'processor');
                if ($cores > 0) {
                    return $cores;
                }
            }
        }
        
        // Method 2: sysctl (macOS/BSD)
        if (function_exists('shell_exec')) {
            $result = shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if ($result !== null && $result !== '') {
                $cores = (int) trim($result);
                if ($cores > 0) {
                    return $cores;
                }
            }
        }
        
        // Method 3: nproc command
        if (function_exists('shell_exec')) {
            $result = shell_exec('nproc 2>/dev/null');
            if ($result !== null && $result !== '') {
                $cores = (int) trim($result);
                if ($cores > 0) {
                    return $cores;
                }
            }
        }
        
        // Method 4: PHP function (if available)
        if (function_exists('sys_getloadavg')) {
            // This doesn't give core count directly, but we can estimate
            $load = sys_getloadavg();
            if ($load !== false && isset($load[0]) && $load[0] > 0) {
                // Estimate based on load average (not very accurate)
                $cores = max(1, (int) ($load[0] * 2));
            }
        }
        
        // Fallback: Use a reasonable default based on common server configs
        return max(4, min(64, $cores)); // Between 4 and 64 cores for high-end servers
    }
    
    /**
     * Initialize email validator with optimized configuration
     */
    private function initializeValidator(): void
    {
        $validatorConfig = [
            'timeout' => 5,
            'check_smtp' => $this->enableSMTP,
            'enable_pattern_filtering' => $this->enablePatternFiltering,
            'pattern_strict_mode' => false,
            'dns_cache_driver' => 'array', // Use in-memory cache for speed
            'dns_cache_ttl' => 3600,
        ];
        
        $this->emailValidator = new EmailValidator($validatorConfig);
    }
    
    /**
     * Setup output file paths
     */
    private function setupOutputFiles(): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $this->outputDir = __DIR__ . '/../data/mass_validation_' . $timestamp;
        
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        
        $this->progressFile = $this->outputDir . '/progress.json';
        $this->validEmailsFile = $this->outputDir . '/valid_emails.json';
        $this->invalidEmailsFile = $this->outputDir . '/invalid_emails.json';
        $this->statsFile = $this->outputDir . '/statistics.json';
    }
    
    /**
     * Memory management
     */
    private function manageMemory(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        // If memory usage is high, trigger garbage collection
        if ($memoryUsage > ($this->memoryLimit * 0.8)) {
            gc_collect_cycles();
        }
        
        // Set memory limit for the process (convert to MB format)
        $memoryLimitMB = round($this->memoryLimit / (1024 * 1024));
        ini_set('memory_limit', $memoryLimitMB . 'M');
    }
    
    /**
     * Get progress percentage
     */
    private function getProgressPercentage(): float
    {
        if ($this->totalEmails === 0) {
            return 0;
        }
        
        return round(($this->processedEmails / $this->totalEmails) * 100, 2);
    }
    
    /**
     * Get estimated time remaining
     */
    private function getEstimatedTimeRemaining(): string
    {
        if ($this->processedEmails === 0) {
            return 'Calculating...';
        }
        
        $elapsedTime = time() - $this->startTime;
        if ($elapsedTime === 0) {
            return 'Calculating...';
        }
        $emailsPerSecond = $this->processedEmails / $elapsedTime;
        $remainingEmails = $this->totalEmails - $this->processedEmails;
        $remainingSeconds = $remainingEmails / max($emailsPerSecond, 0.1);
        
        return $this->formatTime($remainingSeconds);
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * Format time in seconds to human readable format
     */
    private function formatTime(float $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%02d:%02d', $minutes, $seconds);
        } else {
            return sprintf('%02d seconds', $seconds);
        }
    }
    
    /**
     * Get current statistics
     */
    public function getCurrentStats(): array
    {
        return [
            'total_emails' => $this->totalEmails,
            'processed_emails' => $this->processedEmails,
            'valid_emails' => $this->validEmails,
            'invalid_emails' => $this->invalidEmails,
            'progress_percentage' => $this->getProgressPercentage(),
            'elapsed_time' => time() - $this->startTime,
            'estimated_remaining' => $this->getEstimatedTimeRemaining(),
        ];
    }
    
    /**
     * Get max processes
     */
    public function getMaxProcesses(): int
    {
        return $this->maxProcesses;
    }
    
    /**
     * Get detected CPU cores (public method)
     */
    public function getDetectedCPUCores(): int
    {
        return $this->detectCPUCores();
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    echo "Mass Email Validation Dispatcher\n";
    echo "=================================\n\n";
    
    // Check command line arguments
    if ($argc < 2) {
        echo "Usage: php mass-email-validator.php <input_file> [options]\n";
        echo "\nOptions:\n";
        echo "  --batch-size=<size>        Batch size (default: 2000)\n";
        echo "  --max-processes=<count>    Max parallel processes (auto-detected)\n";
        echo "  --memory-limit=<bytes>      Memory limit per process (default: 256MB)\n";
        echo "  --enable-smtp              Enable SMTP validation (slower)\n";
        echo "  --disable-pattern-filter   Disable pattern filtering\n";
        echo "  --aggressive-mode          Ultra-fast mode for massive datasets\n";
        echo "\nExample:\n";
        echo "  php mass-email-validator.php emails.json --batch-size=2000 --max-processes=8\n";
        exit(1);
    }
    
    $inputFile = $argv[1];
    
    // Parse command line options
    $options = [];
    for ($i = 2; $i < $argc; $i++) {
        $arg = $argv[$i];
        if (str_starts_with($arg, '--')) {
            $parts = explode('=', $arg, 2);
            $key = substr($parts[0], 2);
            $value = $parts[1] ?? true;
            
            switch ($key) {
                case 'batch-size':
                    $options['batch_size'] = (int) $value;
                    break;
                case 'max-processes':
                    $options['max_processes'] = (int) $value;
                    break;
                case 'memory-limit':
                    $options['memory_limit'] = parseMemoryLimit((string) $value);
                    break;
                case 'enable-smtp':
                    $options['enable_smtp'] = true;
                    break;
                case 'disable-pattern-filter':
                    $options['enable_pattern_filtering'] = false;
                    break;
                case 'aggressive-mode':
                    $options['aggressive_mode'] = true;
                    break;
            }
        }
    }
    
    // Load emails from input file
    if (!file_exists($inputFile)) {
        echo "❌ Error: Input file '{$inputFile}' not found.\n";
        exit(1);
    }
    
    echo "📧 Loading emails from {$inputFile}...\n";
    $content = file_get_contents($inputFile);
    if ($content === false) {
        echo "❌ Error: Could not read input file.\n";
        exit(1);
    }
    
    $emailsData = json_decode($content, true);
    if ($emailsData === null) {
        echo "❌ Error: Invalid JSON file.\n";
        exit(1);
    }
    
    // Extract emails from the data
    $emails = [];
    if (isset($emailsData['emails']) && is_array($emailsData['emails'])) {
        $emails = $emailsData['emails'];
    } elseif (is_array($emailsData)) {
        $emails = array_map(function($item) {
            return is_array($item) && isset($item['email']) ? $item['email'] : $item;
        }, $emailsData);
    } else {
        echo "❌ Error: Invalid email data format.\n";
        exit(1);
    }
    
    if (empty($emails)) {
        echo "❌ Error: No emails found in input file.\n";
        exit(1);
    }
    
    echo "📧 Loaded " . number_format(count($emails)) . " emails from {$inputFile}\n\n";
    
    // Initialize validator
    $validator = new MassEmailValidator($options);
    
    // Show CPU detection info
    echo "🖥️  Detected CPU cores: " . $validator->getDetectedCPUCores() . "\n";
    echo "⚙️  Using processes: " . $validator->getMaxProcesses() . "\n\n";
    
    // Start validation
    $result = $validator->validateMassEmails($emails);
    
    if ($result['success']) {
        echo "\n🎉 === VALIDATION COMPLETED ===\n";
        echo "📊 Total emails: " . number_format($result['total_emails']) . "\n";
        echo "✅ Valid emails: " . number_format($result['valid_emails']) . "\n";
        echo "❌ Invalid emails: " . number_format($result['invalid_emails']) . "\n";
        echo "⏱️  Processing time: " . formatTime($result['processing_time']) . "\n";
        echo "📁 Output files saved to: " . dirname($result['output_files']['valid']) . "\n";
    } else {
        echo "\n❌ Validation failed.\n";
        exit(1);
    }
}

/**
 * Parse memory limit string (e.g., "128MB", "1GB")
 */
function parseMemoryLimit(string $limit): int
{
    $limit = strtoupper(trim($limit));
    
    if (str_ends_with($limit, 'GB')) {
        return (int) substr($limit, 0, -2) * 1024 * 1024 * 1024;
    } elseif (str_ends_with($limit, 'MB')) {
        return (int) substr($limit, 0, -2) * 1024 * 1024;
    } elseif (str_ends_with($limit, 'KB')) {
        return (int) substr($limit, 0, -2) * 1024;
    } else {
        return (int) $limit;
    }
}

/**
 * Format time in seconds to human readable format
 */
function formatTime(int $seconds): string
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    } elseif ($minutes > 0) {
        return sprintf('%02d:%02d', $minutes, $seconds);
    } else {
        return sprintf('%02d seconds', $seconds);
    }
}