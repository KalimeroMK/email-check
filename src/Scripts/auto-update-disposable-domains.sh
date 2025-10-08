#!/bin/bash

# Disposable Email Domains Auto-Update Script
# This script should be run via cron job to keep the disposable domains list up to date
# 
# Example cron job (run daily at 2 AM):
# 0 2 * * * /path/to/your/project/scripts/auto-update-disposable-domains.sh >> /var/log/disposable-domains-update.log 2>&1

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
UPDATE_SCRIPT="$SCRIPT_DIR/update-disposable-domains.php"
LOG_FILE="$PROJECT_DIR/logs/disposable-domains-update.log"
LOCK_FILE="/tmp/disposable-domains-update.lock"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Function to log errors
log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}" | tee -a "$LOG_FILE"
}

# Function to log success
log_success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1${NC}" | tee -a "$LOG_FILE"
}

# Function to log warnings
log_warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}" | tee -a "$LOG_FILE"
}

# Check if script is already running
if [ -f "$LOCK_FILE" ]; then
    PID=$(cat "$LOCK_FILE")
    if ps -p "$PID" > /dev/null 2>&1; then
        log_error "Update script is already running (PID: $PID)"
        exit 1
    else
        log_warning "Stale lock file found, removing it"
        rm -f "$LOCK_FILE"
    fi
fi

# Create lock file
echo $$ > "$LOCK_FILE"

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# Function to cleanup on exit
cleanup() {
    rm -f "$LOCK_FILE"
    log "Update script finished"
}

# Set trap to cleanup on exit
trap cleanup EXIT

# Check if PHP is available
if ! command -v php &> /dev/null; then
    log_error "PHP is not installed or not in PATH"
    exit 1
fi

# Check if update script exists
if [ ! -f "$UPDATE_SCRIPT" ]; then
    log_error "Update script not found: $UPDATE_SCRIPT"
    exit 1
fi

log "Starting disposable email domains update..."

# Change to project directory
cd "$PROJECT_DIR" || {
    log_error "Failed to change to project directory: $PROJECT_DIR"
    exit 1
}

# Run the update script
if php "$UPDATE_SCRIPT" >> "$LOG_FILE" 2>&1; then
    log_success "Disposable domains updated successfully"
    
    # Optional: Restart web server or clear cache if needed
    # Uncomment the following lines if you need to restart services after update
    
    # if command -v systemctl &> /dev/null; then
    #     log "Restarting web server..."
    #     sudo systemctl reload nginx 2>/dev/null || sudo systemctl reload apache2 2>/dev/null
    # fi
    
    # if [ -f "$PROJECT_DIR/artisan" ]; then
    #     log "Clearing Laravel cache..."
    #     php artisan cache:clear 2>/dev/null
    # fi
    
else
    log_error "Failed to update disposable domains"
    exit 1
fi

log_success "Update completed successfully"
exit 0
