<?php

namespace CognitiveSecurity\LLMAssistant\Api;

use OPNsense\Base\ApiControllerBase;
use CognitiveSecurity\LLMAssistant\LLMAssistant;
use CognitiveSecurity\LLMAssistant\IncidentReportService;

class IncidentReportController extends ApiControllerBase
{
    /**
     * Generate incident report
     */
    public function generateAction()
    {
        if ($this->request->isPost()) {
            $model = new LLMAssistant();
            
            // Check if feature is enabled
            if (!$model->isFeatureEnabled('incident_reports')) {
                return [
                    'status' => 'error',
                    'message' => 'Incident report feature is not enabled'
                ];
            }
            
            // Get time range (default: last 24 hours)
            $hours = $this->request->getPost('hours', 'int', 24);
            $endTime = time();
            $startTime = $endTime - ($hours * 3600);
            
            try {
                $service = new IncidentReportService();
                $report = $service->generateReport($startTime, $endTime);
                
                // Save report for later retrieval
                $reportId = $this->saveReport($report);
                
                return [
                    'status' => 'success',
                    'report_id' => $reportId,
                    'report' => $report
                ];
                
            } catch (\Exception $e) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to generate report: ' . $e->getMessage()
                ];
            }
        }
        
        return ['status' => 'error', 'message' => 'Invalid request method'];
    }
    
    /**
     * List saved reports
     */
    public function listAction()
    {
        $reports = [];
        $reportDir = '/var/llm_assistant/reports/';
        
        if (is_dir($reportDir)) {
            $files = glob($reportDir . '*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) {
                    $reports[] = [
                        'id' => basename($file, '.json'),
                        'created' => $data['created'] ?? filemtime($file),
                        'summary' => $data['summary'] ?? 'No summary available'
                    ];
                }
            }
        }
        
        // Sort by creation date
        usort($reports, function($a, $b) {
            return $b['created'] - $a['created'];
        });
        
        return [
            'status' => 'success',
            'reports' => array_slice($reports, 0, 20) // Last 20 reports
        ];
    }
    
    /**
     * Retrieve a saved report
     */
    public function getAction($reportId)
    {
        $reportFile = sprintf('/var/llm_assistant/reports/%s.json', 
                             preg_replace('/[^a-zA-Z0-9_-]/', '', $reportId));
        
        if (file_exists($reportFile)) {
            $report = json_decode(file_get_contents($reportFile), true);
            return [
                'status' => 'success',
                'report' => $report
            ];
        }
        
        return [
            'status' => 'error',
            'message' => 'Report not found'
        ];
    }
    
    /**
     * Save report to disk
     */
    private function saveReport($report)
    {
        $reportDir = '/var/llm_assistant/reports/';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0700, true);
        }
        
        $reportId = date('Ymd_His') . '_' . uniqid();
        $report['created'] = time();
        $report['id'] = $reportId;
        
        $reportFile = $reportDir . $reportId . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        
        return $reportId;
    }
}