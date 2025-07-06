<?php

namespace CognitiveSecurity\LLMAssistant\Api;

use OPNsense\Base\ApiControllerBase;
use CognitiveSecurity\LLMAssistant\LLMAssistant;
use CognitiveSecurity\LLMAssistant\Api\OrchestrationService;

/**
 * Dashboard widget API controller
 * Handles natural language queries with continuous learning
 */
class WidgetController extends ApiControllerBase
{
    private $sessionKey = 'llm_widget_session';
    
    /**
     * Check if LLM is configured
     */
    public function statusAction()
    {
        $model = new LLMAssistant();
        
        return [
            'configured' => $model->isConfigured(),
            'features' => [
                'natural_language' => true,
                'continuous_learning' => true,
                'multi_model' => true
            ]
        ];
    }
    
    /**
     * Process natural language query from widget
     */
    public function queryAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'Invalid request method'];
        }
        
        $model = new LLMAssistant();
        if (!$model->isConfigured()) {
            return ['status' => 'error', 'message' => 'LLM Assistant not configured'];
        }
        
        $query = $this->request->getPost('query', 'string');
        $context = $this->request->getPost('context', null, []);
        
        if (empty($query)) {
            return ['status' => 'error', 'message' => 'Query cannot be empty'];
        }
        
        try {
            // Use orchestration service for intelligent routing
            $orchestrator = new OrchestrationService();
            $result = $orchestrator->processQuery($query, $context);
            
            if (isset($result['error'])) {
                return ['status' => 'error', 'message' => $result['error']];
            }
            
            // Parse response for actionable items
            $actions = $this->parseActions($query, $result['response']);
            
            // Store in session for learning
            $this->storeInteraction($query, $result['response']);
            
            return [
                'status' => 'success',
                'response' => $result['response'],
                'model' => $this->getModelUsed($result),
                'actions' => $actions,
                'interaction_id' => $this->getLastInteractionId()
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to process query: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Parse response for actionable items
     */
    private function parseActions($query, $response)
    {
        $actions = [];
        $queryLower = strtolower($query);
        
        // Check if query implies rule creation
        if (strpos($queryLower, 'create rule') !== false || 
            strpos($queryLower, 'block') !== false && strpos($queryLower, 'port') !== false) {
            
            // Extract rule parameters from response
            if (preg_match('/block.*?port\s+(\d+)/i', $response, $matches)) {
                $actions[] = [
                    'label' => 'Create Rule',
                    'icon' => 'fa-shield',
                    'style' => 'btn-warning',
                    'confirm' => 'Create a firewall rule as suggested?',
                    'endpoint' => '/api/llmassistant/rules/preview',
                    'data' => ['rule_text' => $response],
                    'successMessage' => 'Rule created (pending review)'
                ];
            }
        }
        
        // Check if query implies configuration review
        if (strpos($queryLower, 'check') !== false || 
            strpos($queryLower, 'review') !== false ||
            strpos($queryLower, 'security') !== false) {
            
            $actions[] = [
                'label' => 'Run Full Review',
                'icon' => 'fa-search',
                'style' => 'btn-primary',
                'endpoint' => '/api/llmassistant/configreview/review',
                'callback' => 'window.location.href="/ui/llmassistant/#configreview"',
                'successMessage' => 'Review complete. Opening detailed results...'
            ];
        }
        
        // Check if query implies report generation
        if (strpos($queryLower, 'report') !== false || 
            strpos($queryLower, 'incident') !== false) {
            
            $actions[] = [
                'label' => 'Generate Report',
                'icon' => 'fa-file-text',
                'style' => 'btn-info',
                'endpoint' => '/api/llmassistant/incidentreport/generate',
                'data' => ['hours' => 24],
                'callback' => 'window.location.href="/ui/llmassistant/#reports"',
                'successMessage' => 'Report generated. Opening...'
            ];
        }
        
        // Always add feedback options
        $actions[] = [
            'label' => 'Helpful',
            'icon' => 'fa-thumbs-up',
            'style' => 'btn-success btn-xs',
            'endpoint' => '/api/llmassistant/widget/feedback',
            'data' => ['feedback' => 'helpful'],
            'successMessage' => 'Thanks for the feedback!'
        ];
        
        $actions[] = [
            'label' => 'Not Helpful',
            'icon' => 'fa-thumbs-down',
            'style' => 'btn-danger btn-xs',
            'endpoint' => '/api/llmassistant/widget/feedback',
            'data' => ['feedback' => 'not_helpful'],
            'successMessage' => 'Thanks. I\'ll try to improve!'
        ];
        
        return $actions;
    }
    
    /**
     * Get conversation history
     */
    public function historyAction()
    {
        $session = $this->session->get($this->sessionKey, []);
        $messages = isset($session['messages']) ? $session['messages'] : [];
        
        // Return last 5 messages
        return [
            'messages' => array_slice($messages, -5)
        ];
    }
    
    /**
     * Provide feedback for learning
     */
    public function feedbackAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'Invalid request method'];
        }
        
        $interactionId = $this->request->getPost('interaction_id', 'int');
        $feedback = $this->request->getPost('feedback', 'string');
        $applied = $this->request->getPost('applied', 'int', 0);
        
        try {
            $orchestrator = new OrchestrationService();
            $orchestrator->provideFeedback($interactionId, $feedback, $applied);
            
            return ['status' => 'success'];
            
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Failed to record feedback'];
        }
    }
    
    /**
     * Store interaction in session for learning
     */
    private function storeInteraction($query, $response)
    {
        $session = $this->session->get($this->sessionKey, []);
        
        if (!isset($session['messages'])) {
            $session['messages'] = [];
        }
        
        // Add user message
        $session['messages'][] = [
            'content' => $query,
            'sender' => 'user',
            'timestamp' => time()
        ];
        
        // Add assistant response
        $session['messages'][] = [
            'content' => $response,
            'sender' => 'assistant',
            'timestamp' => time()
        ];
        
        // Keep only last 20 messages
        if (count($session['messages']) > 20) {
            $session['messages'] = array_slice($session['messages'], -20);
        }
        
        $this->session->set($this->sessionKey, $session);
    }
    
    /**
     * Get model used from result
     */
    private function getModelUsed($result)
    {
        // The orchestrator should indicate which model was used
        if (isset($result['model'])) {
            return $result['model'];
        }
        
        // Default to cypher-alpha for simple queries
        return 'cypher-alpha';
    }
    
    /**
     * Get last interaction ID
     */
    private function getLastInteractionId()
    {
        // This would be implemented properly with database
        return time(); // Simplified for now
    }
}