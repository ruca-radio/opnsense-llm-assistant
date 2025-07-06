<script>
$(document).ready(function() {
    // Initialize tabs
    $("#maintabs").tabs();
    
    // Load saved settings
    mapDataToFormUI({'frm_GeneralSettings':"/api/llmassistant/settings/get"}).done(function(data){
        formatTokenizersUI();
    });
    
    // Save settings
    $("#saveSettings").click(function(){
        saveFormToEndpoint("/api/llmassistant/settings/set", 'frm_GeneralSettings', function(){
            $("#saveAlert").removeClass("hidden").delay(3000).fadeOut();
        });
    });
    
    // Configuration Review
    $("#runConfigReview").click(function(){
        $("#configReviewResults").html('<i class="fa fa-spinner fa-spin"></i> Analyzing configuration...');
        
        $.ajax({
            url: '/api/llmassistant/configreview/review',
            type: 'POST',
            data: { section: $("#reviewSection").val() },
            success: function(response) {
                if (response.status === 'success') {
                    displayConfigReview(response.review);
                } else {
                    $("#configReviewResults").html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            }
        });
    });
    
    // Incident Report Generation
    $("#generateReport").click(function(){
        $("#reportResults").html('<i class="fa fa-spinner fa-spin"></i> Generating report...');
        
        $.ajax({
            url: '/api/llmassistant/incidentreport/generate',
            type: 'POST',
            data: { hours: $("#reportHours").val() },
            success: function(response) {
                if (response.status === 'success') {
                    displayIncidentReport(response.report);
                } else {
                    $("#reportResults").html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            }
        });
    });
    
    // Learning mode chat
    $("#askQuestion").click(function(){
        var question = $("#learningQuestion").val();
        if (!question) return;
        
        $("#learningResponse").html('<i class="fa fa-spinner fa-spin"></i> Thinking...');
        
        $.ajax({
            url: '/api/llmassistant/learning/ask',
            type: 'POST',
            data: { question: question },
            success: function(response) {
                if (response.status === 'success') {
                    $("#learningResponse").html('<div class="well">' + 
                        $('<div>').text(response.answer).html() + '</div>');
                } else {
                    $("#learningResponse").html('<div class="alert alert-danger">' + 
                        response.message + '</div>');
                }
            }
        });
    });
});

function displayConfigReview(review) {
    var html = '<div class="config-review-results">';
    
    // Issues
    if (review.issues && review.issues.length > 0) {
        html += '<h4>Issues Found</h4>';
        html += '<div class="alert alert-warning">';
        html += '<ul>';
        review.issues.forEach(function(issue) {
            html += '<li>' + $('<div>').text(issue).html() + '</li>';
        });
        html += '</ul></div>';
    } else {
        html += '<div class="alert alert-success">No critical issues found!</div>';
    }
    
    // Recommendations
    if (review.recommendations && review.recommendations.length > 0) {
        html += '<h4>Recommendations</h4>';
        html += '<ul class="list-group">';
        review.recommendations.forEach(function(rec) {
            html += '<li class="list-group-item">' + $('<div>').text(rec).html() + '</li>';
        });
        html += '</ul>';
    }
    
    // AI Analysis
    if (review.analysis) {
        html += '<h4>AI Analysis</h4>';
        html += '<div class="well">' + $('<div>').text(review.analysis).html() + '</div>';
    }
    
    html += '</div>';
    $("#configReviewResults").html(html);
}

function displayIncidentReport(report) {
    var html = '<div class="incident-report">';
    
    // Executive Summary
    html += '<h4>Executive Summary</h4>';
    html += '<p>' + $('<div>').text(report.summary).html() + '</p>';
    
    // Statistics
    if (report.statistics) {
        html += '<h4>Statistics</h4>';
        html += '<div class="row">';
        html += '<div class="col-md-3"><div class="panel panel-default"><div class="panel-body">';
        html += '<h3>' + report.statistics.total_events + '</h3><p>Total Events</p>';
        html += '</div></div></div>';
        html += '<div class="col-md-3"><div class="panel panel-default"><div class="panel-body">';
        html += '<h3>' + report.statistics.blocked + '</h3><p>Blocked</p>';
        html += '</div></div></div>';
        html += '<div class="col-md-3"><div class="panel panel-default"><div class="panel-body">';
        html += '<h3>' + report.statistics.passed + '</h3><p>Allowed</p>';
        html += '</div></div></div>';
        html += '<div class="col-md-3"><div class="panel panel-default"><div class="panel-body">';
        html += '<h3>' + (report.incidents ? report.incidents.length : 0) + '</h3><p>Incidents</p>';
        html += '</div></div></div>';
        html += '</div>';
    }
    
    // Incidents
    if (report.incidents && report.incidents.length > 0) {
        html += '<h4>Security Incidents</h4>';
        report.incidents.forEach(function(incident) {
            var alertClass = incident.severity === 'critical' ? 'danger' : 
                           incident.severity === 'high' ? 'warning' : 'info';
            html += '<div class="alert alert-' + alertClass + '">';
            html += '<strong>' + incident.type.replace('_', ' ').toUpperCase() + '</strong>: ';
            html += $('<span>').text(incident.description).html();
            html += '</div>';
        });
    }
    
    // AI Analysis
    if (report.analysis) {
        html += '<h4>AI Analysis</h4>';
        html += '<div class="well">' + $('<div>').text(report.analysis).html() + '</div>';
    }
    
    // Recommendations
    if (report.recommendations && report.recommendations.length > 0) {
        html += '<h4>Recommended Actions</h4>';
        html += '<ol>';
        report.recommendations.forEach(function(rec) {
            html += '<li>' + $('<div>').text(rec).html() + '</li>';
        });
        html += '</ol>';
    }
    
    html += '</div>';
    $("#reportResults").html(html);
}
</script>

<style>
.tab-content { padding: 20px; }
.config-review-results h4 { margin-top: 20px; }
.incident-report h4 { margin-top: 20px; }
.panel h3 { margin-top: 0; }
#saveAlert { margin-top: 10px; }
</style>

<div class="content-box">
    <div id="maintabs">
        <ul class="nav nav-tabs" role="tablist">
            <li class="active"><a href="#dashboard" data-toggle="tab">Dashboard</a></li>
            <li><a href="#configreview" data-toggle="tab">Config Review</a></li>
            <li><a href="#reports" data-toggle="tab">Incident Reports</a></li>
            <li><a href="#learning" data-toggle="tab">Learning Mode</a></li>
            <li><a href="#settings" data-toggle="tab">Settings</a></li>
        </ul>
        
        <div class="tab-content">
            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-pane active">
                <div class="row">
                    <div class="col-md-12">
                        <h3>LLM Security Assistant Dashboard</h3>
                        <p>Welcome to the OPNsense LLM Security Assistant. This tool helps you:</p>
                        <ul>
                            <li><strong>Review Configuration</strong> - Identify security issues and get recommendations</li>
                            <li><strong>Generate Reports</strong> - Create incident reports with AI analysis</li>
                            <li><strong>Learn</strong> - Ask questions about OPNsense and security best practices</li>
                        </ul>
                        
                        <div class="alert alert-info">
                            <strong>Security Note:</strong> This assistant provides suggestions only. 
                            Always review and validate any recommendations before implementation.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Config Review Tab -->
            <div id="configreview" class="tab-pane">
                <h3>Configuration Security Review</h3>
                <p>Analyze your OPNsense configuration for security issues and optimization opportunities.</p>
                
                <div class="form-group">
                    <label>Review Section:</label>
                    <select id="reviewSection" class="form-control" style="width: 300px;">
                        <option value="all">Complete Configuration</option>
                        <option value="rules">Firewall Rules</option>
                        <option value="interfaces">Network Interfaces</option>
                        <option value="nat">NAT Rules</option>
                    </select>
                </div>
                
                <button id="runConfigReview" class="btn btn-primary">
                    <i class="fa fa-search"></i> Run Security Review
                </button>
                
                <hr>
                
                <div id="configReviewResults"></div>
            </div>
            
            <!-- Reports Tab -->
            <div id="reports" class="tab-pane">
                <h3>Incident Report Generator</h3>
                <p>Generate comprehensive security reports with AI-powered analysis.</p>
                
                <div class="form-group">
                    <label>Time Period:</label>
                    <select id="reportHours" class="form-control" style="width: 300px;">
                        <option value="1">Last Hour</option>
                        <option value="6">Last 6 Hours</option>
                        <option value="24" selected>Last 24 Hours</option>
                        <option value="168">Last Week</option>
                    </select>
                </div>
                
                <button id="generateReport" class="btn btn-primary">
                    <i class="fa fa-file-text"></i> Generate Report
                </button>
                
                <hr>
                
                <div id="reportResults"></div>
            </div>
            
            <!-- Learning Mode Tab -->
            <div id="learning" class="tab-pane">
                <h3>Learning Mode</h3>
                <p>Ask questions about OPNsense configuration, security best practices, or get help with specific scenarios.</p>
                
                <div class="form-group">
                    <label>Your Question:</label>
                    <textarea id="learningQuestion" class="form-control" rows="3" 
                        placeholder="e.g., How do I configure a DMZ? What ports should I block for security?"></textarea>
                </div>
                
                <button id="askQuestion" class="btn btn-primary">
                    <i class="fa fa-question-circle"></i> Ask Assistant
                </button>
                
                <hr>
                
                <div id="learningResponse"></div>
            </div>
            
            <!-- Settings Tab -->
            <div id="settings" class="tab-pane">
                <h3>LLM Assistant Settings</h3>
                
                {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_GeneralSettings'])}}
                
                <hr>
                
                <button class="btn btn-primary" id="saveSettings" type="button">
                    <i class="fa fa-save"></i> {{ lang._('Save') }}
                </button>
                
                <div id="saveAlert" class="alert alert-success hidden">
                    Settings saved successfully!
                </div>
            </div>
        </div>
    </div>
</div>