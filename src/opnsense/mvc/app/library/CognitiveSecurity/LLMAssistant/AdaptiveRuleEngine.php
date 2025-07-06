<?php

namespace CognitiveSecurity\LLMAssistant;

use OPNsense\Core\Config;

/**
 * Adaptive rule engine with continuous learning
 * NEVER applies rules automatically - always requires human approval
 */
class AdaptiveRuleEngine
{
    private $sandboxPath = '/var/llm_assistant/rule_sandbox/';
    private $learningDb = '/var/llm_assistant/learning.db';
    private $orchestrator;
    
    public function __construct()
    {
        $this->orchestrator = new Api\OrchestrationService();
        $this->initializeSandbox();
    }
    
    /**
     * Parse natural language to firewall rule (PREVIEW ONLY)
     */
    public function parseNaturalLanguageRule($description)
    {
        // First, use Cypher to extract basic parameters
        $extractPrompt = "Extract firewall rule parameters from: '$description'. " .
                        "Return: action (block/pass), source, destination, port, protocol. " .
                        "If not specified, use 'any'. Format: key=value pairs.";
        
        $extraction = $this->orchestrator->processQuery($extractPrompt, ['task' => 'rule_extraction']);
        
        // Parse extraction
        $params = $this->parseExtraction($extraction['response'] ?? '');
        
        // Use frontier model to validate and enhance
        $validatePrompt = "Review this firewall rule for security best practices:\n" .
                         "Description: $description\n" .
                         "Parsed: " . json_encode($params) . "\n" .
                         "Provide: 1) Security assessment 2) Suggested improvements 3) Warnings";
        
        $validation = $this->orchestrator->processQuery($validatePrompt, ['task' => 'rule_validation']);
        
        // Generate rule preview
        $rule = $this->generateRuleXml($params);
        
        return [
            'description' => $description,
            'parameters' => $params,
            'rule_xml' => $rule,
            'validation' => $validation['response'] ?? '',
            'requires_approval' => true,
            'sandbox_test' => $this->testInSandbox($rule)
        ];
    }
    
    /**
     * Generate adaptive suggestions based on current traffic
     */
    public function generateAdaptiveSuggestions()
    {
        $suggestions = [];
        
        // Analyze recent blocks
        $recentBlocks = $this->analyzeRecentBlocks();
        if (!empty($recentBlocks['repeated_offenders'])) {
            foreach ($recentBlocks['repeated_offenders'] as $offender) {
                $suggestions[] = [
                    'type' => 'block_persistent',
                    'description' => "Block persistent attacker {$offender['ip']}",
                    'reason' => "{$offender['count']} blocked attempts in last hour",
                    'rule_preview' => $this->generateBlockRule($offender['ip']),
                    'confidence' => $this->calculateConfidence($offender),
                    'learn_from_decision' => true
                ];
            }
        }
        
        // Analyze allowed traffic patterns
        $trafficPatterns = $this->analyzeTrafficPatterns();
        foreach ($trafficPatterns['unusual'] as $pattern) {
            $suggestions[] = [
                'type' => 'review_unusual',
                'description' => "Review unusual traffic to {$pattern['destination']}",
                'reason' => "New traffic pattern detected: {$pattern['description']}",
                'action' => 'investigate',
                'confidence' => 0.6,
                'learn_from_decision' => true
            ];
        }
        
        // Check for optimization opportunities
        $optimizations = $this->findRuleOptimizations();
        foreach ($optimizations as $opt) {
            $suggestions[] = [
                'type' => 'optimize',
                'description' => $opt['description'],
                'reason' => $opt['reason'],
                'current_rules' => $opt['current'],
                'suggested_rule' => $opt['suggested'],
                'confidence' => $opt['confidence'],
                'learn_from_decision' => true
            ];
        }
        
        return $this->prioritizeSuggestions($suggestions);
    }
    
    /**
     * Learn from user decisions
     */
    public function learnFromDecision($suggestionId, $decision, $result = null)
    {
        $db = new \SQLite3($this->learningDb);
        
        // Record decision
        $stmt = $db->prepare('
            INSERT INTO rule_decisions (
                suggestion_id, suggestion_type, decision, 
                result, timestamp, context
            ) VALUES (
                :id, :type, :decision, 
                :result, :time, :context
            )
        ');
        
        $stmt->bindValue(':id', $suggestionId);
        $stmt->bindValue(':type', $this->getSuggestionType($suggestionId));
        $stmt->bindValue(':decision', $decision); // accepted, rejected, modified
        $stmt->bindValue(':result', $result); // effective, ineffective, null
        $stmt->bindValue(':time', time());
        $stmt->bindValue(':context', json_encode($this->getCurrentContext()));
        
        $stmt->execute();
        
        // Update confidence scores
        $this->updateConfidenceScores($suggestionId, $decision, $result);
        
        // If rejected, learn why
        if ($decision === 'rejected') {
            $this->analyzeRejection($suggestionId);
        }
        
        $db->close();
    }
    
    /**
     * Test rule in sandbox environment
     */
    private function testInSandbox($ruleXml)
    {
        $testResults = [
            'syntax_valid' => $this->validateRuleSyntax($ruleXml),
            'conflicts' => $this->checkRuleConflicts($ruleXml),
            'performance_impact' => $this->estimatePerformanceImpact($ruleXml),
            'security_score' => $this->calculateSecurityScore($ruleXml)
        ];
        
        // Simulate rule application
        $sandboxFile = $this->sandboxPath . 'test_' . time() . '.xml';
        file_put_contents($sandboxFile, $ruleXml);
        
        // Run validation
        $validation = shell_exec("xmllint --noout " . escapeshellarg($sandboxFile) . " 2>&1");
        $testResults['xml_valid'] = empty($validation);
        
        // Clean up
        unlink($sandboxFile);
        
        return $testResults;
    }
    
    /**
     * Analyze recent blocked traffic
     */
    private function analyzeRecentBlocks()
    {
        $blocks = [];
        $offenders = [];
        
        // Parse recent firewall logs
        $logs = shell_exec('clog /var/log/filter/latest.log | grep "block" | tail -n 1000');
        $lines = explode("\n", $logs);
        
        foreach ($lines as $line) {
            if (preg_match('/block.*?(\d+\.\d+\.\d+\.\d+).*?(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
                $srcIp = $matches[1];
                $offenders[$srcIp] = ($offenders[$srcIp] ?? 0) + 1;
            }
        }
        
        // Find repeated offenders
        $repeated = [];
        foreach ($offenders as $ip => $count) {
            if ($count > 10) { // More than 10 attempts
                $repeated[] = [
                    'ip' => $ip,
                    'count' => $count,
                    'threat_level' => $this->assessThreatLevel($ip, $count)
                ];
            }
        }
        
        return ['repeated_offenders' => $repeated];
    }
    
    /**
     * Analyze traffic patterns for anomalies
     */
    private function analyzeTrafficPatterns()
    {
        // This would implement sophisticated pattern analysis
        // For now, simplified version
        $patterns = [
            'unusual' => [],
            'normal' => []
        ];
        
        // Check for new destination ports
        $recentPorts = $this->getRecentDestinationPorts();
        $historicalPorts = $this->getHistoricalPorts();
        
        foreach ($recentPorts as $port => $count) {
            if (!isset($historicalPorts[$port]) && $count > 5) {
                $patterns['unusual'][] = [
                    'destination' => "port $port",
                    'description' => "New service detected on port $port",
                    'count' => $count
                ];
            }
        }
        
        return $patterns;
    }
    
    /**
     * Find rule optimization opportunities
     */
    private function findRuleOptimizations()
    {
        $optimizations = [];
        $config = Config::getInstance()->object();
        
        if (!isset($config->filter->rule)) {
            return $optimizations;
        }
        
        // Look for redundant rules
        $rules = [];
        foreach ($config->filter->rule as $rule) {
            $key = $this->getRuleKey($rule);
            if (isset($rules[$key])) {
                $optimizations[] = [
                    'description' => 'Consolidate duplicate rules',
                    'reason' => 'Multiple rules with same source/destination/port',
                    'current' => [$rules[$key], $rule],
                    'suggested' => $this->consolidateRules([$rules[$key], $rule]),
                    'confidence' => 0.9
                ];
            }
            $rules[$key] = $rule;
        }
        
        // Look for rules that could use aliases
        $ipCounts = [];
        foreach ($config->filter->rule as $rule) {
            if (isset($rule->source->address)) {
                $addr = (string)$rule->source->address;
                $ipCounts[$addr] = ($ipCounts[$addr] ?? 0) + 1;
            }
        }
        
        foreach ($ipCounts as $ip => $count) {
            if ($count > 3 && $this->isIpAddress($ip)) {
                $optimizations[] = [
                    'description' => "Create alias for frequently used IP $ip",
                    'reason' => "IP $ip appears in $count rules",
                    'current' => $ip,
                    'suggested' => "Create alias 'host_" . str_replace('.', '_', $ip) . "'",
                    'confidence' => 0.8
                ];
            }
        }
        
        return $optimizations;
    }
    
    /**
     * Calculate confidence score for suggestion
     */
    private function calculateConfidence($data)
    {
        $confidence = 0.5; // Base confidence
        
        // Adjust based on historical decisions
        $db = new \SQLite3($this->learningDb);
        $stmt = $db->prepare('
            SELECT decision, COUNT(*) as count 
            FROM rule_decisions 
            WHERE suggestion_type = :type 
            GROUP BY decision
        ');
        $stmt->bindValue(':type', $data['type'] ?? 'unknown');
        $result = $stmt->execute();
        
        $accepted = 0;
        $rejected = 0;
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($row['decision'] === 'accepted') {
                $accepted = $row['count'];
            } elseif ($row['decision'] === 'rejected') {
                $rejected = $row['count'];
            }
        }
        
        if ($accepted + $rejected > 0) {
            $confidence = $accepted / ($accepted + $rejected);
        }
        
        // Adjust based on threat level
        if (isset($data['threat_level'])) {
            $confidence += ($data['threat_level'] - 5) * 0.1;
        }
        
        // Adjust based on count/frequency
        if (isset($data['count'])) {
            $confidence += min($data['count'] / 100, 0.2);
        }
        
        $db->close();
        
        return max(0, min(1, $confidence)); // Clamp between 0 and 1
    }
    
    /**
     * Prioritize suggestions based on importance and confidence
     */
    private function prioritizeSuggestions($suggestions)
    {
        usort($suggestions, function($a, $b) {
            // Priority factors
            $aPriority = $this->calculatePriority($a);
            $bPriority = $this->calculatePriority($b);
            
            return $bPriority <=> $aPriority;
        });
        
        // Return top 5 suggestions
        return array_slice($suggestions, 0, 5);
    }
    
    /**
     * Calculate priority score for suggestion
     */
    private function calculatePriority($suggestion)
    {
        $priority = $suggestion['confidence'] ?? 0.5;
        
        // Boost security-related suggestions
        if ($suggestion['type'] === 'block_persistent') {
            $priority *= 1.5;
        }
        
        // Boost based on threat level
        if (isset($suggestion['threat_level'])) {
            $priority *= (1 + $suggestion['threat_level'] / 10);
        }
        
        // Reduce priority for optimizations
        if ($suggestion['type'] === 'optimize') {
            $priority *= 0.7;
        }
        
        return $priority;
    }
    
    /**
     * Helper methods
     */
    private function initializeSandbox()
    {
        if (!is_dir($this->sandboxPath)) {
            mkdir($this->sandboxPath, 0700, true);
        }
    }
    
    private function parseExtraction($response)
    {
        $params = [
            'action' => 'block',
            'source' => 'any',
            'destination' => 'any',
            'port' => 'any',
            'protocol' => 'any'
        ];
        
        // Simple key=value parser
        if (preg_match_all('/(\w+)=([^\s,]+)/', $response, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $key = strtolower($matches[1][$i]);
                $value = $matches[2][$i];
                if (isset($params[$key])) {
                    $params[$key] = $value;
                }
            }
        }
        
        return $params;
    }
    
    private function generateRuleXml($params)
    {
        $xml = '<rule>';
        $xml .= '<type>' . ($params['action'] === 'block' ? 'block' : 'pass') . '</type>';
        $xml .= '<interface>wan</interface>';
        $xml .= '<source><any>1</any></source>';
        $xml .= '<destination>';
        
        if ($params['destination'] !== 'any') {
            $xml .= '<address>' . htmlspecialchars($params['destination']) . '</address>';
        } else {
            $xml .= '<any>1</any>';
        }
        
        if ($params['port'] !== 'any') {
            $xml .= '<port>' . htmlspecialchars($params['port']) . '</port>';
        }
        
        $xml .= '</destination>';
        
        if ($params['protocol'] !== 'any') {
            $xml .= '<protocol>' . htmlspecialchars($params['protocol']) . '</protocol>';
        }
        
        $xml .= '<descr>AI-suggested rule (pending approval)</descr>';
        $xml .= '</rule>';
        
        return $xml;
    }
    
    private function generateBlockRule($ip)
    {
        return [
            'action' => 'block',
            'source' => $ip,
            'destination' => 'any',
            'interface' => 'wan',
            'description' => "Block persistent attacker $ip"
        ];
    }
    
    private function isIpAddress($str)
    {
        return filter_var($str, FILTER_VALIDATE_IP) !== false;
    }
    
    private function getRuleKey($rule)
    {
        $src = isset($rule->source->address) ? (string)$rule->source->address : 'any';
        $dst = isset($rule->destination->address) ? (string)$rule->destination->address : 'any';
        $port = isset($rule->destination->port) ? (string)$rule->destination->port : 'any';
        
        return md5($src . '|' . $dst . '|' . $port);
    }
}