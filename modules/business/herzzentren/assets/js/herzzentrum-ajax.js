/**
 * DGPTM Herzzentrum Editor - AJAX Handler
 * Version: 4.0.0
 * 
 * Handles AJAX requests for assigned Herzzentrum names
 */

(function() {
    'use strict';
    
    /**
     * Initialize when DOM is ready
     */
    function init() {
        const outputContainer = document.getElementById('dgptm-assigned-herzzentrum-name-output');
        
        if (!outputContainer) {
            return;
        }
        
        // Check if config is available
        if (typeof dgptmEditorConfig === 'undefined') {
            console.warn('DGPTM Editor: Configuration not found');
            return;
        }
        
        fetchAssignedHerzzentrum();
    }
    
    /**
     * Fetch assigned Herzzentrum name via AJAX
     */
    function fetchAssignedHerzzentrum() {
        const outputContainer = document.getElementById('dgptm-assigned-herzzentrum-name-output');
        
        if (!outputContainer) {
            return;
        }
        
        // Show loading state
        outputContainer.innerHTML = '<span class="dgptm-loading">Lädt...</span>';
        
        // Build URL with nonce
        const url = dgptmEditorConfig.ajaxUrl + 
                   '?action=get_assigned_herzzentrum_name' +
                   '&_wpnonce=' + encodeURIComponent(dgptmEditorConfig.nonce);
        
        // Make AJAX request
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.data && typeof data.data.html !== 'undefined') {
                outputContainer.innerHTML = data.data.html;
            } else {
                outputContainer.innerHTML = '';
            }
        })
        .catch(function(error) {
            console.warn('DGPTM Editor AJAX Error:', error);
            outputContainer.innerHTML = '';
        });
    }
    
    /**
     * Initialize on DOM ready
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
