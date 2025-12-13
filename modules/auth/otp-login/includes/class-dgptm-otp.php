<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'DGPTM_OTP_TTL' ) ) { define( 'DGPTM_OTP_TTL', 600 ); }

add_filter( 'auth_cookie_expiration', function( $seconds, $user_id, $remember ){
    return $remember ? 30 * DAY_IN_SECONDS : $seconds;
}, 10, 3 );

add_shortcode( 'dgptm_otp_login', function( $atts ){
    $atts = shortcode_atts( array('redirect' => home_url( '/' )), $atts, 'dgptm_otp_login' );
    ob_start(); $nonce = wp_create_nonce( 'dgptm_otp_public' ); ?>
    <div id="dgptm-otp-form" data-redirect="<?php echo esc_attr( $atts['redirect'] ); ?>">
        <div class="dgptm-form-header">
            <h2><?php esc_html_e( 'Anmeldung', 'dgptm' ); ?></h2>
        </div>
        
        <div class="dgptm-step-1">
            <div class="dgptm-form-group">
                <label for="dgptm-login-identifier"><?php esc_html_e( 'E-Mail oder Benutzername', 'dgptm' ); ?></label>
                <input 
                    type="text" 
                    id="dgptm-login-identifier" 
                    autocomplete="username" 
                    placeholder="<?php esc_attr_e( 'ihre@email.de', 'dgptm' ); ?>"
                    required
                />
            </div>
            <button type="button" id="dgptm-send-otp" class="dgptm-btn dgptm-btn-primary">
                <span class="dgptm-btn-text"><?php esc_html_e( 'Code senden', 'dgptm' ); ?></span>
                <span class="dgptm-btn-loader" style="display:none">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke-width="3" opacity="0.25"/>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                </span>
            </button>
        </div>
        
        <div class="dgptm-step-2" style="display:none">
            <div class="dgptm-form-group">
                <label for="dgptm-otp-code"><?php esc_html_e( 'Einmal-Code', 'dgptm' ); ?></label>
                <input 
                    type="text" 
                    id="dgptm-otp-code" 
                    inputmode="numeric" 
                    pattern="[0-9]*" 
                    autocomplete="one-time-code"
                    placeholder="<?php esc_attr_e( '000000', 'dgptm' ); ?>"
                    maxlength="6"
                    required
                />
            </div>
            
            <label class="dgptm-checkbox-wrapper">
                <input type="checkbox" id="dgptm-remember" />
                <span class="dgptm-checkbox-label"><?php esc_html_e( 'Angemeldet bleiben (30 Tage)', 'dgptm' ); ?></span>
            </label>
            
            <div class="dgptm-button-group">
                <button type="button" id="dgptm-verify-otp" class="dgptm-btn dgptm-btn-primary">
                    <span class="dgptm-btn-text"><?php esc_html_e( 'Einloggen', 'dgptm' ); ?></span>
                    <span class="dgptm-btn-loader" style="display:none">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10" stroke-width="3" opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
                <button type="button" id="dgptm-back" class="dgptm-btn dgptm-btn-secondary">
                    <?php esc_html_e( 'Zurück', 'dgptm' ); ?>
                </button>
            </div>
        </div>
        
        <div class="dgptm-msg" role="status" aria-live="polite"></div>
        <input type="hidden" id="dgptm-nonce" value="<?php echo esc_attr( $nonce ); ?>" />
    </div>
    <style>
    #dgptm-otp-form {
        max-width: 420px;
        margin: 0 auto;
        padding: 2rem;
        background: #ffffff;
        border: 1px solid #e1e4e8;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    
    .dgptm-form-header {
        margin-bottom: 1.5rem;
        text-align: center;
    }
    
    .dgptm-form-header h2 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
        color: #1a1a1a;
    }
    
    .dgptm-form-group {
        margin-bottom: 1.25rem;
    }
    
    .dgptm-form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        font-weight: 500;
        color: #444;
    }
    
    #dgptm-otp-form input[type="text"] {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #d1d5db;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.2s ease;
        background: #ffffff;
        box-sizing: border-box;
    }
    
    #dgptm-otp-form input[type="text"]:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    #dgptm-otp-form input[type="text"]::placeholder {
        color: #9ca3af;
    }
    
    #dgptm-otp-code {
        text-align: center;
        letter-spacing: 0.25em;
        font-size: 1.25rem;
        font-weight: 600;
    }
    
    .dgptm-checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        margin-bottom: 1.25rem;
        cursor: pointer;
        user-select: none;
    }
    
    .dgptm-checkbox-wrapper input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        margin: 0;
    }
    
    .dgptm-checkbox-label {
        font-size: 0.9rem;
        color: #555;
    }
    
    .dgptm-btn {
        width: 100%;
        padding: 0.875rem 1.25rem;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        position: relative;
    }
    
    .dgptm-btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
    }
    
    .dgptm-btn-primary:hover:not(:disabled) {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
        transform: translateY(-1px);
    }
    
    .dgptm-btn-primary:active:not(:disabled) {
        transform: translateY(0);
    }
    
    .dgptm-btn-secondary {
        background: #f3f4f6;
        color: #374151;
        margin-top: 0.75rem;
    }
    
    .dgptm-btn-secondary:hover:not(:disabled) {
        background: #e5e7eb;
    }
    
    .dgptm-btn:disabled {
        cursor: not-allowed;
        opacity: 0.7;
    }
    
    .dgptm-btn-loader svg {
        animation: dgptm-spin 0.8s linear infinite;
    }
    
    @keyframes dgptm-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .dgptm-button-group {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    
    .dgptm-msg {
        margin-top: 1rem;
        padding: 0.875rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        line-height: 1.5;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .dgptm-msg:empty {
        display: none;
        margin: 0;
        padding: 0;
    }
    
    .dgptm-msg.dgptm-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .dgptm-msg.dgptm-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    .dgptm-msg.dgptm-info {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    
    @media (max-width: 480px) {
        #dgptm-otp-form {
            padding: 1.5rem;
        }
        
        .dgptm-form-header h2 {
            font-size: 1.25rem;
        }
    }
    </style>
    <script>
    (function(){
        'use strict';
        
        // Utility functions
        function post(action, data){
            data = data || {};
            data.action = action;
            return fetch(<?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(data).toString(),
                credentials: 'same-origin'
            }).then(function(r) {
                if (!r.ok) throw new Error('Network response was not ok');
                return r.json();
            });
        }
        
        // DOM elements
        var wrap = document.getElementById('dgptm-otp-form');
        if (!wrap) return;
        
        var step1 = wrap.querySelector('.dgptm-step-1');
        var step2 = wrap.querySelector('.dgptm-step-2');
        var msg = wrap.querySelector('.dgptm-msg');
        var identifier = wrap.querySelector('#dgptm-login-identifier');
        var code = wrap.querySelector('#dgptm-otp-code');
        var remember = wrap.querySelector('#dgptm-remember');
        var nonce = wrap.querySelector('#dgptm-nonce').value;
        var redirect = wrap.getAttribute('data-redirect');
        var sendBtn = wrap.querySelector('#dgptm-send-otp');
        var verifyBtn = wrap.querySelector('#dgptm-verify-otp');
        var backBtn = wrap.querySelector('#dgptm-back');
        
        // Message helper
        function setMsg(text, type) {
            msg.textContent = text || '';
            msg.className = 'dgptm-msg';
            if (type) msg.classList.add('dgptm-' + type);
        }
        
        // Button loading state
        function setButtonLoading(button, loading) {
            var text = button.querySelector('.dgptm-btn-text');
            var loader = button.querySelector('.dgptm-btn-loader');
            if (loading) {
                button.disabled = true;
                if (text) text.style.display = 'none';
                if (loader) loader.style.display = 'inline-block';
            } else {
                button.disabled = false;
                if (text) text.style.display = 'inline';
                if (loader) loader.style.display = 'none';
            }
        }
        
        // Input validation
        function validateIdentifier() {
            var val = identifier.value.trim();
            return val.length > 0;
        }
        
        function validateCode() {
            var val = code.value.trim();
            return /^\d{6}$/.test(val);
        }
        
        // Send OTP
        function sendOTP() {
            if (!validateIdentifier()) {
                setMsg('<?php echo esc_js( __( 'Bitte E-Mail oder Benutzername angeben.', 'dgptm' ) ); ?>', 'error');
                identifier.focus();
                return;
            }
            
            setMsg('<?php echo esc_js( __( 'Sende Code…', 'dgptm' ) ); ?>', 'info');
            setButtonLoading(sendBtn, true);
            
            post('dgptm_request_otp', {
                identifier: identifier.value,
                _ajax_nonce: nonce
            }).then(function(res){
                setButtonLoading(sendBtn, false);
                if (res && res.success) {
                    setMsg(res.message, 'success');
                    step1.style.display = 'none';
                    step2.style.display = 'block';
                    setTimeout(function(){ code.focus(); }, 100);
                } else {
                    setMsg((res && res.message) || '<?php echo esc_js( __( 'Fehler beim Senden.', 'dgptm' ) ); ?>', 'error');
                }
            }).catch(function(){
                setButtonLoading(sendBtn, false);
                setMsg('<?php echo esc_js( __( 'Netzwerkfehler.', 'dgptm' ) ); ?>', 'error');
            });
        }
        
        // Verify OTP
        function verifyOTP() {
            if (!validateCode()) {
                setMsg('<?php echo esc_js( __( 'Bitte 6-stelligen Code eingeben.', 'dgptm' ) ); ?>', 'error');
                code.focus();
                return;
            }
            
            setMsg('<?php echo esc_js( __( 'Prüfe Code…', 'dgptm' ) ); ?>', 'info');
            setButtonLoading(verifyBtn, true);
            
            post('dgptm_verify_otp', {
                identifier: identifier.value,
                otp: code.value,
                remember: remember.checked ? 1 : 0,
                _ajax_nonce: nonce
            }).then(function(res){
                setButtonLoading(verifyBtn, false);
                if (res && res.success) {
                    setMsg(res.message, 'success');
                    setTimeout(function(){
                        window.location = redirect || <?php echo json_encode( home_url( '/' ) ); ?>;
                    }, 500);
                } else {
                    setMsg((res && res.message) || '<?php echo esc_js( __( 'Code ungültig.', 'dgptm' ) ); ?>', 'error');
                    code.select();
                }
            }).catch(function(){
                setButtonLoading(verifyBtn, false);
                setMsg('<?php echo esc_js( __( 'Netzwerkfehler.', 'dgptm' ) ); ?>', 'error');
            });
        }
        
        // Back button
        function goBack() {
            step2.style.display = 'none';
            step1.style.display = 'block';
            code.value = '';
            setMsg('');
            setTimeout(function(){ identifier.focus(); }, 100);
        }
        
        // Event listeners
        sendBtn.addEventListener('click', sendOTP);
        verifyBtn.addEventListener('click', verifyOTP);
        backBtn.addEventListener('click', goBack);
        
        // Enter key support - Step 1
        identifier.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                sendOTP();
            }
        });
        
        // Enter key support - Step 2
        code.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                verifyOTP();
            }
        });
        
        // Auto-format code input (only numbers)
        code.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
        
        // Focus on load
        setTimeout(function(){
            if (step1.style.display !== 'none') {
                identifier.focus();
            }
        }, 100);
    })();
    </script>
    <?php return ob_get_clean();
});

// Rate limiting functions with improved security
function dgptm_rate_key( $identifier ) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
    $id = strtolower( trim( (string) $identifier ) );
    // Use wp_hash for better security
    return 'dgptm_otp_rate_' . wp_hash( $ip . '|' . $id );
}

function dgptm_rate_check_and_inc( $identifier ) {
    $limit = (int) dgptm_get_option( 'dgptm_otp_rate_limit', 3 );
    $key   = dgptm_rate_key( $identifier );
    $count = (int) get_transient( $key );
    if ( $count >= $limit ) { 
        return false; 
    }
    set_transient( $key, $count + 1, DGPTM_OTP_TTL );
    return true;
}

function dgptm_rate_reset( $identifier ) { 
    delete_transient( dgptm_rate_key( $identifier ) ); 
}

// User lookup with improved sanitization
function dgptm_find_user_by_identifier( $identifier ) {
    $identifier = trim( (string) $identifier );
    
    if ( empty( $identifier ) ) {
        return null;
    }
    
    // Try email first
    if ( is_email( $identifier ) ) {
        $user = get_user_by( 'email', sanitize_email( $identifier ) );
        if ( $user ) return $user;
    }
    
    // Try login name
    $user = get_user_by( 'login', sanitize_user( $identifier ) );
    if ( $user ) return $user;
    
    // Try slug
    $user = get_user_by( 'slug', sanitize_title( $identifier ) );
    return $user ?: null;
}

// Generate secure OTP
function dgptm_generate_otp() {
    // Use wp_rand for better randomness
    $otp = '';
    for ( $i = 0; $i < 6; $i++ ) {
        $otp .= (string) wp_rand( 0, 9 );
    }
    return $otp;
}

// Store OTP securely
function dgptm_store_otp_for_user( $user_id, $otp ) {
    $hash = wp_hash_password( $otp );
    update_user_meta( $user_id, '_dgptm_otp_hash', $hash );
    update_user_meta( $user_id, '_dgptm_otp_expires', time() + DGPTM_OTP_TTL );
    update_user_meta( $user_id, '_dgptm_otp_attempts', 0 ); // Reset attempts
}

// Verify OTP with attempt limiting
function dgptm_verify_user_otp( $user_id, $otp ) {
    $hash = (string) get_user_meta( $user_id, '_dgptm_otp_hash', true );
    $exp  = (int) get_user_meta( $user_id, '_dgptm_otp_expires', true );
    $attempts = (int) get_user_meta( $user_id, '_dgptm_otp_attempts', true );
    
    // Check expiry
    if ( ! $hash || ! $exp || time() > $exp ) {
        return false;
    }
    
    // Max 5 verification attempts per OTP
    if ( $attempts >= 5 ) {
        delete_user_meta( $user_id, '_dgptm_otp_hash' );
        delete_user_meta( $user_id, '_dgptm_otp_expires' );
        delete_user_meta( $user_id, '_dgptm_otp_attempts' );
        return false;
    }
    
    // Verify password
    $valid = wp_check_password( (string) $otp, $hash );
    
    if ( ! $valid ) {
        // Increment failed attempts
        update_user_meta( $user_id, '_dgptm_otp_attempts', $attempts + 1 );
    }
    
    return $valid;
}

// Send OTP email
function dgptm_send_otp_mail( $user, $otp ) {
    $subject_tpl = (string) dgptm_get_option( 'dgptm_email_subject', 'Ihr Login-Code für {site_name}' );
    $body_tpl    = (string) dgptm_get_option( 'dgptm_email_body', "Hallo {user_login},\n\nIhr Einmal-Code lautet: {otp}\nEr ist {otp_valid_minutes} Minuten gültig.\n\nViele Grüße\n{site_name}" );
    
    $repl = array(
        '{site_name}'          => wp_specialchars_decode( get_bloginfo('name'), ENT_QUOTES ),
        '{user_login}'         => $user->user_login,
        '{user_email}'         => $user->user_email,
        '{display_name}'       => $user->display_name,
        '{otp}'                => $otp,
        '{code}'               => $otp, // Alias für {otp}
        '{otp_valid_minutes}'  => (string) floor( DGPTM_OTP_TTL / 60 ),
    );
    
    $subject = strtr( $subject_tpl, $repl );
    $body    = strtr( $body_tpl, $repl );
    
    $ok = wp_mail( 
        $user->user_email, 
        $subject, 
        $body, 
        array( 'Content-Type: text/plain; charset=UTF-8' ) 
    );
    
    // Optional webhook (ohne OTP aus Sicherheitsgründen)
    $wh_on = (int) dgptm_get_option( 'dgptm_webhook_enable', 0 );
    $wh    = (string) dgptm_get_option( 'dgptm_webhook_url', '' );
    
    if ( $wh_on && $wh && filter_var( $wh, FILTER_VALIDATE_URL ) ) {
        wp_remote_post( esc_url_raw( $wh ), array(
            'timeout'  => 5,
            'blocking' => false,
            'headers'  => array( 'Content-Type' => 'application/json' ),
            'body'     => wp_json_encode( array(
                'event'            => 'otp_sent',
                'user_login'       => $user->user_login,
                'user_email_hash'  => wp_hash( strtolower( $user->user_email ) ),
                'time'             => time(),
            )),
        ));
    }
    
    return $ok;
}

// AJAX: Request OTP
function dgptm_ajax_request_otp() {
    check_ajax_referer( 'dgptm_otp_public' );
    
    $identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
    
    if ( $identifier === '' ) {
        wp_send_json( array(
            'success' => false,
            'message' => __( 'Bitte E-Mail oder Benutzername angeben.', 'dgptm' )
        ));
    }
    
    // Rate limiting
    if ( ! dgptm_rate_check_and_inc( $identifier ) ) {
        wp_send_json( array(
            'success' => false,
            'message' => __( 'Zu viele Versuche. Bitte später erneut versuchen.', 'dgptm' )
        ));
    }
    
    $user = dgptm_find_user_by_identifier( $identifier );
    
    // Don't reveal if user exists (security)
    if ( ! $user ) {
        wp_send_json( array(
            'success' => true,
            'message' => __( 'Wenn ein Konto existiert, wurde ein Code gesendet.', 'dgptm' )
        ));
    }
    
    $otp = dgptm_generate_otp();
    dgptm_store_otp_for_user( $user->ID, $otp );
    $sent = dgptm_send_otp_mail( $user, $otp );
    
    if ( $sent ) {
        $masked_email = substr( $user->user_email, 0, 2 ) . '***@***';
        wp_send_json( array(
            'success' => true,
            'message' => sprintf( __( 'Code gesendet an %s.', 'dgptm' ), esc_html( $masked_email ) )
        ));
    } else {
        wp_send_json( array(
            'success' => false,
            'message' => __( 'E-Mail konnte nicht gesendet werden.', 'dgptm' )
        ));
    }
}
add_action( 'wp_ajax_nopriv_dgptm_request_otp', 'dgptm_ajax_request_otp' );
add_action( 'wp_ajax_dgptm_request_otp', 'dgptm_ajax_request_otp' );

// AJAX: Verify OTP
function dgptm_ajax_verify_otp() {
    check_ajax_referer( 'dgptm_otp_public' );
    
    $identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
    $otp        = isset( $_POST['otp'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['otp'] ) ) : '';
    $remember   = isset( $_POST['remember'] ) ? (bool) intval( $_POST['remember'] ) : false;
    
    if ( $identifier === '' || $otp === '' ) {
        wp_send_json( array(
            'success' => false,
            'message' => __( 'Bitte alle Felder ausfüllen.', 'dgptm' )
        ));
    }
    
    $user = dgptm_find_user_by_identifier( $identifier );
    
    if ( ! $user ) {
        wp_send_json( array(
            'success' => false,
            'message' => __( 'Code ungültig oder abgelaufen.', 'dgptm' )
        ));
    }
    
    if ( ! dgptm_verify_user_otp( $user->ID, $otp ) ) {
        wp_send_json( array(
            'success' => false,
            'message' => __( 'Code ungültig oder abgelaufen.', 'dgptm' )
        ));
    }
    
    // Clean up OTP data
    delete_user_meta( $user->ID, '_dgptm_otp_hash' );
    delete_user_meta( $user->ID, '_dgptm_otp_expires' );
    delete_user_meta( $user->ID, '_dgptm_otp_attempts' );
    dgptm_rate_reset( $identifier );
    
    // Log in user
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, $remember );
    do_action( 'wp_login', $user->user_login, $user );
    
    wp_send_json( array(
        'success' => true,
        'message' => __( 'Erfolgreich eingeloggt. Einen Moment…', 'dgptm' )
    ));
}
add_action( 'wp_ajax_nopriv_dgptm_verify_otp', 'dgptm_ajax_verify_otp' );
add_action( 'wp_ajax_dgptm_verify_otp', 'dgptm_ajax_verify_otp' );
