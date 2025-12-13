(function($) {
    'use strict';
    
    class WissensBot {
        constructor() {
            this.form = $('#wissens-bot-form');
            this.input = $('#wissens-bot-input');
            this.submit = $('#wissens-bot-submit');
            this.messages = $('#wissens-bot-messages');
            this.sourcesContainer = $('#wissens-bot-sources');
            this.sourcesList = $('#wissens-bot-sources-list');
            
            this.conversationHistory = [];
            this.isProcessing = false;
            
            this.init();
        }
        
        init() {
            // Event Listeners
            this.form.on('submit', (e) => this.handleSubmit(e));
            
            // Enter-Taste zum Senden (Shift+Enter für neue Zeile)
            this.input.on('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.form.submit();
                }
            });
            
            // Auto-Resize Textarea
            this.input.on('input', () => this.autoResizeTextarea());
        }
        
        handleSubmit(e) {
            e.preventDefault();
            
            if (this.isProcessing) {
                return;
            }
            
            const message = this.input.val().trim();
            
            if (!message) {
                return;
            }
            
            // User-Nachricht anzeigen
            this.addMessage(message, 'user');
            
            // Input leeren
            this.input.val('');
            this.autoResizeTextarea();
            
            // Nachricht an Backend senden
            this.sendMessage(message);
        }
        
        async sendMessage(message) {
            this.isProcessing = true;
            this.setProcessingState(true);
            
            // Typing Indicator anzeigen
            const typingId = this.showTypingIndicator();
            
            try {
                const response = await $.ajax({
                    url: wissensBotAjax.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wissens_bot_chat',
                        nonce: wissensBotAjax.nonce,
                        message: message,
                        history: JSON.stringify(this.conversationHistory)
                    }
                });
                
                // Typing Indicator entfernen
                this.removeTypingIndicator(typingId);
                
                if (response.success) {
                    // Bot-Antwort hinzufügen
                    this.addMessage(response.data.response, 'bot');
                    
                    // Quellen anzeigen
                    if (response.data.sources && response.data.sources.length > 0) {
                        this.showSources(response.data.sources);
                    } else {
                        this.hideSources();
                    }
                    
                    // Conversation History aktualisieren
                    this.conversationHistory.push({
                        role: 'user',
                        content: message
                    });
                    this.conversationHistory.push({
                        role: 'assistant',
                        content: response.data.response
                    });
                    
                } else {
                    this.showError(response.data.message || wissensBotAjax.strings.error);
                }
                
            } catch (error) {
                this.removeTypingIndicator(typingId);
                this.showError(wissensBotAjax.strings.error);
                console.error('Chat Error:', error);
            }
            
            this.isProcessing = false;
            this.setProcessingState(false);
        }
        
        addMessage(content, type) {
            const timestamp = new Date().toLocaleTimeString('de-DE', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const messageHtml = `
                <div class="wissens-bot-message ${type}-message">
                    <div class="message-content">
                        ${this.formatMessage(content)}
                    </div>
                    <div class="message-timestamp">${timestamp}</div>
                </div>
            `;
            
            this.messages.append(messageHtml);
            this.scrollToBottom();
        }
        
        formatMessage(content) {
            // Markdown-ähnliche Formatierung
            let formatted = content;
            
            // Links
            formatted = formatted.replace(
                /(https?:\/\/[^\s]+)/g,
                '<a href="$1" target="_blank" rel="noopener">$1</a>'
            );
            
            // Fett
            formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            
            // Absätze
            formatted = formatted.replace(/\n\n/g, '</p><p>');
            formatted = '<p>' + formatted + '</p>';
            
            return formatted;
        }
        
        showTypingIndicator() {
            const id = 'typing-' + Date.now();
            const typingHtml = `
                <div class="wissens-bot-message bot-message" id="${id}">
                    <div class="wissens-bot-typing">
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                        <span class="typing-dot"></span>
                    </div>
                </div>
            `;
            
            this.messages.append(typingHtml);
            this.scrollToBottom();
            
            return id;
        }
        
        removeTypingIndicator(id) {
            $('#' + id).remove();
        }
        
        showSources(sources) {
            this.sourcesList.empty();
            
            sources.forEach((source) => {
                let sourceHtml = '';
                
                if (source.type === 'sharepoint') {
                    sourceHtml = `
                        <div class="source-item">
                            <span class="source-badge">SharePoint</span>
                            <span>${source.count} Dokumente gefunden</span>
                        </div>
                    `;
                } else if (source.type === 'pubmed' && source.articles) {
                    source.articles.forEach((article) => {
                        sourceHtml += `
                            <div class="source-item">
                                <span class="source-badge">PubMed</span>
                                <a href="${article.url}" target="_blank" class="source-link">
                                    ${article.title}
                                </a>
                            </div>
                        `;
                    });
                } else if (source.type === 'scholar' && source.articles) {
                    source.articles.forEach((article) => {
                        sourceHtml += `
                            <div class="source-item">
                                <span class="source-badge">Scholar</span>
                                <a href="${article.url}" target="_blank" class="source-link">
                                    ${article.title}
                                </a>
                            </div>
                        `;
                    });
                }
                
                this.sourcesList.append(sourceHtml);
            });
            
            this.sourcesContainer.slideDown(300);
        }
        
        hideSources() {
            this.sourcesContainer.slideUp(300);
        }
        
        showError(message) {
            const errorHtml = `
                <div class="wissens-bot-message bot-message">
                    <div class="message-content error-message">
                        ${message}
                    </div>
                </div>
            `;
            
            this.messages.append(errorHtml);
            this.scrollToBottom();
        }
        
        setProcessingState(processing) {
            if (processing) {
                this.submit.prop('disabled', true);
                this.submit.find('.submit-text').text(wissensBotAjax.strings.thinking);
            } else {
                this.submit.prop('disabled', false);
                this.submit.find('.submit-text').text('Senden');
            }
        }
        
        scrollToBottom() {
            const container = this.messages[0];
            container.scrollTop = container.scrollHeight;
        }
        
        autoResizeTextarea() {
            const textarea = this.input[0];
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }
    }
    
    // Initialisierung wenn DOM bereit ist
    $(document).ready(function() {
        if ($('#wissens-bot-form').length) {
            new WissensBot();
        }
    });
    
})(jQuery);
