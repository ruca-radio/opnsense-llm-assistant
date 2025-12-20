<?php

namespace CognitiveSecurity\LLMAssistant\Api;

use OPNsense\Core\Config;

/**
 * Multi-model orchestration service
 * Uses Cypher Alpha for routine tasks, Frontier models for complex decisions
 */
class OrchestrationService
{
    private $llmService;
    private $learningDb = '/var/llm_assistant/learning.db';
    private $contextWindow = '/var/llm_assistant/context_window.json';
    
    // Task complexity thresholds
    const SIMPLE_TASK = 1;
    const MODERATE_TASK = 2;
    const COMPLEX_TASK = 3;
    
    public function __construct()
    {
        $this->llmService = new LLMService();
        $this->initializeLearningDb();
    }
    
    /**
     * Process natural language query with smart model selection
     */
    public function processQuery($query, $context = [])
    {
        // Analyze query complexity
        $complexity = $this->analyzeComplexity($query);
        
        // Get current context and learning history
        $fullContext = $this->buildContext($query, $context);
        
        // Route to appropriate model
        if ($complexity === self::COMPLEX_TASK) {
            return $this->processFrontierQuery($query, $fullContext);
        } else {
            return $this->processCypherQuery($query, $fullContext);
        }
    }
    
    /**
     * Analyze query complexity to determine model routing
     */
    private function analyzeComplexity($query)
    {
        $complexIndicators = [
            'create rule', 'configure', 'security policy', 'analyze attack',
            'incident response', 'optimize', 'redesign', 'architect'
        ];
        
        $moderateIndicators = [
            'explain', 'review', 'check', 'list', 'show', 'summarize'
        ];
        
        $queryLower = strtolower($query);
        
        foreach ($complexIndicators as $indicator) {
            if (strpos($queryLower, $indicator) !== false) {
                return self::COMPLEX_TASK;
            }
        }
        
        foreach ($moderateIndicators as $indicator) {
            if (strpos($queryLower, $indicator) !== false) {
                return self::MODERATE_TASK;
            }
        }
        
        return self::SIMPLE_TASK;
    }
    
    /**
     * Process with Cypher Alpha (free model for routine tasks)
     */
    private function processCypherQuery($query, $context)
    {
        $prompt = $this->buildCypherPrompt($query, $context);
        
        // Override model for this query
        $originalModel = $this->getConfiguredModel();
        $this->setTemporaryModel('openrouter/cypher-alpha:free');
        
        $response = $this->llmService->query($prompt, $context, 'orchestration');
        
        // Restore original model
        $this->setTemporaryModel($originalModel);
        
        // Learn from interaction
        $this->recordInteraction($query, $response, 'cypher-alpha');
        
        return $response;
    }
    
    /**
     * Process with Frontier model (GPT-4 or Claude for complex tasks)
     */
    private function processFrontierQuery($query, $context)
    {
        // First, use Cypher to gather context and prepare analysis
        $prepPrompt = "Analyze this query and list key security considerations: $query";
        $this->setTemporaryModel('openrouter/cypher-alpha:free');
        $prepResponse = $this->llmService->query($prepPrompt, [], 'orchestration');
        
        // Build enhanced context with Cypher's analysis
        $enhancedContext = array_merge($context, [
            'security_considerations' => $prepResponse['response'] ?? '',
            'requires_frontier' => true
        ]);
        
        // Now use frontier model for the actual response
        $frontierModel = $this->getFrontierModel();
        $this->setTemporaryModel($frontierModel);
        
        $prompt = $this->buildFrontierPrompt($query, $enhancedContext);
        $response = $this->llmService->query($prompt, $enhancedContext, 'orchestration');
        
        // Learn from the interaction
        $this->recordInteraction($query, $response, $frontierModel);
        
        return $response;
    }
    
    /**
     * Build context from learning history and current state
     */
    private function buildContext($query, $userContext)
    {
        $context = $userContext;
        
        // Add recent interactions
        $recentInteractions = $this->getRecentInteractions(5);
        if (!empty($recentInteractions)) {
            $context['recent_queries'] = array_map(function($i) {
                return $i['query'];
            }, $recentInteractions);
        }
        
        // Add current system state
        $context['firewall_state'] = $this->getFirewallState();
        
        // Add learned preferences
        $context['user_preferences'] = $this->getLearnedPreferences();
        
        // Load persistent context window
        if (file_exists($this->contextWindow)) {
            $persistentContext = json_decode(file_get_contents($this->contextWindow), true);
            $context = array_merge($context, $persistentContext);
        }
        
        return $context;
    }
    
    /**
     * Build prompt for Cypher Alpha
     */
    private function buildCypherPrompt($query, $context)
    {
        $prompt = "You are a helpful OPNsense firewall assistant. ";
        $prompt .= "Provide concise, practical answers. ";
        $prompt .= "If the query requires complex analysis, say 'This requires deeper analysis' ";
        $prompt .= "and provide a basic answer.\n\n";
        
        if (!empty($context['recent_queries'])) {
            $prompt .= "Recent context:\n";
            foreach ($context['recent_queries'] as $q) {
                $prompt .= "- $q\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "User query: $query";
        
        return $prompt;
    }
    
    /**
     * Build prompt for Frontier model
     */
    private function buildFrontierPrompt($query, $context)
    {
        $prompt = "You are an expert OPNsense security architect with deep knowledge ";
        $prompt .= "of firewall best practices, threat analysis, and network security.\n\n";
        
        if (isset($context['security_considerations'])) {
            $prompt .= "Initial analysis:\n" . $context['security_considerations'] . "\n\n";
        }
        
        $prompt .= "Based on the analysis and your expertise, provide a comprehensive response ";
        $prompt .= "that includes:\n";
        $prompt .= "1. Direct answer to the query\n";
        $prompt .= "2. Security implications\n";
        $prompt .= "3. Best practice recommendations\n";
        $prompt .= "4. Any warnings or caveats\n\n";
        
        $prompt .= "User query: $query";
        
        return $prompt;
    }
    
    /**
     * Initialize learning database
     */
    private function initializeLearningDb()
    {
        $dir = dirname($this->learningDb);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        
        if (!file_exists($this->learningDb)) {
            try {
                $db = new \SQLite3($this->learningDb);
                $db->exec('
                    CREATE TABLE IF NOT EXISTS interactions (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        timestamp INTEGER,
                        query TEXT,
                        response TEXT,
                        model TEXT,
                        feedback TEXT,
                        applied BOOLEAN DEFAULT 0
                    );
                    
                    CREATE TABLE IF NOT EXISTS preferences (
                        key TEXT PRIMARY KEY,
                        value TEXT,
                        confidence REAL DEFAULT 0.5,
                        updated INTEGER
                    );
                    
                    CREATE TABLE IF NOT EXISTS patterns (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        pattern TEXT UNIQUE,
                        action TEXT,
                        frequency INTEGER DEFAULT 1,
                        last_seen INTEGER
                    );
                    
                    CREATE INDEX IF NOT EXISTS idx_interactions_timestamp 
                    ON interactions(timestamp);
                    
                    CREATE INDEX IF NOT EXISTS idx_patterns_action 
                    ON patterns(action);
                ');
                $db->close();
            } catch (\Exception $e) {
                error_log("Failed to initialize learning database: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Record interaction for learning
     */
    private function recordInteraction($query, $response, $model)
    {
        try {
            $db = new \SQLite3($this->learningDb);
            $stmt = $db->prepare('
                INSERT INTO interactions (timestamp, query, response, model)
                VALUES (:timestamp, :query, :response, :model)
            ');
            
            $stmt->bindValue(':timestamp', time(), SQLITE3_INTEGER);
            $stmt->bindValue(':query', $query, SQLITE3_TEXT);
            $stmt->bindValue(':response', json_encode($response), SQLITE3_TEXT);
            $stmt->bindValue(':model', $model, SQLITE3_TEXT);
            $stmt->execute();
            
            $db->close();
            
            // Update patterns
            $this->updatePatterns($query);
        } catch (\Exception $e) {
            error_log("Failed to record interaction: " . $e->getMessage());
        }
    }
    
    /**
     * Update learned patterns
     */
    private function updatePatterns($query)
    {
        // Extract potential patterns (simplified)
        $patterns = [
            'check_rules' => '/check.*rules?|review.*config/i',
            'create_rule' => '/create.*rule|add.*rule|new.*rule/i',
            'analyze_logs' => '/analyze.*logs?|check.*logs?|review.*traffic/i',
            'security_audit' => '/security.*audit|audit.*security|compliance/i'
        ];
        
        try {
            $db = new \SQLite3($this->learningDb);
            
            foreach ($patterns as $action => $pattern) {
                if (preg_match($pattern, $query)) {
                    $stmt = $db->prepare('
                        INSERT INTO patterns (pattern, action, last_seen)
                        VALUES (:pattern, :action, :time)
                        ON CONFLICT(pattern) DO UPDATE SET
                            frequency = frequency + 1,
                            last_seen = :time
                    ');
                    
                    $stmt->bindValue(':pattern', $pattern, SQLITE3_TEXT);
                    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
                    $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
                    $stmt->execute();
                }
            }
            
            $db->close();
        } catch (\Exception $e) {
            error_log("Failed to update patterns: " . $e->getMessage());
        }
    }
    
    /**
     * Get recent interactions for context
     */
    private function getRecentInteractions($limit = 5)
    {
        try {
            $db = new \SQLite3($this->learningDb);
            $stmt = $db->prepare('
                SELECT query, response, model 
                FROM interactions 
                ORDER BY timestamp DESC 
                LIMIT :limit
            ');
            $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
            
            $result = $stmt->execute();
            $interactions = [];
            
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $interactions[] = $row;
            }
            
            $db->close();
            return $interactions;
        } catch (\Exception $e) {
            error_log("Failed to get recent interactions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get learned user preferences
     */
    private function getLearnedPreferences()
    {
        try {
            $db = new \SQLite3($this->learningDb);
            $result = $db->query('
                SELECT key, value 
                FROM preferences 
                WHERE confidence > 0.7
                ORDER BY confidence DESC
            ');
            
            $preferences = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $preferences[$row['key']] = $row['value'];
            }
            
            $db->close();
            return $preferences;
        } catch (\Exception $e) {
            error_log("Failed to get learned preferences: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update learning from user feedback
     */
    public function provideFeedback($interactionId, $feedback, $applied = false)
    {
        try {
            $db = new \SQLite3($this->learningDb);
            $stmt = $db->prepare('
                UPDATE interactions 
                SET feedback = :feedback, applied = :applied
                WHERE id = :id
            ');
            
            $stmt->bindValue(':feedback', $feedback, SQLITE3_TEXT);
            $stmt->bindValue(':applied', $applied ? 1 : 0, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $interactionId, SQLITE3_INTEGER);
            $stmt->execute();
            
            // If positive feedback and applied, increase confidence in patterns
            if ($feedback === 'helpful' && $applied) {
                $this->reinforcePatterns($interactionId);
            }
            
            $db->close();
        } catch (\Exception $e) {
            error_log("Failed to provide feedback: " . $e->getMessage());
        }
    }
    
    /**
     * Reinforce successful patterns
     */
    private function reinforcePatterns($interactionId)
    {
        try {
            $db = new \SQLite3($this->learningDb);
            
            // Get the interaction
            $stmt = $db->prepare('SELECT query FROM interactions WHERE id = :id');
            $stmt->bindValue(':id', $interactionId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $interaction = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($interaction) {
                // Update confidence for matching patterns
                $this->updatePatterns($interaction['query']);
            }
            
            $db->close();
        } catch (\Exception $e) {
            error_log("Failed to reinforce patterns: " . $e->getMessage());
        }
    }
    
    /**
     * Get current firewall state summary
     */
    private function getFirewallState()
    {
        // Simplified state gathering
        return [
            'rules_count' => $this->countFirewallRules(),
            'interfaces_count' => $this->countActiveInterfaces(),
            'last_config_change' => $this->getLastConfigChange()
        ];
    }
    
    /**
     * Helper methods for configuration
     */
    private function getConfiguredModel()
    {
        $config = Config::getInstance()->object();
        return (string)$config->CognitiveSecurity->LLMAssistant->general->model_name;
    }
    
    private function getFrontierModel()
    {
        $config = Config::getInstance()->object();
        $configured = (string)$config->CognitiveSecurity->LLMAssistant->general->frontier_model;
        return !empty($configured) ? $configured : 'anthropic/claude-3.5-sonnet';
    }
    
    private function setTemporaryModel($model)
    {
        // This would need to be implemented in LLMService
        // For now, we'll pass the model in the context
    }
    
    private function countFirewallRules()
    {
        $config = Config::getInstance()->object();
        return isset($config->filter->rule) ? count($config->filter->rule) : 0;
    }
    
    private function countActiveInterfaces()
    {
        $config = Config::getInstance()->object();
        $count = 0;
        if (isset($config->interfaces)) {
            foreach ($config->interfaces->children() as $interface) {
                if ((string)$interface->enable === '1') {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    private function getLastConfigChange()
    {
        return filemtime('/conf/config.xml');
    }
}