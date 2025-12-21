<?php

namespace CognitiveSecurity\LLMAssistant\Api;

use OPNsense\Base\ApiControllerBase;
use CognitiveSecurity\LLMAssistant\LLMAssistant;
use CognitiveSecurity\LLMAssistant\Api\OrchestrationService;

/**
 * Learning mode controller for Q&A functionality
 */
class LearningController extends ApiControllerBase
{
    /**
     * Answer questions about OPNsense and security
     */
    public function askAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'Invalid request method'];
        }
        
        $model = new LLMAssistant();
        
        // Check if learning mode is enabled
        if (!$model->isFeatureEnabled('learning_mode')) {
            return [
                'status' => 'error',
                'message' => 'Learning mode is not enabled'
            ];
        }
        
        $question = $this->request->getPost('question', 'string', '');
        
        if (empty($question)) {
            return ['status' => 'error', 'message' => 'Question cannot be empty'];
        }
        
        // Limit question length
        if (strlen($question) > 1000) {
            return ['status' => 'error', 'message' => 'Question is too long (max 1000 characters)'];
        }
        
        try {
            // Build context for learning query
            $context = [
                'mode' => 'learning',
                'system_info' => $this->getSystemInfo()
            ];
            
            // Use orchestration service for intelligent response
            $orchestrator = new OrchestrationService();
            $result = $orchestrator->processQuery($question, $context);
            
            if (isset($result['error'])) {
                return ['status' => 'error', 'message' => $result['error']];
            }
            
            return [
                'status' => 'success',
                'answer' => $result['response'],
                'model' => $result['model'] ?? 'unknown'
            ];
            
        } catch (\Exception $e) {
            error_log("Learning mode error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to process question: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get suggested questions for learning mode
     */
    public function suggestionsAction()
    {
        $suggestions = [
            'Getting Started' => [
                'How do I configure a basic firewall rule?',
                'What is the difference between LAN and WAN?',
                'How do I set up port forwarding?'
            ],
            'Security Best Practices' => [
                'What ports should I block for security?',
                'How do I configure a DMZ?',
                'What are the best practices for SSH access?'
            ],
            'Troubleshooting' => [
                'Why is my traffic being blocked?',
                'How do I check firewall logs?',
                'What does this error message mean?'
            ],
            'Advanced Topics' => [
                'How do I set up VLANs?',
                'What is stateful inspection?',
                'How do I configure intrusion detection?'
            ]
        ];
        
        return [
            'status' => 'success',
            'suggestions' => $suggestions
        ];
    }
    
    /**
     * Get basic system information for context
     */
    private function getSystemInfo()
    {
        $info = [
            'version' => 'OPNsense',
            'has_rules' => false,
            'interface_count' => 0
        ];
        
        try {
            $config = \OPNsense\Core\Config::getInstance()->object();
            
            if (isset($config->filter->rule)) {
                $info['has_rules'] = true;
                $info['rule_count'] = count($config->filter->rule);
            }
            
            if (isset($config->interfaces)) {
                $count = 0;
                foreach ($config->interfaces->children() as $interface) {
                    if ((string)$interface->enable === '1') {
                        $count++;
                    }
                }
                $info['interface_count'] = $count;
            }
        } catch (\Exception $e) {
            // Silently fail, return basic info
        }
        
        return $info;
    }
}
