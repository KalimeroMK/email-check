<?php

namespace KalimeroMK\EmailCheck\Scripts;

/**
 * Mass Validation Monitor
 * 
 * Monitors the progress of mass email validation processes
 */
class MassValidationMonitor
{
    private string $progressFile;
    private bool $isRunning = true;
    
    public function __construct(string $progressFile)
    {
        $this->progressFile = $progressFile;
    }
    
    /**
     * Start monitoring
     */
    public function startMonitoring(): void
    {
        echo "🔍 Starting mass validation monitor...\n";
        echo "📁 Monitoring: {$this->progressFile}\n\n";
        
        // Handle Ctrl+C gracefully
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
        
        $lastUpdate = 0;
        
        while ($this->isRunning) {
            if (file_exists($this->progressFile)) {
                $progress = $this->loadProgress();
                
                if ($progress && $progress['timestamp'] !== $lastUpdate) {
                    $this->displayProgress($progress);
                    $lastUpdate = $progress['timestamp'];
                    
                    // Check if completed
                    if ($progress['current_batch'] >= $progress['total_batches']) {
                        echo "\n🎉 Validation completed!\n";
                        break;
                    }
                }
            } else {
                echo "⏳ Waiting for progress file...\n";
            }
            
            sleep(5); // Check every 5 seconds
            
            // Handle signals
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }
    
    /**
     * Load progress from file
     */
    private function loadProgress(): ?array
    {
        $content = file_get_contents($this->progressFile);
        if ($content === false) {
            return null;
        }
        
        $progress = json_decode($content, true);
        return $progress ?: null;
    }
    
    /**
     * Display progress information
     */
    private function displayProgress(array $progress): void
    {
        // Clear screen (works on most terminals)
        echo "\033[2J\033[H";
        
        echo "📊 Mass Email Validation Progress\n";
        echo "=================================\n\n";
        
        // Basic info
        echo "📧 Total emails: " . number_format($progress['total_emails']) . "\n";
        echo "📦 Current batch: {$progress['current_batch']}/{$progress['total_batches']}\n";
        echo "✅ Valid emails: " . number_format($progress['valid_emails']) . "\n";
        echo "❌ Invalid emails: " . number_format($progress['invalid_emails']) . "\n";
        echo "📈 Progress: {$progress['progress_percentage']}%\n\n";
        
        // Progress bar
        $this->displayProgressBar($progress['progress_percentage']);
        
        // Time info
        echo "\n⏱️  Elapsed time: " . $this->formatTime($progress['elapsed_time']) . "\n";
        echo "⏳ Estimated remaining: {$progress['estimated_remaining']}\n";
        
        // Statistics
        $emailsPerSecond = $progress['processed_emails'] / max($progress['elapsed_time'], 1);
        echo "🚀 Processing speed: " . round($emailsPerSecond, 2) . " emails/second\n";
        echo "📊 Validation rate: " . round(($progress['valid_emails'] / max($progress['processed_emails'], 1)) * 100, 2) . "%\n";
        
        echo "\n🕐 Last update: {$progress['timestamp']}\n";
        echo "Press Ctrl+C to stop monitoring\n";
    }
    
    /**
     * Display progress bar
     */
    private function displayProgressBar(float $percentage): void
    {
        $barLength = 50;
        $filledLength = (int) ($barLength * $percentage / 100);
        
        $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
        
        echo "Progress: [{$bar}] {$percentage}%\n";
    }
    
    /**
     * Format time in seconds to human readable format
     */
    private function formatTime(int $seconds): string
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
     * Handle system signals
     */
    public function handleSignal(int $signal): void
    {
        echo "\n\n👋 Monitoring stopped by user.\n";
        $this->isRunning = false;
    }
    
    /**
     * Get current progress summary
     */
    public function getProgressSummary(): ?array
    {
        $progress = $this->loadProgress();
        if (!$progress) {
            return null;
        }
        
        return [
            'total_emails' => $progress['total_emails'],
            'processed_emails' => $progress['processed_emails'],
            'valid_emails' => $progress['valid_emails'],
            'invalid_emails' => $progress['invalid_emails'],
            'progress_percentage' => $progress['progress_percentage'],
            'elapsed_time' => $progress['elapsed_time'],
            'estimated_remaining' => $progress['estimated_remaining'],
            'current_batch' => $progress['current_batch'],
            'total_batches' => $progress['total_batches'],
        ];
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    echo "Mass Validation Monitor\n";
    echo "======================\n\n";
    
    // Check command line arguments
    if ($argc < 2) {
        echo "Usage: php monitor-validation.php <progress_file>\n";
        echo "\nExample:\n";
        echo "  php monitor-validation.php ../data/mass_validation_2025-10-08_15-30-00/progress.json\n";
        echo "\nThe progress file is created by the mass email validator.\n";
        exit(1);
    }
    
    $progressFile = $argv[1];
    
    if (!file_exists($progressFile)) {
        echo "❌ Error: Progress file '{$progressFile}' not found.\n";
        echo "Make sure the mass email validator is running.\n";
        exit(1);
    }
    
    $monitor = new MassValidationMonitor($progressFile);
    $monitor->startMonitoring();
}
