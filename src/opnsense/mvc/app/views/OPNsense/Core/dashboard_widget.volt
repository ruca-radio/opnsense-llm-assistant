{# LLM Assistant Dashboard Widget #}
<div id="llm-assistant-widget" class="widget panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-robot"></i> AI Assistant
            <div class="widget-controls pull-right">
                <a href="#" class="llm-minimize"><i class="fa fa-chevron-down"></i></a>
                <a href="/ui/llmassistant/" target="_blank"><i class="fa fa-external-link"></i></a>
            </div>
        </h3>
    </div>
    <div class="panel-body">
        <div id="llm-chat-container" style="height: 300px; overflow-y: auto; margin-bottom: 10px;">
            <div class="llm-welcome-message">
                <p><strong>Hello! I'm your AI firewall assistant.</strong></p>
                <p>Ask me anything about your OPNsense configuration, security status, or firewall rules.</p>
                <p style="font-size: 0.9em; color: #666;">Examples:</p>
                <ul style="font-size: 0.9em; color: #666;">
                    <li>"Show me recent blocked traffic"</li>
                    <li>"Create a rule to block port 8080"</li>
                    <li>"Check my firewall for security issues"</li>
                    <li>"Explain what my WAN rules do"</li>
                </ul>
            </div>
        </div>
        
        <div class="input-group">
            <input type="text" id="llm-input" class="form-control" 
                   placeholder="Ask me anything..." 
                   onkeypress="if(event.keyCode==13) LLMWidget.sendMessage()">
            <span class="input-group-btn">
                <button class="btn btn-primary" onclick="LLMWidget.sendMessage()">
                    <i class="fa fa-paper-plane"></i>
                </button>
            </span>
        </div>
        
        <div id="llm-status" style="margin-top: 5px; font-size: 0.85em; color: #666;">
            <span id="llm-model-indicator">
                <i class="fa fa-circle" style="color: #5cb85c;"></i> Ready
            </span>
            <span class="pull-right" id="llm-learning-indicator" style="display: none;">
                <i class="fa fa-graduation-cap"></i> Learning from interaction
            </span>
        </div>
    </div>
</div>

<style>
#llm-assistant-widget {
    min-height: 400px;
}

#llm-chat-container {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
}

.llm-message {
    margin-bottom: 10px;
    padding: 8px;
    border-radius: 4px;
}

.llm-user-message {
    background: #e3f2fd;
    text-align: right;
    margin-left: 20%;
}

.llm-assistant-message {
    background: #fff;
    border: 1px solid #ddd;
    margin-right: 20%;
}

.llm-message-meta {
    font-size: 0.8em;
    color: #666;
    margin-top: 4px;
}

.llm-thinking {
    color: #666;
    font-style: italic;
}

.llm-action-buttons {
    margin-top: 8px;
}

.llm-action-buttons button {
    font-size: 0.85em;
    padding: 2px 8px;
    margin-right: 4px;
}

.llm-code-block {
    background: #f5f5f5;
    border: 1px solid #ddd;
    padding: 8px;
    margin: 8px 0;
    font-family: monospace;
    font-size: 0.9em;
    border-radius: 3px;
}

.widget-controls a {
    color: #666;
    margin-left: 5px;
}
</style>

<script>
var LLMWidget = {
    messageId: 0,
    currentModel: 'cypher-alpha',
    
    init: function() {
        // Load conversation history
        this.loadHistory();
        
        // Set up minimize button
        $('.llm-minimize').click(function(e) {
            e.preventDefault();
            $('#llm-chat-container').slideToggle();
            $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
        });
        
        // Check if LLM is configured
        this.checkConfiguration();
    },
    
    checkConfiguration: function() {
        $.ajax({
            url: '/api/llmassistant/widget/status',
            type: 'GET',
            success: function(response) {
                if (!response.configured) {
                    $('#llm-chat-container').html(
                        '<div class="alert alert-warning">' +
                        '<i class="fa fa-exclamation-triangle"></i> ' +
                        'LLM Assistant not configured. ' +
                        '<a href="/ui/llmassistant/#settings">Configure now</a>' +
                        '</div>'
                    );
                    $('#llm-input').prop('disabled', true);
                }
            }
        });
    },
    
    sendMessage: function() {
        var input = $('#llm-input').val().trim();
        if (!input) return;
        
        // Add user message
        this.addMessage(input, 'user');
        $('#llm-input').val('');
        
        // Show thinking indicator
        var thinkingId = this.addMessage('<i class="fa fa-spinner fa-spin"></i> Thinking...', 'assistant', true);
        
        // Update status
        $('#llm-model-indicator').html('<i class="fa fa-circle" style="color: #f0ad4e;"></i> Processing...');
        $('#llm-learning-indicator').show();
        
        // Send to backend
        $.ajax({
            url: '/api/llmassistant/widget/query',
            type: 'POST',
            data: {
                query: input,
                context: this.getContext()
            },
            success: function(response) {
                // Remove thinking message
                $('#' + thinkingId).remove();
                
                if (response.status === 'success') {
                    var messageEl = LLMWidget.addMessage(response.response, 'assistant');
                    
                    // Add action buttons if applicable
                    if (response.actions) {
                        LLMWidget.addActionButtons(messageEl, response.actions);
                    }
                    
                    // Update model indicator
                    var modelUsed = response.model || 'cypher-alpha';
                    $('#llm-model-indicator').html(
                        '<i class="fa fa-circle" style="color: #5cb85c;"></i> Ready ' +
                        '<span style="font-size: 0.8em;">(' + modelUsed + ')</span>'
                    );
                } else {
                    LLMWidget.addMessage('Error: ' + response.message, 'assistant');
                    $('#llm-model-indicator').html('<i class="fa fa-circle" style="color: #d9534f;"></i> Error');
                }
                
                $('#llm-learning-indicator').fadeOut(2000);
            },
            error: function() {
                $('#' + thinkingId).remove();
                LLMWidget.addMessage('Connection error. Please try again.', 'assistant');
                $('#llm-model-indicator').html('<i class="fa fa-circle" style="color: #d9534f;"></i> Offline');
                $('#llm-learning-indicator').hide();
            }
        });
    },
    
    addMessage: function(content, sender, isThinking) {
        var messageId = 'llm-msg-' + (++this.messageId);
        var messageClass = sender === 'user' ? 'llm-user-message' : 'llm-assistant-message';
        if (isThinking) messageClass += ' llm-thinking';
        
        var message = $('<div>')
            .attr('id', messageId)
            .addClass('llm-message ' + messageClass);
        
        // Parse content for code blocks
        content = this.parseContent(content);
        
        message.html(content);
        
        // Add metadata
        if (!isThinking) {
            var meta = $('<div>').addClass('llm-message-meta');
            meta.text(sender === 'user' ? 'You' : 'Assistant');
            meta.append(' • ' + new Date().toLocaleTimeString());
            message.append(meta);
        }
        
        $('#llm-chat-container').append(message);
        $('#llm-chat-container').scrollTop($('#llm-chat-container')[0].scrollHeight);
        
        return messageId;
    },
    
    parseContent: function(content) {
        // Convert code blocks
        content = content.replace(/```(\w+)?\n([\s\S]*?)```/g, function(match, lang, code) {
            return '<div class="llm-code-block">' + $('<div>').text(code.trim()).html() + '</div>';
        });
        
        // Convert inline code
        content = content.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Convert line breaks
        content = content.replace(/\n/g, '<br>');
        
        return content;
    },
    
    addActionButtons: function(messageEl, actions) {
        var buttonContainer = $('<div>').addClass('llm-action-buttons');
        
        actions.forEach(function(action) {
            var btn = $('<button>')
                .addClass('btn btn-sm ' + (action.style || 'btn-default'))
                .html('<i class="fa ' + (action.icon || 'fa-check') + '"></i> ' + action.label)
                .click(function() {
                    LLMWidget.executeAction(action);
                });
            buttonContainer.append(btn);
        });
        
        $('#' + messageEl).append(buttonContainer);
    },
    
    executeAction: function(action) {
        if (action.confirm && !confirm(action.confirm)) {
            return;
        }
        
        $.ajax({
            url: action.endpoint,
            type: action.method || 'POST',
            data: action.data,
            success: function(response) {
                LLMWidget.addMessage(action.successMessage || 'Action completed successfully', 'assistant');
                if (action.callback) {
                    eval(action.callback);
                }
            },
            error: function() {
                LLMWidget.addMessage(action.errorMessage || 'Action failed', 'assistant');
            }
        });
    },
    
    getContext: function() {
        // Gather current page context
        return {
            page: window.location.pathname,
            widget_mode: true,
            timestamp: Date.now()
        };
    },
    
    loadHistory: function() {
        // Load last few messages from session
        $.ajax({
            url: '/api/llmassistant/widget/history',
            type: 'GET',
            success: function(response) {
                if (response.messages) {
                    response.messages.forEach(function(msg) {
                        LLMWidget.addMessage(msg.content, msg.sender);
                    });
                }
            }
        });
    },
    
    provideFeedback: function(messageId, feedback) {
        $.ajax({
            url: '/api/llmassistant/widget/feedback',
            type: 'POST',
            data: {
                message_id: messageId,
                feedback: feedback
            }
        });
        
        // Visual feedback
        $('#' + messageId).find('.llm-feedback-btn').removeClass('active');
        $('#' + messageId).find('.llm-feedback-' + feedback).addClass('active');
    }
};

// Initialize when document is ready
$(document).ready(function() {
    LLMWidget.init();
});
</script>