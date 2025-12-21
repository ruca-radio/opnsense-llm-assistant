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
     * Get decrypted API key
     * Uses OPNsense configuration encryption if available
     */
    public function getApiKey()
    {
        $encryptedKey = (string)$this->general->api_key;
        
        if (empty($encryptedKey)) {
            return '';
        }
        
        // Check if the key is already in plaintext (for backwards compatibility)
        // In production, all keys should be encrypted
        if (strpos($encryptedKey, 'enc:') === 0) {
            // Key is encrypted, decrypt it
            return $this->decryptApiKey(substr($encryptedKey, 4));
        }
        
        // Return plaintext key (should be migrated to encrypted format)
        return $encryptedKey;
    }
    
    /**
     * Set encrypted API key
     */
    public function setApiKey($plainKey)
    {
        if (empty($plainKey)) {
            $this->general->api_key = '';
            return;
        }
        
        // Encrypt the key before storing
        $encrypted = $this->encryptApiKey($plainKey);
        $this->general->api_key = 'enc:' . $encrypted;
    }
    
    /**
     * Encrypt API key using system-specific encryption
     * This is a simplified version - OPNsense has its own encryption methods
     */
    private function encryptApiKey($plaintext)
    {
        // In production OPNsense, use the framework's encryption
        // For now, use base64 encoding as a placeholder
        // Real implementation should use proper encryption like openssl_encrypt
        $method = 'AES-256-CBC';
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        
        if (function_exists('openssl_encrypt')) {
            $encrypted = openssl_encrypt($plaintext, $method, $key, 0, $iv);
            return base64_encode($iv . $encrypted);
        }
        
        // Fallback to base64 (not secure, but better than nothing)
        return base64_encode($plaintext);
    }
    
    /**
     * Decrypt API key
     */
    private function decryptApiKey($encrypted)
    {
        $method = 'AES-256-CBC';
        $key = $this->getEncryptionKey();
        
        if (function_exists('openssl_decrypt')) {
            $data = base64_decode($encrypted);
            if ($data === false || strlen($data) < 16) {
                // Try fallback decryption
                return base64_decode($encrypted);
            }
            
            $iv = substr($data, 0, 16);
            $ciphertext = substr($data, 16);
            
            $decrypted = openssl_decrypt($ciphertext, $method, $key, 0, $iv);
            if ($decrypted !== false) {
                return $decrypted;
            }
        }
        
        // Fallback decryption
        return base64_decode($encrypted);
    }
    
    /**
     * Get encryption key for API keys
     * In production, this should use OPNsense's system key
     */
    private function getEncryptionKey()
    {
        // Try to use OPNsense system key if available
        $systemKey = @file_get_contents('/conf/config.xml.key');
        if ($systemKey !== false) {
            return hash('sha256', $systemKey, true);
        }
        
        // Fallback to derived key from hostname
        // This is not ideal but works for basic protection
        $hostname = php_uname('n');
        return hash('sha256', $hostname . 'llm_assistant_key', true);
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