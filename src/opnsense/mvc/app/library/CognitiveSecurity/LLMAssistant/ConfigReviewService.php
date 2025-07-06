<?php

namespace CognitiveSecurity\LLMAssistant;

use OPNsense\Core\Config;
use OPNsense\Firewall\Filter;

class ConfigReviewService
{
    private $llmService;
    
    public function __construct()
    {
        $this->llmService = new Api\LLMService();
    }
    
    /**
     * Review firewall configuration for issues
     */
    public function reviewConfiguration($section = 'all')
    {
        $config = Config::getInstance()->object();
        $issues = [];
        $recommendations = [];
        
        switch ($section) {
            case 'rules':
                $this->reviewFirewallRules($config, $issues, $recommendations);
                break;
            case 'interfaces':
                $this->reviewInterfaces($config, $issues, $recommendations);
                break;
            case 'nat':
                $this->reviewNatRules($config, $issues, $recommendations);
                break;
            case 'all':
                $this->reviewFirewallRules($config, $issues, $recommendations);
                $this->reviewInterfaces($config, $issues, $recommendations);
                $this->reviewNatRules($config, $issues, $recommendations);
                break;
        }
        
        // Get LLM analysis if we found issues
        if (!empty($issues)) {
            $analysis = $this->getLLMAnalysis($issues, $recommendations);
            return [
                'issues' => $issues,
                'recommendations' => $recommendations,
                'analysis' => $analysis
            ];
        }
        
        return [
            'issues' => [],
            'recommendations' => ['Your configuration appears to be following best practices.'],
            'analysis' => 'No significant issues found.'
        ];
    }
    
    /**
     * Review firewall rules for common issues
     */
    private function reviewFirewallRules($config, &$issues, &$recommendations)
    {
        if (!isset($config->filter->rule)) {
            return;
        }
        
        $ruleCount = 0;
        $anyAnyRules = 0;
        $disabledRules = 0;
        $duplicateRules = [];
        $ruleHashes = [];
        
        foreach ($config->filter->rule as $rule) {
            $ruleCount++;
            
            // Check for disabled rules
            if ((string)$rule->disabled === '1') {
                $disabledRules++;
            }
            
            // Check for any/any rules
            $src = (string)$rule->source->any === '1' ? 'any' : (string)$rule->source->address;
            $dst = (string)$rule->destination->any === '1' ? 'any' : (string)$rule->destination->address;
            
            if ($src === 'any' && $dst === 'any' && (string)$rule->type === 'pass') {
                $anyAnyRules++;
                $issues[] = "Rule allows any source to any destination: " . (string)$rule->descr;
            }
            
            // Check for duplicate rules
            $ruleHash = md5($src . $dst . (string)$rule->protocol . (string)$rule->destination->port);
            if (isset($ruleHashes[$ruleHash])) {
                $duplicateRules[] = (string)$rule->descr;
            }
            $ruleHashes[$ruleHash] = true;
            
            // Check for overly permissive rules
            if ((string)$rule->protocol === 'any' && (string)$rule->type === 'pass') {
                $issues[] = "Rule allows all protocols: " . (string)$rule->descr;
            }
        }
        
        // Generate recommendations
        if ($anyAnyRules > 0) {
            $recommendations[] = "Found $anyAnyRules overly permissive any/any rules. Consider restricting source/destination.";
        }
        
        if ($disabledRules > 10) {
            $recommendations[] = "You have $disabledRules disabled rules. Consider removing unused rules.";
        }
        
        if (!empty($duplicateRules)) {
            $recommendations[] = "Found potentially duplicate rules: " . implode(', ', array_slice($duplicateRules, 0, 3));
        }
        
        if ($ruleCount > 500) {
            $recommendations[] = "You have $ruleCount firewall rules. Consider consolidating rules using aliases.";
        }
    }
    
    /**
     * Review interface configuration
     */
    private function reviewInterfaces($config, &$issues, &$recommendations)
    {
        if (!isset($config->interfaces)) {
            return;
        }
        
        $publicInterfaces = 0;
        $noFirewallInterfaces = [];
        
        foreach ($config->interfaces->children() as $ifname => $interface) {
            // Skip disabled interfaces
            if ((string)$interface->enable !== '1') {
                continue;
            }
            
            // Check for public IPs without proper firewall rules
            $ipaddr = (string)$interface->ipaddr;
            if ($this->isPublicIP($ipaddr)) {
                $publicInterfaces++;
                
                // Check if interface has restrictive rules
                $hasRestrictiveRules = $this->checkInterfaceRules($ifname, $config);
                if (!$hasRestrictiveRules) {
                    $noFirewallInterfaces[] = $ifname;
                }
            }
        }
        
        if (!empty($noFirewallInterfaces)) {
            $issues[] = "Interfaces with public IPs but no restrictive rules: " . implode(', ', $noFirewallInterfaces);
            $recommendations[] = "Add restrictive firewall rules for public-facing interfaces.";
        }
    }
    
    /**
     * Review NAT rules
     */
    private function reviewNatRules($config, &$issues, &$recommendations)
    {
        if (!isset($config->nat->rule)) {
            return;
        }
        
        $portForwards = 0;
        $riskyPorts = [];
        $riskyPortNumbers = ['22', '23', '3389', '445', '135', '139'];
        
        foreach ($config->nat->rule as $rule) {
            $portForwards++;
            $dstPort = (string)$rule->destination->port;
            
            if (in_array($dstPort, $riskyPortNumbers)) {
                $riskyPorts[] = $dstPort . ' (' . (string)$rule->descr . ')';
            }
        }
        
        if (!empty($riskyPorts)) {
            $issues[] = "NAT rules forwarding potentially risky ports: " . implode(', ', $riskyPorts);
            $recommendations[] = "Consider using VPN instead of exposing management ports.";
        }
        
        if ($portForwards > 20) {
            $recommendations[] = "You have $portForwards port forwards. Consider using reverse proxy for HTTP/HTTPS services.";
        }
    }
    
    /**
     * Get LLM analysis of issues
     */
    private function getLLMAnalysis($issues, $recommendations)
    {
        $prompt = "As a security expert, analyze these OPNsense firewall configuration issues:\n\n";
        $prompt .= "Issues found:\n";
        foreach ($issues as $issue) {
            $prompt .= "- $issue\n";
        }
        $prompt .= "\nCurrent recommendations:\n";
        foreach ($recommendations as $rec) {
            $prompt .= "- $rec\n";
        }
        $prompt .= "\nProvide a brief security impact assessment and prioritized action plan.";
        
        $result = $this->llmService->query($prompt, ['feature' => 'config_review'], 'config_review');
        
        return $result['response'] ?? 'Unable to generate analysis.';
    }
    
    /**
     * Check if IP is public
     */
    private function isPublicIP($ip)
    {
        if (empty($ip) || $ip === 'dhcp') {
            return false;
        }
        
        // RFC1918 private ranges
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16'
        ];
        
        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange($ip, $cidr)
    {
        list($subnet, $bits) = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }
    
    /**
     * Check if interface has restrictive rules
     */
    private function checkInterfaceRules($ifname, $config)
    {
        // Simplified check - in production, do deeper analysis
        $restrictiveRules = 0;
        
        if (isset($config->filter->rule)) {
            foreach ($config->filter->rule as $rule) {
                if ((string)$rule->interface === $ifname && (string)$rule->type !== 'pass') {
                    $restrictiveRules++;
                }
            }
        }
        
        return $restrictiveRules > 0;
    }
}