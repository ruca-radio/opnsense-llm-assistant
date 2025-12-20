<?php

namespace CognitiveSecurity\LLMAssistant\Api;

use OPNsense\Core\Config;

class LLMService
{
    private $model;
    private $rateLimiter;
    private $auditLogger;
    
    public function __construct()
    {
        $this->model = new \CognitiveSecurity\LLMAssistant\LLMAssistant();
        $this->rateLimiter = new RateLimiter();
        $this->auditLogger = new AuditLogger();
    }
    
    /**
     * Query the LLM with rate limiting and audit logging
     */
    public function query($prompt, $context = [], $feature = 'general')
    {
        // Check rate limit
        if (!$this->rateLimiter->checkLimit($this->model->getRateLimit())) {
            return ['error' => 'Rate limit exceeded. Please wait before trying again.'];
        }
        
        // Audit log the request
        $this->auditLogger->logRequest($feature, $prompt);
        
        try {
            $response = $this->sendRequest($prompt, $context);
            
            // Audit log the response
            $this->auditLogger->logResponse($feature, $response);
            
            return ['success' => true, 'response' => $response];
            
        } catch (\Exception $e) {
            $this->auditLogger->logError($feature, $e->getMessage());
            return ['error' => 'Failed to query LLM: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send request to LLM provider
     */
    private function sendRequest($prompt, $context)
    {
        $provider = (string)$this->model->general->api_provider;
        $apiKey = $this->model->getApiKey();
        $endpoint = (string)$this->model->general->api_endpoint;
        $modelName = (string)$this->model->general->model_name;
        $maxTokens = (int)$this->model->general->max_tokens;
        $temperature = (float)$this->model->general->temperature;
        
        // Build the full prompt with context
        $fullPrompt = $this->buildPrompt($prompt, $context);
        
        // Provider-specific request handling
        switch ($provider) {
            case 'openrouter':
                return $this->sendOpenRouterRequest($endpoint, $apiKey, $modelName, $fullPrompt, $maxTokens, $temperature);
            case 'openai':
                return $this->sendOpenAIRequest($endpoint, $apiKey, $modelName, $fullPrompt, $maxTokens, $temperature);
            case 'anthropic':
                return $this->sendAnthropicRequest($endpoint, $apiKey, $modelName, $fullPrompt, $maxTokens, $temperature);
            case 'local':
                return $this->sendLocalRequest($endpoint, $modelName, $fullPrompt, $maxTokens, $temperature);
            default:
                throw new \Exception("Unknown provider: $provider");
        }
    }
    
    /**
     * Build prompt with security context
     */
    private function buildPrompt($prompt, $context)
    {
        $systemPrompt = "You are a security assistant for OPNsense firewall. ";
        $systemPrompt .= "Provide clear, actionable advice. ";
        $systemPrompt .= "Always prioritize security best practices. ";
        $systemPrompt .= "Be concise and technically accurate.\n\n";
        
        if (!empty($context)) {
            $systemPrompt .= "Context:\n";
            foreach ($context as $key => $value) {
                $systemPrompt .= "$key: $value\n";
            }
            $systemPrompt .= "\n";
        }
        
        return $systemPrompt . "User Query: " . $prompt;
    }
    
    /**
     * Send request to OpenRouter
     */
    private function sendOpenRouterRequest($endpoint, $apiKey, $model, $prompt, $maxTokens, $temperature)
    {
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: https://opnsense.local',
            'X-Title: OPNsense LLM Assistant'
        ];
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ];
        
        return $this->curlRequest($endpoint . '/chat/completions', $headers, $data);
    }
    
    /**
     * Send request to OpenAI
     */
    private function sendOpenAIRequest($endpoint, $apiKey, $model, $prompt, $maxTokens, $temperature)
    {
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ];
        
        $url = $endpoint ?: 'https://api.openai.com/v1';
        return $this->curlRequest($url . '/chat/completions', $headers, $data);
    }
    
    /**
     * Send request to Anthropic
     */
    private function sendAnthropicRequest($endpoint, $apiKey, $model, $prompt, $maxTokens, $temperature)
    {
        $headers = [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ];
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature
        ];
        
        $url = $endpoint ?: 'https://api.anthropic.com/v1';
        $response = $this->curlRequest($url . '/messages', $headers, $data);
        
        // Anthropic has different response format
        if (isset($response['content']) && is_array($response['content'])) {
            return $response['content'][0]['text'] ?? 
                   throw new \Exception("Unexpected Anthropic response format");
        }
        
        throw new \Exception("Invalid Anthropic response");
    }
    
    /**
     * Send request to local model (Ollama)
     */
    private function sendLocalRequest($endpoint, $model, $prompt, $maxTokens, $temperature)
    {
        $headers = [
            'Content-Type: application/json'
        ];
        
        $data = [
            'model' => $model,
            'prompt' => $prompt,
            'options' => [
                'num_predict' => $maxTokens,
                'temperature' => $temperature
            ],
            'stream' => false
        ];
        
        $url = $endpoint ?: 'http://localhost:11434';
        $result = $this->curlRequest($url . '/api/generate', $headers, $data);
        
        return $result['response'] ?? 
               throw new \Exception("Unexpected local model response format");
    }
    
    /**
     * Generic cURL request handler
     */
    private function curlRequest($url, $headers, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception("cURL error: " . $error);
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("HTTP error $httpCode: " . $result);
        }
        
        $response = json_decode($result, true);
        if (!$response) {
            throw new \Exception("Invalid JSON response");
        }
        
        return $response['choices'][0]['message']['content'] ?? 
               throw new \Exception("Unexpected response format");
    }
}

/**
 * Simple rate limiter using file-based storage
 */
class RateLimiter
{
    private $storageFile = '/tmp/llm_rate_limit.json';
    private $lockFile = '/tmp/llm_rate_limit.lock';
    
    public function checkLimit($maxPerMinute)
    {
        $now = time();
        $minute = floor($now / 60);
        
        // Use file locking to prevent race conditions
        $lockFp = fopen($this->lockFile, 'c');
        if (!$lockFp) {
            // If we can't get a lock, fail open (allow request)
            return true;
        }
        
        if (!flock($lockFp, LOCK_EX)) {
            fclose($lockFp);
            return true;
        }
        
        $data = $this->loadData();
        
        // Clean old entries
        foreach ($data as $min => $count) {
            if ($min < $minute - 1) {
                unset($data[$min]);
            }
        }
        
        // Check current minute
        $currentCount = $data[$minute] ?? 0;
        if ($currentCount >= $maxPerMinute) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            return false;
        }
        
        // Increment and save
        $data[$minute] = $currentCount + 1;
        $this->saveData($data);
        
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
        
        return true;
    }
    
    private function loadData()
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }
        
        $content = @file_get_contents($this->storageFile);
        if ($content === false) {
            return [];
        }
        
        $data = @json_decode($content, true);
        return is_array($data) ? $data : [];
    }
    
    private function saveData($data)
    {
        @file_put_contents($this->storageFile, json_encode($data), LOCK_EX);
    }
}

/**
 * Audit logger for LLM interactions
 */
class AuditLogger
{
    private $logFile = '/var/log/llm_assistant_audit.log';
    
    public function logRequest($feature, $prompt)
    {
        $this->log('REQUEST', $feature, ['prompt_length' => strlen($prompt)]);
    }
    
    public function logResponse($feature, $response)
    {
        $this->log('RESPONSE', $feature, ['response_length' => strlen($response)]);
    }
    
    public function logError($feature, $error)
    {
        $this->log('ERROR', $feature, ['error' => $error]);
    }
    
    private function log($type, $feature, $data)
    {
        $username = 'system';
        $ipAddress = 'local';
        
        // Safely get session username if available
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['Username'])) {
            $username = $_SESSION['Username'];
        }
        
        // Safely get remote IP if available
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }
        
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'feature' => $feature,
            'user' => $username,
            'ip' => $ipAddress,
            'data' => $data
        ];
        
        $line = json_encode($entry) . PHP_EOL;
        
        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0700, true);
        }
        
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}