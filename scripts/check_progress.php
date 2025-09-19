<?php

// Check progress of email validation
$progressFile = 'progress.json';

if (!file_exists($progressFile)) {
    echo "❌ No progress file found. Validation might not be running.\n";
    exit(1);
}

$progress = json_decode(file_get_contents($progressFile), true);

echo "📊 Email Validation Progress\n";
echo "============================\n\n";

echo "📦 Batch: {$progress['batch']} / {$progress['total_batches']}\n";
echo "📧 Processed: " . number_format($progress['processed']) . " emails\n";
echo "✅ Valid: " . number_format($progress['valid']) . " emails\n";
echo "❌ Invalid: " . number_format($progress['invalid']) . " emails\n";
echo "⏱️  Elapsed time: " . round($progress['elapsed_time'] / 60, 1) . " minutes\n";
echo "🕐 Estimated remaining: " . $progress['estimated_remaining'] . " minutes\n";
echo "📅 Last updated: " . $progress['last_updated'] . "\n\n";

$percentage = round(($progress['batch'] / $progress['total_batches']) * 100, 1);
echo "📈 Progress: {$percentage}%\n";

// Progress bar
$barLength = 50;
$filledLength = round(($progress['batch'] / $progress['total_batches']) * $barLength);
$bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
echo "[" . $bar . "] {$percentage}%\n";
