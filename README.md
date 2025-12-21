# OPNsense LLM Security Assistant

A pragmatic AI-powered security assistant for OPNsense that augments human security teams rather than replacing them.

## Features

### ✅ Configuration Review
- Analyzes firewall rules for security issues
- Identifies overly permissive rules
- Detects potential misconfigurations
- Provides actionable recommendations

### ✅ Incident Report Generation
- Analyzes firewall logs for security events
- Detects port scans, brute force attempts, and DDoS patterns
- Generates executive summaries
- Provides prioritized recommendations

### ✅ Learning Mode
- Interactive Q&A about OPNsense configuration
- Security best practices guidance
- Context-aware assistance

### 🚧 Natural Language Rules (Experimental)
- Convert plain English to firewall rules
- Sandbox testing before deployment
- Human approval required

## Security Features

- **Rate Limiting**: Prevents API abuse
- **Audit Logging**: All LLM interactions logged
- **Encrypted Storage**: API keys stored securely
- **Human-in-the-loop**: All suggestions require approval
- **Sandboxing**: Rules tested before deployment

## Installation

### Automated Installation (Recommended)

The plugin is currently in active development and not yet available via the OPNsense package manager. Use the provided installation script:

1. Download or clone this repository:
   ```bash
   git clone https://github.com/ruca-radio/opnsense-llm-assistant.git
   cd opnsense-llm-assistant
   ```

2. Run the installation script as root:
   ```bash
   sudo sh install.sh
   ```

3. The script will:
   - Check system compatibility (FreeBSD/OPNsense)
   - Install required dependencies (PHP extensions)
   - Copy plugin files to the correct locations
   - Set appropriate permissions
   - Register the plugin with OPNsense
   - Restart the web interface

4. After installation:
   - Navigate to **Interfaces → LLM Assistant** in the OPNsense web interface
   - Configure your API provider and key
   - Enable desired features

### Manual Installation

If you prefer manual installation or the script fails:

1. Ensure PHP and required extensions are installed:
   ```bash
   pkg install php81 php81-curl php81-json php81-openssl
   ```

2. Copy plugin files to OPNsense directories:
   ```bash
   cp -r src/opnsense/mvc/app/* /usr/local/opnsense/mvc/app/
   ```

3. Set permissions:
   ```bash
   chown -R root:wheel /usr/local/opnsense/mvc/app/controllers/CognitiveSecurity
   chown -R root:wheel /usr/local/opnsense/mvc/app/models/CognitiveSecurity
   chown -R root:wheel /usr/local/opnsense/mvc/app/library/CognitiveSecurity
   chown -R root:wheel /usr/local/opnsense/mvc/app/views/CognitiveSecurity
   ```

4. Create runtime directories:
   ```bash
   mkdir -p /var/log/llm-assistant /var/cache/llm-assistant
   chmod 755 /var/log/llm-assistant /var/cache/llm-assistant
   ```

5. Restart the web interface:
   ```bash
   configctl webgui restart
   ```

### Uninstallation

To remove the plugin:

```bash
sudo sh uninstall.sh
```

The uninstall script will:
- Backup your configuration and logs
- Remove all plugin files
- Clean up runtime directories
- Unregister the plugin from OPNsense

## Configuration

### Supported Providers
- OpenRouter (recommended)
- OpenAI
- Anthropic
- Local models (Ollama)

### API Key Setup
1. Get an API key from your chosen provider
2. Enter in Settings tab
3. Select appropriate model for your use case
4. Adjust rate limits based on your plan

## Usage Guidelines

### DO:
- Use for configuration reviews during maintenance
- Generate reports for security audits
- Ask questions to understand configurations
- Review all AI suggestions critically

### DON'T:
- Enable automated actions without review
- Rely solely on AI for security decisions
- Share API keys or sensitive configs
- Ignore rate limits

## Architecture

The plugin follows OPNsense MVC architecture:
- Models for configuration management
- API controllers for backend logic
- Service classes for LLM integration
- Volt templates for UI

## Security Considerations

This plugin is designed with security first:
- No automatic rule changes
- All actions logged
- Rate limited to prevent abuse
- Suggestions only, not actions
- Local processing where possible

## Development

Built following the critical review from AI agents that concluded most "AI-powered" security features are dangerous. This plugin focuses on:
- Augmenting human analysts
- Reducing workload, not replacing judgment
- Practical features over hype
- Performance-conscious design

## License

BSD 2-Clause License

## Contributing

Issues and PRs welcome. Please follow OPNsense coding standards.

## Credits

Developed by CognitiveSecurity with input from security professionals who value practical tools over AI hype.