#!/bin/sh
#
# OPNsense LLM Assistant - Installation Script
# Compatible with OPNsense/FreeBSD
#
# Run with: sudo sh install.sh
#

set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Plugin information
PLUGIN_NAME="llm-assistant"
PLUGIN_VENDOR="CognitiveSecurity"
PLUGIN_VERSION="1.0.0"

# Installation paths
OPNSENSE_BASE="/usr/local"
MVC_PATH="${OPNSENSE_BASE}/opnsense/mvc/app"
SRC_PATH="$(dirname "$(realpath "$0")")/src/opnsense/mvc/app"

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

# Check if running on FreeBSD/OPNsense
check_system() {
    echo_info "Checking system compatibility..."
    
    if [ "$(uname -s)" != "FreeBSD" ]; then
        echo_error "This script is designed for FreeBSD/OPNsense systems"
        exit 1
    fi
    
    if [ ! -d "/usr/local/opnsense" ]; then
        echo_warn "OPNsense installation not detected in standard location"
        echo_warn "Continuing anyway, but plugin may not function correctly"
    fi
    
    echo_info "System check passed"
}

# Check and install dependencies
install_dependencies() {
    echo_info "Checking dependencies..."
    
    # Check PHP
    if ! command -v php >/dev/null 2>&1; then
        echo_error "PHP is not installed. Please install PHP first."
        echo_error "Run: pkg install php81"
        exit 1
    fi
    
    PHP_VERSION=$(php -r 'echo PHP_VERSION;' 2>/dev/null)
    echo_info "PHP version: ${PHP_VERSION}"
    
    # Check for required PHP extensions
    REQUIRED_EXTENSIONS="curl json openssl"
    MISSING_EXTENSIONS=""
    
    for ext in $REQUIRED_EXTENSIONS; do
        if ! php -m 2>/dev/null | grep -qi "^${ext}$"; then
            MISSING_EXTENSIONS="${MISSING_EXTENSIONS} php81-${ext}"
        fi
    done
    
    if [ -n "$MISSING_EXTENSIONS" ]; then
        echo_warn "Missing PHP extensions:${MISSING_EXTENSIONS}"
        echo_info "Installing missing extensions..."
        pkg install -y $MISSING_EXTENSIONS || echo_warn "Some extensions could not be installed automatically"
    fi
    
    echo_info "Dependencies check complete"
}

# Backup existing configuration if present
backup_existing() {
    echo_info "Checking for existing installation..."
    
    if [ -d "${MVC_PATH}/controllers/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        BACKUP_DIR="/root/llm-assistant-backup-$(date +%Y%m%d-%H%M%S)"
        echo_warn "Existing installation found. Creating backup at ${BACKUP_DIR}"
        
        mkdir -p "${BACKUP_DIR}"
        
        [ -d "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" ] && \
            cp -r "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" "${BACKUP_DIR}/controllers/"
        [ -d "${MVC_PATH}/models/${PLUGIN_VENDOR}" ] && \
            cp -r "${MVC_PATH}/models/${PLUGIN_VENDOR}" "${BACKUP_DIR}/models/"
        [ -d "${MVC_PATH}/library/${PLUGIN_VENDOR}" ] && \
            cp -r "${MVC_PATH}/library/${PLUGIN_VENDOR}" "${BACKUP_DIR}/library/"
        [ -d "${MVC_PATH}/views/${PLUGIN_VENDOR}" ] && \
            cp -r "${MVC_PATH}/views/${PLUGIN_VENDOR}" "${BACKUP_DIR}/views/"
        
        echo_info "Backup created successfully"
    fi
}

# Install plugin files
install_files() {
    echo_info "Installing plugin files..."
    
    if [ ! -d "$SRC_PATH" ]; then
        echo_error "Source directory not found: ${SRC_PATH}"
        echo_error "Please run this script from the plugin root directory"
        exit 1
    fi
    
    # Create necessary directories
    echo_info "Creating directory structure..."
    mkdir -p "${MVC_PATH}/controllers/${PLUGIN_VENDOR}/LLMAssistant/Api"
    mkdir -p "${MVC_PATH}/controllers/${PLUGIN_VENDOR}/LLMAssistant/forms"
    mkdir -p "${MVC_PATH}/models/${PLUGIN_VENDOR}/LLMAssistant/Menu"
    mkdir -p "${MVC_PATH}/models/${PLUGIN_VENDOR}/LLMAssistant/ACL"
    mkdir -p "${MVC_PATH}/library/${PLUGIN_VENDOR}/LLMAssistant/Api"
    mkdir -p "${MVC_PATH}/views/${PLUGIN_VENDOR}/LLMAssistant"
    mkdir -p "${MVC_PATH}/views/OPNsense/Core"
    
    # Copy controllers
    echo_info "Installing controllers..."
    if [ -d "${SRC_PATH}/controllers/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        cp -r "${SRC_PATH}/controllers/${PLUGIN_VENDOR}/LLMAssistant/"* \
            "${MVC_PATH}/controllers/${PLUGIN_VENDOR}/LLMAssistant/" || {
            echo_error "Failed to copy controllers"
            exit 1
        }
    fi
    
    # Copy models
    echo_info "Installing models..."
    if [ -d "${SRC_PATH}/models/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        cp -r "${SRC_PATH}/models/${PLUGIN_VENDOR}/LLMAssistant/"* \
            "${MVC_PATH}/models/${PLUGIN_VENDOR}/LLMAssistant/" || {
            echo_error "Failed to copy models"
            exit 1
        }
    fi
    
    # Copy libraries
    echo_info "Installing libraries..."
    if [ -d "${SRC_PATH}/library/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        cp -r "${SRC_PATH}/library/${PLUGIN_VENDOR}/LLMAssistant/"* \
            "${MVC_PATH}/library/${PLUGIN_VENDOR}/LLMAssistant/" || {
            echo_error "Failed to copy libraries"
            exit 1
        }
    fi
    
    # Copy views
    echo_info "Installing views..."
    if [ -d "${SRC_PATH}/views/${PLUGIN_VENDOR}/LLMAssistant" ]; then
        cp -r "${SRC_PATH}/views/${PLUGIN_VENDOR}/LLMAssistant/"* \
            "${MVC_PATH}/views/${PLUGIN_VENDOR}/LLMAssistant/" || {
            echo_error "Failed to copy views"
            exit 1
        }
    fi
    
    # Copy dashboard widget if it exists
    if [ -f "${SRC_PATH}/views/OPNsense/Core/dashboard_widget.volt" ]; then
        cp "${SRC_PATH}/views/OPNsense/Core/dashboard_widget.volt" \
            "${MVC_PATH}/views/OPNsense/Core/llm_assistant_widget.volt" 2>/dev/null || true
    fi
    
    echo_info "Plugin files installed successfully"
}

# Set proper permissions
set_permissions() {
    echo_info "Setting file permissions..."
    
    # Set ownership to root:wheel (standard for OPNsense)
    chown -R root:wheel "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" 2>/dev/null || true
    chown -R root:wheel "${MVC_PATH}/models/${PLUGIN_VENDOR}" 2>/dev/null || true
    chown -R root:wheel "${MVC_PATH}/library/${PLUGIN_VENDOR}" 2>/dev/null || true
    chown -R root:wheel "${MVC_PATH}/views/${PLUGIN_VENDOR}" 2>/dev/null || true
    
    # Set appropriate permissions
    find "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" -type f -exec chmod 644 {} \; 2>/dev/null || true
    find "${MVC_PATH}/models/${PLUGIN_VENDOR}" -type f -exec chmod 644 {} \; 2>/dev/null || true
    find "${MVC_PATH}/library/${PLUGIN_VENDOR}" -type f -exec chmod 644 {} \; 2>/dev/null || true
    find "${MVC_PATH}/views/${PLUGIN_VENDOR}" -type f -exec chmod 644 {} \; 2>/dev/null || true
    
    find "${MVC_PATH}/controllers/${PLUGIN_VENDOR}" -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "${MVC_PATH}/models/${PLUGIN_VENDOR}" -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "${MVC_PATH}/library/${PLUGIN_VENDOR}" -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "${MVC_PATH}/views/${PLUGIN_VENDOR}" -type d -exec chmod 755 {} \; 2>/dev/null || true
    
    echo_info "Permissions set successfully"
}

# Create necessary log and data directories
create_runtime_dirs() {
    echo_info "Creating runtime directories..."
    
    # Create log directory
    LOG_DIR="/var/log/llm-assistant"
    mkdir -p "${LOG_DIR}"
    chmod 755 "${LOG_DIR}"
    
    # Create cache directory
    CACHE_DIR="/var/cache/llm-assistant"
    mkdir -p "${CACHE_DIR}"
    chmod 755 "${CACHE_DIR}"
    
    echo_info "Runtime directories created"
}

# Register plugin with OPNsense
register_plugin() {
    echo_info "Registering plugin with OPNsense..."
    
    # Reload configd templates
    if command -v configctl >/dev/null 2>&1; then
        configctl template reload OPNsense/LLMAssistant 2>/dev/null || \
            echo_warn "Could not reload configd templates (may not be necessary)"
    fi
    
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
        echo_warn "configctl not found. Please restart web interface manually:"
        echo_warn "  service nginx restart"
    fi
    
    echo_info "Plugin registration complete"
}

# Display post-installation instructions
post_install() {
    echo ""
    echo "${GREEN}========================================${NC}"
    echo "${GREEN}Installation Complete!${NC}"
    echo "${GREEN}========================================${NC}"
    echo ""
    echo "Next steps:"
    echo "  1. Access OPNsense web interface"
    echo "  2. Navigate to: Interfaces → LLM Assistant"
    echo "  3. Configure your API provider and key"
    echo "  4. Enable desired features"
    echo ""
    echo "Configuration:"
    echo "  - Supported providers: OpenRouter, OpenAI, Anthropic, Local (Ollama)"
    echo "  - API keys are stored encrypted"
    echo "  - All LLM interactions are audit logged"
    echo ""
    echo "Logs:"
    echo "  - Plugin logs: /var/log/llm-assistant/"
    echo "  - Audit logs: Check OPNsense system logs"
    echo ""
    echo "Documentation:"
    echo "  - README: https://github.com/ruca-radio/opnsense-llm-assistant"
    echo ""
    echo "If the plugin doesn't appear:"
    echo "  - Refresh your browser (Ctrl+F5)"
    echo "  - Clear browser cache"
    echo "  - Restart web interface: configctl webgui restart"
    echo ""
}

# Main installation flow
main() {
    echo ""
    echo "========================================="
    echo "OPNsense LLM Assistant - Installer"
    echo "Version: ${PLUGIN_VERSION}"
    echo "========================================="
    echo ""
    
    check_root
    check_system
    install_dependencies
    backup_existing
    install_files
    set_permissions
    create_runtime_dirs
    register_plugin
    post_install
    
    echo "${GREEN}Installation completed successfully!${NC}"
    echo ""
}

# Run main installation
main
