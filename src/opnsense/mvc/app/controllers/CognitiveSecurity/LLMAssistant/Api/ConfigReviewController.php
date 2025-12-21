<?php

namespace CognitiveSecurity\LLMAssistant\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;
use CognitiveSecurity\LLMAssistant\LLMAssistant;
use CognitiveSecurity\LLMAssistant\ConfigReviewService;

class ConfigReviewController extends ApiControllerBase
{
    /**
     * Review firewall configuration
     */
    public function reviewAction()
    {
        if ($this->request->isPost()) {
            $model = new LLMAssistant();
            
            // Check if feature is enabled
            if (!$model->isFeatureEnabled('config_review')) {
                return [
                    'status' => 'error',
                    'message' => 'Configuration review feature is not enabled'
                ];
            }
            
            $section = $this->request->getPost('section', 'string', 'all');
            
            // Validate section parameter
            $validSections = ['all', 'rules', 'interfaces', 'nat'];
            if (!in_array($section, $validSections)) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid section specified'
                ];
            }
            
            try {
                $service = new ConfigReviewService();
                $review = $service->reviewConfiguration($section);
                
                return [
                    'status' => 'success',
                    'review' => $review
                ];
                
            } catch (\Exception $e) {
                error_log("Config review error: " . $e->getMessage());
                return [
                    'status' => 'error',
                    'message' => 'Failed to review configuration: ' . $e->getMessage()
                ];
            }
        }
        
        return ['status' => 'error', 'message' => 'Invalid request method'];
    }
    
    /**
     * Get available review sections
     */
    public function sectionsAction()
    {
        return [
            'sections' => [
                'all' => 'Complete Configuration',
                'rules' => 'Firewall Rules',
                'interfaces' => 'Network Interfaces',
                'nat' => 'NAT Rules'
            ]
        ];
    }
}