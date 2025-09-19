<?php

// Suppress deprecated warnings from Illuminate
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('log_errors', 0);
ini_set('display_errors', 0);

require_once __DIR__ . "/../vendor/autoload.php";

use App\ConfigManager;
use App\DataManager;
use App\QueryManager;

// Load configuration
$config = ConfigManager::load();

// Initialize managers
$dataManager = new DataManager($config);
$queryManager = new QueryManager();

echo "🔍 Query Information\n";
echo "===================\n\n";

// Show .env configuration
echo "📋 Environment Configuration:\n";
echo "  DATA_SOURCE: " . ConfigManager::getEnv('DATA_SOURCE', 'database') . "\n";
echo "  QUERY_TYPE: " . ConfigManager::getEnv('QUERY_TYPE', 'valid_emails') . "\n";
echo "  QUERY_LIMIT: " . ConfigManager::getEnv('QUERY_LIMIT', '0') . "\n";
echo "  QUERY_OFFSET: " . ConfigManager::getEnv('QUERY_OFFSET', '0') . "\n\n";

if ($dataManager->isUsingDatabase()) {
    echo "🗄️  Database Configuration:\n";
    echo "  DB_HOST: " . ConfigManager::getEnv('DB_HOST', 'localhost') . "\n";
    echo "  DB_DATABASE: " . ConfigManager::getEnv('DB_DATABASE', 'your_database') . "\n";
    echo "  DB_USERNAME: " . ConfigManager::getEnv('DB_USERNAME', 'your_username') . "\n\n";
    
    echo "📊 Query Details:\n";
    $queryInfo = $queryManager->getQueryInfo();
    echo "  Type: " . $queryInfo['type'] . "\n";
    echo "  Description: " . $queryInfo['description'] . "\n";
    echo "  Query: " . $queryInfo['query'] . "\n";
    echo "  Count Query: " . $queryInfo['count_query'] . "\n";
    echo "  Limit: " . ($queryInfo['limit'] > 0 ? $queryInfo['limit'] : 'No limit') . "\n";
    echo "  Offset: " . $queryInfo['offset'] . "\n\n";
    
    // Test connection
    echo "🔌 Testing database connection...\n";
    try {
        $totalEmails = $dataManager->countEmails();
        echo "  ✅ Connection successful!\n";
        echo "  📧 Total emails found: {$totalEmails}\n\n";
    } catch (\Exception $e) {
        echo "  ❌ Connection failed: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "📄 JSON File Configuration:\n";
    echo "  JSON_FILE_PATH: " . ConfigManager::getJsonFilePath() . "\n\n";
    
    // Test JSON file
    echo "🔌 Testing JSON file...\n";
    try {
        $totalEmails = $dataManager->countEmails();
        echo "  ✅ JSON file accessible!\n";
        echo "  📧 Total emails found: {$totalEmails}\n\n";
    } catch (\Exception $e) {
        echo "  ❌ JSON file error: " . $e->getMessage() . "\n\n";
    }
}

echo "📝 Available Query Types:\n";
echo "  valid_emails  - Only valid emails (default)\n";
echo "  all_emails    - All emails regardless of status\n";
echo "  invalid_emails - Only invalid emails\n";
echo "  custom        - Custom SQL query\n\n";

echo "⚙️  To change query type, update .env file:\n";
echo "  QUERY_TYPE=valid_emails\n";
echo "  QUERY_LIMIT=100\n";
echo "  QUERY_OFFSET=0\n\n";

echo "✨ Query information displayed successfully!\n";
