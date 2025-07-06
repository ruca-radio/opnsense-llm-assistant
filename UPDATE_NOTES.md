# LLM Assistant v2.0 - AI Integration Update

## What's New

### 🎯 Dashboard Widget
- Natural language chat directly from OPNsense dashboard
- Ask questions in plain English
- Get instant responses with actionable buttons
- Continuous learning from interactions

### 🧠 Multi-Model Orchestration
- **Cypher Alpha (Free)**: Handles routine queries, log analysis, basic questions
- **Frontier Models (GPT-4/Claude)**: Complex decisions, security analysis, rule creation
- Automatic routing based on query complexity
- Cost-optimized model selection

### 📚 Continuous Learning System
- Learns from every interaction
- Tracks user preferences and decisions
- Improves suggestions over time
- SQLite-based learning database
- Pattern recognition for common tasks

### 🛡️ Adaptive Rule Engine
- Natural language to firewall rules
- "Block all traffic from China" → Proper rule XML
- Sandbox testing before deployment
- Security validation by frontier model
- ALWAYS requires human approval

### 🔄 Feedback Loop
- Learn from accepted/rejected suggestions
- Adapt confidence scores
- Remember what works for your network
- Personalized recommendations

## How It Works

1. **Simple Query → Cypher Alpha**
   - "What's my WAN IP?"
   - "Show recent blocks"
   - "List active connections"

2. **Complex Query → Frontier Model**
   - "Create a DMZ for my web server"
   - "Analyze this attack pattern"
   - "Design a security policy for..."

3. **Learning Process**
   - Every query is recorded
   - User feedback updates confidence
   - Patterns emerge over time
   - Suggestions improve with use

## Security Safeguards

- ✅ No automatic rule changes
- ✅ All suggestions require approval
- ✅ Sandbox testing for rules
- ✅ Audit logging of all interactions
- ✅ Rate limiting on API calls
- ✅ Human always in control

## Configuration

Add to your settings:
- `frontier_model`: Your premium model (e.g., "anthropic/claude-3.5-sonnet")
- `free_model`: Already set to "openrouter/cypher-alpha:free"

## Usage Examples

**Dashboard Widget:**
- "Block port 8080 from external networks"
- "Why is 192.168.1.100 being blocked?"
- "Create a rule to allow SSH from my office"
- "Check my firewall for security issues"

**Natural Language Rules:**
- "Allow HTTPS from DMZ to internet"
- "Block all torrent traffic"
- "Create strict rules for IoT devices"

**Learning Queries:**
- "What firewall rules affect my web server?"
- "Explain my NAT configuration"
- "How do I set up a VPN?"

## Technical Details

- Orchestration service routes queries intelligently
- Learning database tracks patterns and preferences
- Adaptive rule engine with syntax validation
- Widget integrates seamlessly with dashboard
- All through OpenRouter API

## Coming Next

- Rule change predictions
- Anomaly detection alerts
- Traffic pattern learning
- Automated report scheduling
- Integration with threat feeds

Remember: The AI learns and adapts, but never acts without your permission!