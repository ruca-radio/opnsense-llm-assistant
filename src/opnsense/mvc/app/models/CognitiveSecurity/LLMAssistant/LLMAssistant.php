<?php

namespace CognitiveSecurity\LLMAssistant;

use OPNsense\Base\BaseModel;
use OPNsense\Core\Config;

class LLMAssistant extends BaseModel
{
    /**
     * Check if the assistant is properly configured
     */
    public function isConfigured()
    {
        $enabled = (string)$this->general->enabled;
        $apiKey = (string)$this->general->api_key;
        $provider = (string)$this->general->api_provider;
        
        if ($enabled !== '1') {
            return false;
        }
        
        // Local models don't need API keys
        if ($provider === 'local') {
            return true;
        }
        
        return !empty($apiKey);
    }
    
    /**
     * Get decrypted API key (implement proper encryption in production)
     */
    public function getApiKey()
    {
        // TODO: Implement proper key encryption/decryption
        return (string)$this->general->api_key;
    }
    
    /**
     * Check if a specific feature is enabled
     */
    public function isFeatureEnabled($feature)
    {
        if (!$this->isConfigured()) {
            return false;
        }
        
        switch ($feature) {
            case 'config_review':
                return (string)$this->features->config_review === '1';
            case 'incident_reports':
                return (string)$this->features->incident_reports === '1';
            case 'rule_assistant':
                return (string)$this->features->rule_assistant === '1';
            case 'learning_mode':
                return (string)$this->features->learning_mode === '1';
            default:
                return false;
        }
    }
    
    /**
     * Get rate limit settings
     */
    public function getRateLimit()
    {
        return (int)$this->security->rate_limit;
    }
}