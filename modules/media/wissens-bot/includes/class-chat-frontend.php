<?php
/**
 * Frontend Chat-Widget für Wissens-Bot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wissens_Bot_Frontend {
    
    /**
     * Rendert das Chat-Widget
     */
    public function render_widget($atts) {
        $title = isset($atts['title']) ? esc_html($atts['title']) : __('Wissens-Bot', 'wissens-bot');
        $height = isset($atts['height']) ? esc_attr($atts['height']) : '600px';
        
        ob_start();
        ?>
        <div class="wissens-bot-container" style="height: <?php echo $height; ?>;">
            <div class="wissens-bot-header">
                <h3 class="wissens-bot-title">
                    <span class="wissens-bot-icon">🤖</span>
                    <?php echo $title; ?>
                </h3>
                <div class="wissens-bot-status">
                    <span class="status-indicator"></span>
                    <span class="status-text"><?php _e('Bereit', 'wissens-bot'); ?></span>
                </div>
            </div>
            
            <div class="wissens-bot-messages" id="wissens-bot-messages">
                <div class="wissens-bot-message bot-message">
                    <div class="message-content">
                        <p><?php echo $this->get_welcome_message(); ?></p>
                    </div>
                    <div class="message-timestamp"><?php echo current_time('H:i'); ?></div>
                </div>
            </div>
            
            <div class="wissens-bot-sources" id="wissens-bot-sources" style="display: none;">
                <div class="sources-header">
                    <strong><?php _e('Verwendete Quellen:', 'wissens-bot'); ?></strong>
                </div>
                <div class="sources-list" id="wissens-bot-sources-list"></div>
            </div>
            
            <div class="wissens-bot-input-area">
                <form id="wissens-bot-form" class="wissens-bot-form">
                    <textarea 
                        id="wissens-bot-input" 
                        class="wissens-bot-input" 
                        placeholder="<?php esc_attr_e('Stellen Sie Ihre Frage...', 'wissens-bot'); ?>"
                        rows="2"
                    ></textarea>
                    <button type="submit" class="wissens-bot-submit" id="wissens-bot-submit">
                        <span class="submit-text"><?php _e('Senden', 'wissens-bot'); ?></span>
                        <span class="submit-icon">→</span>
                    </button>
                </form>
                <div class="wissens-bot-hints">
                    <small>
                        <?php 
                        $keywords = get_option('wissens_bot_topic_keywords');
                        if (!empty($keywords)) {
                            printf(
                                __('Themen: %s', 'wissens-bot'),
                                '<strong>' . esc_html($keywords) . '</strong>'
                            );
                        }
                        ?>
                    </small>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generiert Willkommensnachricht
     */
    private function get_welcome_message() {
        $keywords = get_option('wissens_bot_topic_keywords');
        $sources = array();
        
        if (get_option('wissens_bot_sharepoint_enabled')) {
            $sources[] = 'SharePoint';
        }
        if (get_option('wissens_bot_pubmed_enabled')) {
            $sources[] = 'PubMed';
        }
        if (get_option('wissens_bot_scholar_enabled')) {
            $sources[] = 'Google Scholar';
        }
        
        $message = __('Hallo! Ich bin Ihr Wissens-Bot und helfe Ihnen gerne bei Fragen', 'wissens-bot');
        
        if (!empty($keywords)) {
            $message .= ' ' . sprintf(__('zu folgenden Themen: %s.', 'wissens-bot'), '<strong>' . esc_html($keywords) . '</strong>');
        } else {
            $message .= '.';
        }
        
        if (!empty($sources)) {
            $message .= ' ' . sprintf(
                __('Ich durchsuche dabei folgende Quellen: %s.', 'wissens-bot'),
                implode(', ', $sources)
            );
        }
        
        $message .= ' ' . __('Wie kann ich Ihnen helfen?', 'wissens-bot');
        
        return $message;
    }
}
