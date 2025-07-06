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

1. Download the plugin package
2. Install via OPNsense package manager:
   ```
   pkg install os-llm-assistant
   ```
3. Navigate to Interfaces → LLM Assistant
4. Configure your API provider and key
5. Enable desired features

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