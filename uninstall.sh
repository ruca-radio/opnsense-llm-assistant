#!/bin/sh
#
# OPNsense LLM Assistant - Uninstallation Script
# Compatible with OPNsense/FreeBSD
#
# Run with: sudo sh uninstall.sh
#

set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Plugin information
PLUGIN_VENDOR="CognitiveSecurity"

# Installation paths
OPNSENSE_BASE="/usr/local"
MVC_PATH="${OPNSENSE_BASE}/opnsense/mvc/app"

# Global backup directory variable
BACKUP_DIR=""

echo_info() {
    echo "${GREEN}[INFO]${NC} $1"
}

echo_warn() {
    echo "${YELLOW}[WARN]${NC} $1"
}

echo_error() {
    echo "${RED}[ERROR]${NC} $1"
}

# Check if running as root
check_root() {
    if [ "$(id -u)" -ne 0 ]; then
        echo_error "This script must be run as root or with sudo"
        exit 1
    fi
}

# Confirm uninstallation
confirm_uninstall() {
    echo_warn "This will remove the LLM Assistant plugin from your system."
    echo_warn "Your configuration will be backed up but the plugin will be removed."
    echo ""
    printf "Are you sure you want to continue? (yes/no): "
    read -r response
    
    if [ "$response" != "yes" ]; then
        echo_info "Uninstallation cancelled"
        exit 0
    fi
}

# Backup configuration before removal
backup_config() {
    echo_info "Backing up configuration..."
    
    BACKUP_DIR="/root/llm-assistant-uninstall-backup-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "${BACKUP_DIR}"
    
    # Backup plugin directories if they exist
    if [ -d "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" ]; then
        mkdir -p "${BACKUP_DIR}/controllers"
        cp -r "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" "${BACKUP_DIR}/controllers/" 2>/dev/null || true
    fi
    if [ -d "${MVC_PATH}/models/${PLUGIN_VENDOR}" ]; then
        mkdir -p "${BACKUP_DIR}/models"
        cp -r "${MVC_PATH}/models/${PLUGIN_VENDOR}" "${BACKUP_DIR}/models/" 2>/dev/null || true
    fi
    if [ -d "${MVC_PATH}/library/${PLUGIN_VENDOR}" ]; then
        mkdir -p "${BACKUP_DIR}/library"
        cp -r "${MVC_PATH}/library/${PLUGIN_VENDOR}" "${BACKUP_DIR}/library/" 2>/dev/null || true
    fi
    if [ -d "${MVC_PATH}/views/${PLUGIN_VENDOR}" ]; then
        mkdir -p "${BACKUP_DIR}/views"
        cp -r "${MVC_PATH}/views/${PLUGIN_VENDOR}" "${BACKUP_DIR}/views/" 2>/dev/null || true
    fi
    
    # Backup logs
    if [ -d "/var/log/llm-assistant" ]; then
        mkdir -p "${BACKUP_DIR}/logs"
        cp -r /var/log/llm-assistant "${BACKUP_DIR}/logs/" 2>/dev/null || true
    fi
    
    # Backup OPNsense configuration if possible
    if [ -f "/conf/config.xml" ]; then
        cp /conf/config.xml "${BACKUP_DIR}/config.xml.backup" 2>/dev/null || true
    fi
    
    echo_info "Configuration backed up to: ${BACKUP_DIR}"
}

# Remove plugin files
remove_files() {
    echo_info "Removing plugin files..."
    
    # Remove controllers
    if [ -d "${MVC_PATH}/controllers/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        rm -rf "${MVC_PATH}/controllers/${PLUGIN_VENDOR}/LLMAssistant"
        echo_info "Removed controllers"
    fi
    
    # Remove models
    if [ -d "${MVC_PATH}/models/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        rm -rf "${MVC_PATH}/models/${PLUGIN_VENDOR}/LLMAssistant"
        echo_info "Removed models"
    fi
    
    # Remove libraries
    if [ -d "${MVC_PATH}/library/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        rm -rf "${MVC_PATH}/library/${PLUGIN_VENDOR}/LLMAssistant"
        echo_info "Removed libraries"
    fi
    
    # Remove views
    if [ -d "${MVC_PATH}/views/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        rm -rf "${MVC_PATH}/views/${PLUGIN_VENDOR}/LLMAssistant"
        echo_info "Removed views"
    fi
    
    # Remove dashboard widget
    if [ -f "${MVC_PATH}/views/OPNsense/Core/llm_assistant_widget.volt" ]; then
        rm -f "${MVC_PATH}/views/OPNsense/Core/llm_assistant_widget.volt"
    fi
    
    # Remove parent directories if empty
    [ -d "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" ] && \
        rmdir "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" 2>/dev/null || true
    [ -d "${MVC_PATH}/models/${PLUGIN_VENDOR}" ] && \
        rmdir "${MVC_PATH}/models/${PLUGIN_VENDOR}" 2>/dev/null || true
    [ -d "${MVC_PATH}/library/${PLUGIN_VENDOR}" ] && \
        rmdir "${MVC_PATH}/library/${PLUGIN_VENDOR}" 2>/dev/null || true
    [ -d "${MVC_PATH}/views/${PLUGIN_VENDOR}" ] && \
        rmdir "${MVC_PATH}/views/${PLUGIN_VENDOR}" 2>/dev/null || true
    
    echo_info "Plugin files removed"
}

# Remove runtime directories
remove_runtime_dirs() {
    echo_info "Removing runtime directories..."
    
    # Ask about log removal
    printf "Remove log files? (yes/no): "
    read -r response
    if [ "$response" = "yes" ]; then
        if [ -d "/var/log/llm-assistant" ]; then
            rm -rf /var/log/llm-assistant
            echo_info "Log files removed"
        fi
    else
        echo_info "Log files preserved"
    fi
    
    # Remove cache
    if [ -d "/var/cache/llm-assistant" ]; then
        rm -rf /var/cache/llm-assistant
        echo_info "Cache removed"
    fi
}

# Unregister plugin from OPNsense
unregister_plugin() {
    echo_info "Unregistering plugin from OPNsense..."
    
    # Clear model cache
    if [ -f "/usr/local/opnsense/mvc/script/clear_cache.php" ]; then
        php /usr/local/opnsense/mvc/script/clear_cache.php 2>/dev/null || \
            echo_warn "Could not clear model cache"
    fi
    
    # Restart web interface
    if command -v configctl >/dev/null 2>&1; then
        echo_info "Restarting web interface..."
        configctl webgui restart 2>/dev/null || \
            service nginx restart 2>/dev/null || \
            echo_warn "Could not restart web interface automatically. Please restart manually."
    else
        echo_warn "Please restart web interface manually: service nginx restart"
    fi
    
    echo_info "Plugin unregistered"
}

# Display post-uninstallation message
post_uninstall() {
    echo ""
    echo "${GREEN}========================================${NC}"
    echo "${GREEN}Uninstallation Complete!${NC}"
    echo "${GREEN}========================================${NC}"
    echo ""
    echo "The LLM Assistant plugin has been removed."
    echo ""
    echo "Backup location:"
    echo "  Configuration and logs backed up to: ${BACKUP_DIR}"
    echo ""
    echo "To restore:"
    echo "  If you need to restore the plugin, you can:"
    echo "  1. Run the install.sh script again"
    echo "  2. Manually restore configuration from backup if needed"
    echo ""
    echo "Note:"
    echo "  - Your OPNsense configuration has been preserved"
    echo "  - The plugin entry in config.xml may need manual removal if needed"
    echo "  - Refresh your browser to see changes"
    echo ""
}

# Main uninstallation flow
main() {
    echo ""
    echo "========================================="
    echo "OPNsense LLM Assistant - Uninstaller"
    echo "========================================="
    echo ""
    
    check_root
    confirm_uninstall
    backup_config
    remove_files
    remove_runtime_dirs
    unregister_plugin
    post_uninstall
    
    echo "${GREEN}Uninstallation completed successfully!${NC}"
    echo ""
}

# Run main uninstallation
main
