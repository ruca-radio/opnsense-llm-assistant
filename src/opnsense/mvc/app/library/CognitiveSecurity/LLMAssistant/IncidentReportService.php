<?php

namespace CognitiveSecurity\LLMAssistant;

use OPNsense\Core\Config;

class IncidentReportService
{
    private $llmService;
    private $logPath = '/var/log/filter/';
    
    public function __construct()
    {
        $this->llmService = new Api\LLMService();
    }
    
    /**
     * Generate incident report for a time range
     */
    public function generateReport($startTime, $endTime, $options = [])
    {
        $logData = $this->collectLogData($startTime, $endTime);
        $statistics = $this->analyzeLogData($logData);
        $incidents = $this->identifyIncidents($logData, $statistics);
        
        // Generate different report sections
        $report = [
            'summary' => $this->generateExecutiveSummary($statistics, $incidents),
            'statistics' => $statistics,
            'incidents' => $incidents,
            'timeline' => $this->generateTimeline($incidents),
            'recommendations' => []
        ];
        
        // Get LLM analysis if incidents were found
        if (!empty($incidents)) {
            $llmAnalysis = $this->getLLMAnalysis($incidents, $statistics);
            $report['analysis'] = $llmAnalysis['analysis'];
            $report['recommendations'] = $llmAnalysis['recommendations'];
        }
        
        return $report;
    }
    
    /**
     * Collect log data for analysis
     */
    private function collectLogData($startTime, $endTime)
    {
        $logs = [];
        $logFile = $this->logPath . 'latest.log';
        
        // Read log file (simplified - in production use log parsing library)
        if (!file_exists($logFile)) {
            return $logs;
        }
        
        $cmd = sprintf(
            'clog %s 2>/dev/null | grep -E "^%s|^%s" | tail -n 10000',
            escapeshellarg($logFile),
            date('M d', $startTime),
            date('M d', $endTime)
        );
        
        $output = @shell_exec($cmd);
        
        if (empty($output)) {
            return $logs;
        }
        
        $lines = explode("\n", $output);
        
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }
            
            $parsed = $this->parseLogLine($line);
            if ($parsed && $parsed['timestamp'] >= $startTime && $parsed['timestamp'] <= $endTime) {
                $logs[] = $parsed;
            }
        }
        
        return $logs;
    }
    
    /**
     * Parse a firewall log line
     */
    private function parseLogLine($line)
    {
        // Simplified parser - in production use proper log parser
        if (preg_match('/^(\w+ \d+ [\d:]+).*?(block|pass).*?(\d+\.\d+\.\d+\.\d+).*?(\d+\.\d+\.\d+\.\d+).*?(\w+)\/(\d+)/', $line, $matches)) {
            return [
                'timestamp' => strtotime($matches[1]),
                'action' => $matches[2],
                'src_ip' => $matches[3],
                'dst_ip' => $matches[4],
                'protocol' => $matches[5],
                'dst_port' => $matches[6],
                'raw' => $line
            ];
        }
        return null;
    }
    
    /**
     * Analyze log data for statistics
     */
    private function analyzeLogData($logs)
    {
        $stats = [
            'total_events' => count($logs),
            'blocked' => 0,
            'passed' => 0,
            'top_sources' => [],
            'top_destinations' => [],
            'top_ports' => [],
            'protocols' => []
        ];
        
        $sources = [];
        $destinations = [];
        $ports = [];
        $protocols = [];
        
        foreach ($logs as $log) {
            // Count actions
            if ($log['action'] === 'block') {
                $stats['blocked']++;
            } else {
                $stats['passed']++;
            }
            
            // Count sources
            $sources[$log['src_ip']] = ($sources[$log['src_ip']] ?? 0) + 1;
            
            // Count destinations
            $destinations[$log['dst_ip']] = ($destinations[$log['dst_ip']] ?? 0) + 1;
            
            // Count ports
            $ports[$log['dst_port']] = ($ports[$log['dst_port']] ?? 0) + 1;
            
            // Count protocols
            $protocols[$log['protocol']] = ($protocols[$log['protocol']] ?? 0) + 1;
        }
        
        // Get top 10 of each
        arsort($sources);
        arsort($destinations);
        arsort($ports);
        arsort($protocols);
        
        $stats['top_sources'] = array_slice($sources, 0, 10, true);
        $stats['top_destinations'] = array_slice($destinations, 0, 10, true);
        $stats['top_ports'] = array_slice($ports, 0, 10, true);
        $stats['protocols'] = $protocols;
        
        return $stats;
    }
    
    /**
     * Identify potential security incidents
     */
    private function identifyIncidents($logs, $statistics)
    {
        $incidents = [];
        
        // Port scan detection
        $portScans = $this->detectPortScans($logs);
        if (!empty($portScans)) {
            $incidents[] = [
                'type' => 'port_scan',
                'severity' => 'medium',
                'description' => 'Potential port scanning activity detected',
                'details' => $portScans
            ];
        }
        
        // Brute force detection
        $bruteForce = $this->detectBruteForce($logs);
        if (!empty($bruteForce)) {
            $incidents[] = [
                'type' => 'brute_force',
                'severity' => 'high',
                'description' => 'Potential brute force attempts detected',
                'details' => $bruteForce
            ];
        }
        
        // DDoS detection
        $ddos = $this->detectDDoS($logs);
        if (!empty($ddos)) {
            $incidents[] = [
                'type' => 'ddos',
                'severity' => 'critical',
                'description' => 'Potential DDoS attack detected',
                'details' => $ddos
            ];
        }
        
        return $incidents;
    }
    
    /**
     * Detect port scanning activity
     */
    private function detectPortScans($logs)
    {
        $scanners = [];
        $threshold = 20; // ports per source
        
        // Group by source IP
        $sourceActivity = [];
        foreach ($logs as $log) {
            if ($log['action'] === 'block') {
                $key = $log['src_ip'];
                if (!isset($sourceActivity[$key])) {
                    $sourceActivity[$key] = [];
                }
                $sourceActivity[$key][$log['dst_port']] = true;
            }
        }
        
        // Find sources hitting many ports
        foreach ($sourceActivity as $ip => $ports) {
            if (count($ports) > $threshold) {
                $scanners[] = [
                    'source_ip' => $ip,
                    'ports_scanned' => count($ports),
                    'sample_ports' => array_keys(array_slice($ports, 0, 10, true))
                ];
            }
        }
        
        return $scanners;
    }
    
    /**
     * Detect brute force attempts
     */
    private function detectBruteForce($logs)
    {
        $attempts = [];
        $bruteForcePorts = ['22', '3389', '21', '23'];
        $threshold = 10; // attempts per minute
        
        // Group by source, port, and minute
        $activity = [];
        foreach ($logs as $log) {
            if ($log['action'] === 'block' && in_array($log['dst_port'], $bruteForcePorts)) {
                $minute = floor($log['timestamp'] / 60);
                $key = $log['src_ip'] . ':' . $log['dst_port'] . ':' . $minute;
                $activity[$key] = ($activity[$key] ?? 0) + 1;
            }
        }
        
        // Find high-frequency attempts
        foreach ($activity as $key => $count) {
            if ($count > $threshold) {
                list($ip, $port, $minute) = explode(':', $key);
                $attempts[] = [
                    'source_ip' => $ip,
                    'target_port' => $port,
                    'attempts' => $count,
                    'time' => date('Y-m-d H:i', $minute * 60)
                ];
            }
        }
        
        return $attempts;
    }
    
    /**
     * Detect potential DDoS
     */
    private function detectDDoS($logs)
    {
        $ddosIndicators = [];
        $threshold = 1000; // events per minute
        
        // Group by minute
        $eventsPerMinute = [];
        foreach ($logs as $log) {
            $minute = floor($log['timestamp'] / 60);
            $eventsPerMinute[$minute] = ($eventsPerMinute[$minute] ?? 0) + 1;
        }
        
        // Find spikes
        foreach ($eventsPerMinute as $minute => $count) {
            if ($count > $threshold) {
                $ddosIndicators[] = [
                    'time' => date('Y-m-d H:i', $minute * 60),
                    'events' => $count,
                    'severity' => $count > $threshold * 2 ? 'critical' : 'high'
                ];
            }
        }
        
        return $ddosIndicators;
    }
    
    /**
     * Generate executive summary
     */
    private function generateExecutiveSummary($statistics, $incidents)
    {
        $summary = sprintf(
            "During the reporting period, the firewall processed %d events with %d blocked and %d allowed connections. ",
            $statistics['total_events'],
            $statistics['blocked'],
            $statistics['passed']
        );
        
        if (empty($incidents)) {
            $summary .= "No significant security incidents were detected.";
        } else {
            $summary .= sprintf(
                "%d potential security incidents were identified requiring attention.",
                count($incidents)
            );
        }
        
        return $summary;
    }
    
    /**
     * Generate incident timeline
     */
    private function generateTimeline($incidents)
    {
        $timeline = [];
        
        foreach ($incidents as $incident) {
            $timeline[] = [
                'type' => $incident['type'],
                'severity' => $incident['severity'],
                'description' => $incident['description'],
                'count' => count($incident['details'])
            ];
        }
        
        return $timeline;
    }
    
    /**
     * Get LLM analysis of incidents
     */
    private function getLLMAnalysis($incidents, $statistics)
    {
        $prompt = "Analyze this security incident report from an OPNsense firewall:\n\n";
        $prompt .= "Statistics:\n";
        $prompt .= "- Total events: " . $statistics['total_events'] . "\n";
        $prompt .= "- Blocked: " . $statistics['blocked'] . "\n";
        $prompt .= "- Top attacked ports: " . implode(', ', array_keys(array_slice($statistics['top_ports'], 0, 5, true))) . "\n";
        $prompt .= "\nIncidents detected:\n";
        
        foreach ($incidents as $incident) {
            $prompt .= sprintf(
                "- %s (%s severity): %s - %d occurrences\n",
                $incident['type'],
                $incident['severity'],
                $incident['description'],
                count($incident['details'])
            );
        }
        
        $prompt .= "\nProvide:\n1. Brief analysis of the security posture\n2. Three specific recommendations\n3. Priority actions for the security team";
        
        $result = $this->llmService->query($prompt, ['feature' => 'incident_report'], 'incident_reports');
        
        if (isset($result['response'])) {
            // Parse LLM response to extract sections
            $response = $result['response'];
            
            // Simple parsing - in production use more robust method
            $sections = preg_split('/\d+\./', $response);
            
            return [
                'analysis' => trim($sections[0] ?? $response),
                'recommendations' => [
                    trim($sections[1] ?? 'Review firewall rules for the most attacked ports'),
                    trim($sections[2] ?? 'Implement rate limiting for suspicious sources'),
                    trim($sections[3] ?? 'Consider blocking repeat offenders at the network edge')
                ]
            ];
        }
        
        return [
            'analysis' => 'Unable to generate AI analysis.',
            'recommendations' => [
                'Review logs manually for detailed analysis',
                'Consider implementing additional monitoring',
                'Update firewall rules based on attack patterns'
            ]
        ];
    }
}