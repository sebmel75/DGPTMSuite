<?php
/**
 * Publication Frontend Manager - Enhanced Reviewer Panel
 *
 * Provides:
 * - [pfm_reviewer_panel] shortcode showing assigned reviews only
 * - Editor in Chief: Add/create reviewers
 * - Integrated file upload with SharePoint support
 * - Required rating before submission
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Reviewer_Panel {

    /**
     * Initialize the reviewer panel
     */
    public static function init() {
        // Register shortcodes
        add_shortcode('pfm_reviewer_panel', array(__CLASS__, 'render_reviewer_panel'));
        add_shortcode('pfm_review_form', array(__CLASS__, 'render_review_form'));
        add_shortcode('pfm_is_assigned_reviewer', array(__CLASS__, 'render_is_assigned_reviewer'));

        // AJAX handlers
        add_action('wp_ajax_pfm_add_reviewer', array(__CLASS__, 'ajax_add_reviewer'));
        add_action('wp_ajax_pfm_assign_reviewer_to_publication', array(__CLASS__, 'ajax_assign_reviewer'));
        add_action('wp_ajax_pfm_remove_reviewer_assignment', array(__CLASS__, 'ajax_remove_reviewer'));
        add_action('wp_ajax_pfm_submit_review_enhanced', array(__CLASS__, 'ajax_submit_review'));
        add_action('wp_ajax_pfm_get_available_reviewers', array(__CLASS__, 'ajax_get_reviewers'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'maybe_enqueue_assets'));
    }

    /**
     * Conditionally enqueue assets
     */
    public static function maybe_enqueue_assets() {
        global $post;

        if (is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'pfm_reviewer_panel') ||
            has_shortcode($post->post_content, 'pfm_review_form')
        )) {
            self::enqueue_assets();
        }
    }

    /**
     * Enqueue CSS and JavaScript
     */
    public static function enqueue_assets() {
        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'pfm-reviewer-panel',
            PFM_URL . 'assets/css/reviewer-panel.css',
            array(),
            PFM_VERSION
        );

        wp_enqueue_script(
            'pfm-reviewer-panel',
            PFM_URL . 'assets/js/reviewer-panel.js',
            array('jquery'),
            PFM_VERSION,
            true
        );

        wp_localize_script('pfm-reviewer-panel', 'pfm_reviewer_panel', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pfm_reviewer_panel_nonce'),
            'i18n' => array(
                'confirm_remove' => __('Reviewer-Zuweisung wirklich entfernen?', PFM_TD),
                'uploading' => __('Wird hochgeladen...', PFM_TD),
                'submit_success' => __('Review erfolgreich eingereicht!', PFM_TD),
                'submit_error' => __('Fehler beim Einreichen des Reviews.', PFM_TD),
                'rating_required' => __('Bitte wählen Sie eine Empfehlung aus.', PFM_TD),
                'reviewer_added' => __('Reviewer erfolgreich hinzugefügt.', PFM_TD),
                'reviewer_assigned' => __('Reviewer erfolgreich zugewiesen.', PFM_TD),
            ),
        ));
    }

    /**
     * Render the reviewer panel showing assigned reviews
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_reviewer_panel($atts) {
        if (!is_user_logged_in()) {
            return '<div class="pfm-notice pfm-notice-error">' . __('Bitte melden Sie sich an, um auf das Reviewer-Panel zuzugreifen.', PFM_TD) . '</div>';
        }

        $atts = shortcode_atts(array(
            'show_completed' => 'yes',
        ), $atts);

        $user_id = get_current_user_id();
        $is_eic = pfm_user_is_editor_in_chief();
        $is_ed = pfm_user_is_redaktion();
        $is_rev = pfm_user_is_reviewer();

        // Check permission
        if (!$is_eic && !$is_ed && !$is_rev) {
            return '<div class="pfm-notice pfm-notice-error">' . __('Sie haben keine Berechtigung für das Reviewer-Panel.', PFM_TD) . '</div>';
        }

        // Get assigned publications
        $assigned_publications = self::get_assigned_publications($user_id, $is_eic, $is_ed);

        ob_start();
        ?>
        <div class="pfm-reviewer-panel">
            <div class="panel-header">
                <h2>
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php _e('Meine Review-Aufgaben', PFM_TD); ?>
                </h2>
                <?php if ($is_eic || $is_ed): ?>
                    <span class="role-badge eic"><?php echo $is_eic ? __('Editor in Chief', PFM_TD) : __('Redaktion', PFM_TD); ?></span>
                <?php else: ?>
                    <span class="role-badge reviewer"><?php _e('Reviewer', PFM_TD); ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($assigned_publications)): ?>
                <div class="pfm-notice pfm-notice-info">
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Ihnen sind derzeit keine Reviews zugewiesen.', PFM_TD); ?>
                </div>
            <?php else: ?>
                <div class="review-tasks-list">
                    <?php foreach ($assigned_publications as $pub): ?>
                        <?php echo self::render_publication_card($pub, $user_id, $is_eic, $is_ed); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($is_eic): ?>
                <?php echo self::render_reviewer_management(); ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get publications assigned to reviewer
     */
    private static function get_assigned_publications($user_id, $is_eic, $is_ed) {
        $args = array(
            'post_type' => 'publikation',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // For reviewers: only show assigned publications
        if (!$is_eic && !$is_ed) {
            $args['meta_query'] = array(
                array(
                    'key' => 'pfm_assigned_reviewers',
                    'value' => serialize(strval($user_id)),
                    'compare' => 'LIKE',
                ),
            );
        } else {
            // For EIC/editors: show publications under review
            $args['meta_query'] = array(
                array(
                    'key' => 'review_status',
                    'value' => array('submitted', 'under_review'),
                    'compare' => 'IN',
                ),
            );
        }

        $query = new WP_Query($args);
        $publications = array();

        foreach ($query->posts as $post) {
            $assigned_reviewers = get_post_meta($post->ID, 'pfm_assigned_reviewers', true);
            if (!is_array($assigned_reviewers)) {
                $assigned_reviewers = array();
            }

            // Check if user has already submitted review
            $user_review = self::get_user_review($post->ID, $user_id);

            $publications[] = array(
                'post' => $post,
                'assigned_reviewers' => $assigned_reviewers,
                'deadline' => get_post_meta($post->ID, 'pfm_review_deadline', true),
                'status' => get_post_meta($post->ID, 'review_status', true),
                'user_review' => $user_review,
                'all_reviews' => self::get_all_reviews($post->ID),
            );
        }

        return $publications;
    }

    /**
     * Get user's review for a publication
     */
    private static function get_user_review($post_id, $user_id) {
        $reviews = get_comments(array(
            'post_id' => $post_id,
            'user_id' => $user_id,
            'type' => 'pfm_review',
            'status' => 'approve',
            'number' => 1,
        ));

        return !empty($reviews) ? $reviews[0] : null;
    }

    /**
     * Get all reviews for a publication
     */
    private static function get_all_reviews($post_id) {
        return get_comments(array(
            'post_id' => $post_id,
            'type' => 'pfm_review',
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'DESC',
        ));
    }

    /**
     * Render a publication card
     */
    private static function render_publication_card($pub, $user_id, $is_eic, $is_ed) {
        $post = $pub['post'];
        $deadline = $pub['deadline'];
        $user_review = $pub['user_review'];
        $has_submitted = !empty($user_review);
        $is_overdue = $deadline && strtotime($deadline) < time() && !$has_submitted;

        $autoren = get_post_meta($post->ID, 'autoren', true);
        $abstract = get_post_meta($post->ID, 'pfm_abstract', true);

        ob_start();
        ?>
        <div class="publication-card <?php echo $has_submitted ? 'completed' : ''; ?> <?php echo $is_overdue ? 'overdue' : ''; ?>" data-post-id="<?php echo esc_attr($post->ID); ?>">
            <div class="card-header">
                <h3><?php echo esc_html($post->post_title); ?></h3>
                <div class="card-badges">
                    <?php if ($has_submitted): ?>
                        <span class="badge badge-success">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php _e('Review eingereicht', PFM_TD); ?>
                        </span>
                    <?php elseif ($is_overdue): ?>
                        <span class="badge badge-danger">
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('Überfällig', PFM_TD); ?>
                        </span>
                    <?php else: ?>
                        <span class="badge badge-pending">
                            <span class="dashicons dashicons-clock"></span>
                            <?php _e('Ausstehend', PFM_TD); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-meta">
                <?php if ($autoren): ?>
                    <div class="meta-item">
                        <span class="dashicons dashicons-admin-users"></span>
                        <span><?php echo esc_html($autoren); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($deadline): ?>
                    <div class="meta-item <?php echo $is_overdue ? 'overdue' : ''; ?>">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span><?php printf(__('Deadline: %s', PFM_TD), date_i18n('d.m.Y', strtotime($deadline))); ?></span>
                    </div>
                <?php endif; ?>

                <div class="meta-item">
                    <span class="dashicons dashicons-clock"></span>
                    <span><?php printf(__('Eingereicht: %s', PFM_TD), get_the_date('d.m.Y', $post)); ?></span>
                </div>
            </div>

            <?php if ($abstract): ?>
                <div class="card-abstract">
                    <strong><?php _e('Abstract:', PFM_TD); ?></strong>
                    <p><?php echo wp_trim_words(wp_strip_all_tags($abstract), 50); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($is_eic || $is_ed): ?>
                <?php echo self::render_reviewer_assignments($post->ID, $pub['assigned_reviewers'], $pub['all_reviews']); ?>
            <?php endif; ?>

            <div class="card-actions">
                <?php
                // Download manuscript link
                $manuscript_id = get_post_meta($post->ID, 'pfm_manuscript_attachment_id', true);
                $sp_item_id = get_post_meta($post->ID, '_pfm_current_sp_item_id', true);

                if ($sp_item_id || $manuscript_id):
                ?>
                    <a href="<?php echo esc_url(self::get_manuscript_download_url($post->ID)); ?>" class="btn btn-secondary" target="_blank">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Manuskript', PFM_TD); ?>
                    </a>
                <?php endif; ?>

                <?php if (!$has_submitted): ?>
                    <button class="btn btn-primary open-review-form" data-post-id="<?php echo esc_attr($post->ID); ?>">
                        <span class="dashicons dashicons-edit"></span>
                        <?php _e('Review einreichen', PFM_TD); ?>
                    </button>
                <?php else: ?>
                    <button class="btn btn-outline view-review" data-comment-id="<?php echo esc_attr($user_review->comment_ID); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Mein Review ansehen', PFM_TD); ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!$has_submitted): ?>
                <div class="review-form-container" id="review-form-<?php echo esc_attr($post->ID); ?>" style="display:none;">
                    <?php echo self::render_inline_review_form($post->ID); ?>
                </div>
            <?php endif; ?>

            <?php if ($has_submitted): ?>
                <div class="review-details-container" id="review-details-<?php echo esc_attr($user_review->comment_ID); ?>" style="display:none;">
                    <?php echo self::render_review_details($user_review); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render reviewer assignments section (for EIC)
     */
    private static function render_reviewer_assignments($post_id, $assigned_reviewers, $all_reviews) {
        ob_start();
        ?>
        <div class="reviewer-assignments">
            <h4>
                <span class="dashicons dashicons-groups"></span>
                <?php _e('Zugewiesene Reviewer', PFM_TD); ?>
                <button class="btn-icon add-reviewer-btn" data-post-id="<?php echo esc_attr($post_id); ?>" title="<?php esc_attr_e('Reviewer hinzufügen', PFM_TD); ?>">
                    <span class="dashicons dashicons-plus-alt"></span>
                </button>
            </h4>

            <?php if (empty($assigned_reviewers)): ?>
                <p class="no-reviewers"><?php _e('Noch keine Reviewer zugewiesen.', PFM_TD); ?></p>
            <?php else: ?>
                <ul class="reviewer-list">
                    <?php foreach ($assigned_reviewers as $reviewer_id): ?>
                        <?php
                        $reviewer = get_userdata($reviewer_id);
                        if (!$reviewer) continue;

                        $review = null;
                        foreach ($all_reviews as $r) {
                            if ($r->user_id == $reviewer_id) {
                                $review = $r;
                                break;
                            }
                        }
                        ?>
                        <li class="reviewer-item <?php echo $review ? 'has-review' : ''; ?>">
                            <div class="reviewer-info">
                                <span class="reviewer-name"><?php echo esc_html($reviewer->display_name); ?></span>
                                <span class="reviewer-email"><?php echo esc_html($reviewer->user_email); ?></span>
                            </div>
                            <div class="reviewer-status">
                                <?php if ($review): ?>
                                    <?php $rec = get_comment_meta($review->comment_ID, 'pfm_recommendation', true); ?>
                                    <span class="status-badge status-<?php echo esc_attr($rec); ?>">
                                        <?php echo esc_html(self::get_recommendation_label($rec)); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-pending"><?php _e('Ausstehend', PFM_TD); ?></span>
                                <?php endif; ?>
                                <button class="btn-icon remove-reviewer-btn" data-post-id="<?php echo esc_attr($post_id); ?>" data-reviewer-id="<?php echo esc_attr($reviewer_id); ?>" title="<?php esc_attr_e('Entfernen', PFM_TD); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Add Reviewer Modal Trigger Area -->
            <div class="add-reviewer-form" id="add-reviewer-form-<?php echo esc_attr($post_id); ?>" style="display:none;">
                <?php echo self::render_add_reviewer_form($post_id); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the add reviewer form
     */
    private static function render_add_reviewer_form($post_id) {
        ob_start();
        ?>
        <div class="add-reviewer-panel">
            <h5><?php _e('Reviewer hinzufügen', PFM_TD); ?></h5>

            <div class="form-tabs">
                <button class="tab-btn active" data-tab="existing"><?php _e('Vorhandene', PFM_TD); ?></button>
                <button class="tab-btn" data-tab="new"><?php _e('Neu anlegen', PFM_TD); ?></button>
            </div>

            <div class="tab-content active" id="tab-existing-<?php echo esc_attr($post_id); ?>">
                <div class="form-group">
                    <label><?php _e('Reviewer auswählen:', PFM_TD); ?></label>
                    <select class="existing-reviewer-select" data-post-id="<?php echo esc_attr($post_id); ?>">
                        <option value=""><?php _e('-- Reviewer auswählen --', PFM_TD); ?></option>
                        <?php
                        $reviewers = pfm_get_all_reviewers();
                        $assigned = (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true);
                        foreach ($reviewers as $reviewer):
                            if (in_array($reviewer->ID, $assigned)) continue;
                        ?>
                            <option value="<?php echo esc_attr($reviewer->ID); ?>">
                                <?php echo esc_html($reviewer->display_name); ?> (<?php echo esc_html($reviewer->user_email); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary assign-existing-reviewer" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <?php _e('Zuweisen', PFM_TD); ?>
                </button>
            </div>

            <div class="tab-content" id="tab-new-<?php echo esc_attr($post_id); ?>">
                <div class="form-group">
                    <label><?php _e('E-Mail-Adresse:', PFM_TD); ?> <span class="required">*</span></label>
                    <input type="email" class="new-reviewer-email" placeholder="reviewer@example.com" required>
                </div>
                <div class="form-group">
                    <label><?php _e('Name:', PFM_TD); ?> <span class="required">*</span></label>
                    <input type="text" class="new-reviewer-name" placeholder="<?php esc_attr_e('Vorname Nachname', PFM_TD); ?>" required>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" class="send-invitation" checked>
                        <?php _e('Einladungs-E-Mail senden', PFM_TD); ?>
                    </label>
                </div>
                <button class="btn btn-primary create-and-assign-reviewer" data-post-id="<?php echo esc_attr($post_id); ?>">
                    <?php _e('Anlegen & Zuweisen', PFM_TD); ?>
                </button>
            </div>

            <div class="form-group deadline-group">
                <label><?php _e('Review-Deadline:', PFM_TD); ?></label>
                <input type="date" class="review-deadline" value="<?php echo esc_attr(date('Y-m-d', strtotime('+21 days'))); ?>">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render inline review form
     */
    private static function render_inline_review_form($post_id) {
        $post = get_post($post_id);

        ob_start();
        ?>
        <form class="pfm-review-form" data-post-id="<?php echo esc_attr($post_id); ?>">
            <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

            <div class="form-section">
                <h4><?php _e('Ihre Bewertung', PFM_TD); ?> <span class="required">*</span></h4>

                <div class="recommendation-options">
                    <label class="recommendation-option">
                        <input type="radio" name="recommendation" value="accept" required>
                        <span class="option-card accept">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <span class="option-label"><?php _e('Akzeptieren', PFM_TD); ?></span>
                            <span class="option-desc"><?php _e('Publikation ohne Änderungen annehmen', PFM_TD); ?></span>
                        </span>
                    </label>

                    <label class="recommendation-option">
                        <input type="radio" name="recommendation" value="minor" required>
                        <span class="option-card minor">
                            <span class="dashicons dashicons-edit"></span>
                            <span class="option-label"><?php _e('Kleinere Revision', PFM_TD); ?></span>
                            <span class="option-desc"><?php _e('Geringfügige Überarbeitungen erforderlich', PFM_TD); ?></span>
                        </span>
                    </label>

                    <label class="recommendation-option">
                        <input type="radio" name="recommendation" value="major" required>
                        <span class="option-card major">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <span class="option-label"><?php _e('Größere Revision', PFM_TD); ?></span>
                            <span class="option-desc"><?php _e('Umfangreiche Überarbeitungen nötig', PFM_TD); ?></span>
                        </span>
                    </label>

                    <label class="recommendation-option">
                        <input type="radio" name="recommendation" value="reject" required>
                        <span class="option-card reject">
                            <span class="dashicons dashicons-dismiss"></span>
                            <span class="option-label"><?php _e('Ablehnen', PFM_TD); ?></span>
                            <span class="option-desc"><?php _e('Publikation nicht zur Veröffentlichung geeignet', PFM_TD); ?></span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="form-section">
                <h4><?php _e('Kommentare an Autor:innen', PFM_TD); ?></h4>
                <textarea name="comments_to_author" rows="6" placeholder="<?php esc_attr_e('Diese Kommentare werden den Autor:innen übermittelt...', PFM_TD); ?>"></textarea>
            </div>

            <div class="form-section">
                <h4><?php _e('Vertrauliche Kommentare an die Redaktion', PFM_TD); ?></h4>
                <textarea name="confidential_comments" rows="4" placeholder="<?php esc_attr_e('Diese Kommentare sehen nur die Redakteur:innen...', PFM_TD); ?>"></textarea>
            </div>

            <div class="form-section">
                <h4><?php _e('Gutachten-Datei (optional)', PFM_TD); ?></h4>
                <div class="file-upload-area">
                    <input type="file" name="review_file" id="review-file-<?php echo esc_attr($post_id); ?>" accept=".pdf,.doc,.docx">
                    <label for="review-file-<?php echo esc_attr($post_id); ?>" class="file-upload-label">
                        <span class="dashicons dashicons-upload"></span>
                        <span><?php _e('Datei auswählen oder hierher ziehen', PFM_TD); ?></span>
                        <span class="file-name"></span>
                    </label>
                    <p class="description"><?php _e('PDF oder Word-Dokument, max. 20 MB', PFM_TD); ?></p>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary cancel-review">
                    <?php _e('Abbrechen', PFM_TD); ?>
                </button>
                <button type="submit" class="btn btn-primary submit-review">
                    <span class="btn-text"><?php _e('Review einreichen', PFM_TD); ?></span>
                    <span class="btn-loading" style="display:none;">
                        <span class="spinner"></span>
                        <?php _e('Wird gesendet...', PFM_TD); ?>
                    </span>
                </button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render review details
     */
    private static function render_review_details($review) {
        $rec = get_comment_meta($review->comment_ID, 'pfm_recommendation', true);
        $cta = get_comment_meta($review->comment_ID, 'pfm_comments_to_author', true);
        $cte = get_comment_meta($review->comment_ID, 'pfm_confidential_to_editor', true);
        $attachment_id = get_comment_meta($review->comment_ID, 'pfm_review_attachment_id', true);
        $sp_version_id = get_comment_meta($review->comment_ID, 'pfm_review_sp_version_id', true);

        ob_start();
        ?>
        <div class="review-details">
            <div class="detail-item">
                <strong><?php _e('Empfehlung:', PFM_TD); ?></strong>
                <span class="status-badge status-<?php echo esc_attr($rec); ?>">
                    <?php echo esc_html(self::get_recommendation_label($rec)); ?>
                </span>
            </div>

            <div class="detail-item">
                <strong><?php _e('Eingereicht am:', PFM_TD); ?></strong>
                <span><?php echo esc_html(mysql2date('d.m.Y H:i', $review->comment_date)); ?></span>
            </div>

            <?php if ($cta): ?>
                <div class="detail-item">
                    <strong><?php _e('Kommentare an Autor:innen:', PFM_TD); ?></strong>
                    <div class="comment-text"><?php echo wp_kses_post(nl2br($cta)); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($cte && (pfm_user_is_editor_in_chief() || pfm_user_is_redaktion())): ?>
                <div class="detail-item confidential">
                    <strong><?php _e('Vertrauliche Kommentare:', PFM_TD); ?></strong>
                    <div class="comment-text"><?php echo wp_kses_post(nl2br($cte)); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($attachment_id || $sp_version_id): ?>
                <div class="detail-item">
                    <strong><?php _e('Angehängte Datei:', PFM_TD); ?></strong>
                    <?php if ($sp_version_id): ?>
                        <button class="btn btn-small pfm-sp-download" data-version-id="<?php echo esc_attr($sp_version_id); ?>">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Herunterladen (SharePoint)', PFM_TD); ?>
                        </button>
                    <?php elseif ($attachment_id): ?>
                        <a href="<?php echo esc_url(wp_get_attachment_url($attachment_id)); ?>" class="btn btn-small" target="_blank">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Herunterladen', PFM_TD); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render reviewer management section
     */
    private static function render_reviewer_management() {
        ob_start();
        ?>
        <div class="reviewer-management-section">
            <h3>
                <span class="dashicons dashicons-admin-users"></span>
                <?php _e('Reviewer-Verwaltung', PFM_TD); ?>
            </h3>

            <div class="management-grid">
                <div class="management-card">
                    <h4><?php _e('Neuen Reviewer anlegen', PFM_TD); ?></h4>
                    <form class="create-reviewer-form">
                        <div class="form-group">
                            <label><?php _e('E-Mail:', PFM_TD); ?> <span class="required">*</span></label>
                            <input type="email" name="email" required placeholder="email@example.com">
                        </div>
                        <div class="form-group">
                            <label><?php _e('Name:', PFM_TD); ?> <span class="required">*</span></label>
                            <input type="text" name="display_name" required placeholder="<?php esc_attr_e('Vorname Nachname', PFM_TD); ?>">
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="send_welcome" checked>
                                <?php _e('Willkommens-E-Mail senden', PFM_TD); ?>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php _e('Reviewer anlegen', PFM_TD); ?>
                        </button>
                    </form>
                </div>

                <div class="management-card">
                    <h4><?php _e('Aktive Reviewer', PFM_TD); ?></h4>
                    <ul class="all-reviewers-list">
                        <?php
                        $reviewers = pfm_get_all_reviewers();
                        if (empty($reviewers)):
                        ?>
                            <li class="no-reviewers"><?php _e('Keine Reviewer vorhanden.', PFM_TD); ?></li>
                        <?php else: ?>
                            <?php foreach ($reviewers as $reviewer): ?>
                                <li>
                                    <span class="reviewer-avatar"><?php echo get_avatar($reviewer->ID, 32); ?></span>
                                    <span class="reviewer-name"><?php echo esc_html($reviewer->display_name); ?></span>
                                    <span class="reviewer-email"><?php echo esc_html($reviewer->user_email); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get recommendation label
     */
    public static function get_recommendation_label($rec) {
        $labels = array(
            'accept' => __('Akzeptieren', PFM_TD),
            'minor' => __('Kleinere Revision', PFM_TD),
            'major' => __('Größere Revision', PFM_TD),
            'reject' => __('Ablehnen', PFM_TD),
        );
        return $labels[$rec] ?? $rec;
    }

    /**
     * Get manuscript download URL
     */
    private static function get_manuscript_download_url($post_id) {
        // Check for SharePoint version first
        $sp_item_id = get_post_meta($post_id, '_pfm_current_sp_item_id', true);
        if ($sp_item_id) {
            return add_query_arg(array(
                'action' => 'pfm_download_manuscript',
                'post_id' => $post_id,
                'nonce' => wp_create_nonce('pfm_download_manuscript'),
            ), admin_url('admin-ajax.php'));
        }

        // Fall back to local attachment
        $attachment_id = get_post_meta($post_id, 'pfm_manuscript_attachment_id', true);
        if ($attachment_id) {
            return wp_get_attachment_url($attachment_id);
        }

        return '#';
    }

    /**
     * Render standalone review form shortcode
     */
    public static function render_review_form($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts);
        $post_id = intval($atts['id'] ?: ($_GET['pfm_id'] ?? 0));

        if (!$post_id) {
            return '<div class="pfm-notice pfm-notice-error">' . __('Ungültige Publikations-ID.', PFM_TD) . '</div>';
        }

        if (!is_user_logged_in()) {
            return '<div class="pfm-notice pfm-notice-error">' . __('Bitte melden Sie sich an.', PFM_TD) . '</div>';
        }

        $user_id = get_current_user_id();
        $assigned = (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true);

        if (!in_array($user_id, $assigned, true) && !pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            return '<div class="pfm-notice pfm-notice-error">' . __('Sie sind nicht als Reviewer für diese Publikation zugewiesen.', PFM_TD) . '</div>';
        }

        // Check if already reviewed
        $existing_review = self::get_user_review($post_id, $user_id);
        if ($existing_review) {
            return '<div class="pfm-notice pfm-notice-success">' .
                   __('Sie haben bereits ein Review für diese Publikation eingereicht.', PFM_TD) .
                   '</div>' .
                   self::render_review_details($existing_review);
        }

        $post = get_post($post_id);

        ob_start();
        ?>
        <div class="pfm-standalone-review-form">
            <div class="form-header">
                <h2><?php _e('Review einreichen', PFM_TD); ?></h2>
                <h3><?php echo esc_html($post->post_title); ?></h3>
            </div>
            <?php echo self::render_inline_review_form($post_id); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Check if current user is assigned as reviewer
     * Returns "1" if user has pending review assignments, "0" otherwise
     *
     * Usage: [pfm_is_assigned_reviewer]
     * Use in Elementor with condition: Shortcode equals "1"
     *
     * @param array $atts Shortcode attributes
     * @return string "1" or "0"
     */
    public static function render_is_assigned_reviewer($atts) {
        // Not logged in = not assigned
        if (!is_user_logged_in()) {
            return '0';
        }

        $user_id = get_current_user_id();

        // Check if user is marked as reviewer
        $is_reviewer = get_user_meta($user_id, 'pfm_is_reviewer', true) === '1';
        if (!$is_reviewer) {
            return '0';
        }

        // Check if user has any publication assignments
        $args = array(
            'post_type' => 'publikation',
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'pfm_assigned_reviewers',
                    'value' => serialize(strval($user_id)),
                    'compare' => 'LIKE',
                ),
            ),
        );

        $query = new WP_Query($args);

        return $query->have_posts() ? '1' : '0';
    }

    /**
     * AJAX: Add/create reviewer
     */
    public static function ajax_add_reviewer() {
        check_ajax_referer('pfm_reviewer_panel_nonce', 'nonce');

        if (!current_user_can('edit_users') && !pfm_user_is_editor_in_chief()) {
            wp_send_json_error(array('message' => __('Keine Berechtigung.', PFM_TD)));
        }

        $email = sanitize_email($_POST['email'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $send_welcome = !empty($_POST['send_welcome']);

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Ungültige E-Mail-Adresse.', PFM_TD)));
        }

        if (empty($name)) {
            wp_send_json_error(array('message' => __('Name ist erforderlich.', PFM_TD)));
        }

        // Check if user exists
        $existing_user = get_user_by('email', $email);

        if ($existing_user) {
            // Just add reviewer flag
            update_user_meta($existing_user->ID, 'pfm_is_reviewer', '1');
            wp_send_json_success(array(
                'message' => __('Benutzer existiert bereits und wurde als Reviewer markiert.', PFM_TD),
                'user_id' => $existing_user->ID,
                'is_new' => false,
            ));
        }

        // Create new user
        $username = sanitize_user(strtolower(str_replace(' ', '.', $name)));
        $username = self::generate_unique_username($username);
        $password = wp_generate_password(12, true, true);

        $user_id = wp_insert_user(array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $name,
            'role' => 'subscriber',
        ));

        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }

        // Set reviewer flag
        update_user_meta($user_id, 'pfm_is_reviewer', '1');

        // Send welcome email
        if ($send_welcome) {
            self::send_reviewer_welcome_email($user_id, $password);
        }

        wp_send_json_success(array(
            'message' => __('Reviewer erfolgreich angelegt.', PFM_TD),
            'user_id' => $user_id,
            'is_new' => true,
        ));
    }

    /**
     * Generate unique username
     */
    private static function generate_unique_username($username) {
        $original = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $original . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Send reviewer welcome email
     */
    private static function send_reviewer_welcome_email($user_id, $password) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $settings = pfm_get_settings();
        $journal_name = $settings['journal_title'] ?: get_bloginfo('name');

        $subject = sprintf(__('Willkommen als Reviewer bei %s', PFM_TD), $journal_name);
        $message = sprintf(
            __("Sehr geehrte/r %s,\n\nSie wurden als Reviewer für %s registriert.\n\nIhre Zugangsdaten:\nBenutzername: %s\nPasswort: %s\n\nBitte ändern Sie Ihr Passwort nach dem ersten Login.\n\nLogin-Seite: %s\n\nMit freundlichen Grüßen\n%s", PFM_TD),
            $user->display_name,
            $journal_name,
            $user->user_login,
            $password,
            wp_login_url(),
            $journal_name
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * AJAX: Assign reviewer to publication
     */
    public static function ajax_assign_reviewer() {
        check_ajax_referer('pfm_reviewer_panel_nonce', 'nonce');

        if (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            wp_send_json_error(array('message' => __('Keine Berechtigung.', PFM_TD)));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $reviewer_id = intval($_POST['reviewer_id'] ?? 0);
        $deadline = sanitize_text_field($_POST['deadline'] ?? '');
        $send_invitation = !empty($_POST['send_invitation']);

        if (!$post_id || !$reviewer_id) {
            wp_send_json_error(array('message' => __('Ungültige Parameter.', PFM_TD)));
        }

        $assigned = (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true);

        if (in_array($reviewer_id, $assigned, true)) {
            wp_send_json_error(array('message' => __('Reviewer ist bereits zugewiesen.', PFM_TD)));
        }

        $assigned[] = $reviewer_id;
        update_post_meta($post_id, 'pfm_assigned_reviewers', $assigned);

        if ($deadline) {
            update_post_meta($post_id, 'pfm_review_deadline', $deadline);
        }

        // Update status if needed
        $status = get_post_meta($post_id, 'review_status', true);
        if ($status === 'submitted') {
            update_post_meta($post_id, 'review_status', 'under_review');
        }

        // Send invitation email
        if ($send_invitation) {
            $reviewer = get_userdata($reviewer_id);
            if ($reviewer) {
                // Generate upload token
                $token_manager = new PFM_Upload_Token();
                $token_data = $token_manager->generate($post_id, PFM_Upload_Token::TYPE_GUTACHTEN, array(
                    'reviewer_name' => $reviewer->display_name,
                    'reviewer_email' => $reviewer->user_email,
                    'expires_in' => 28,
                ));

                if (!is_wp_error($token_data)) {
                    PFM_Email_Templates::send_reviewer_invitation_with_link(
                        $post_id,
                        $reviewer->user_email,
                        $reviewer->display_name,
                        $token_data['upload_url'],
                        $deadline ? date_i18n('d.m.Y', strtotime($deadline)) : __('Keine Frist gesetzt', PFM_TD)
                    );
                }
            }
        }

        wp_send_json_success(array(
            'message' => __('Reviewer erfolgreich zugewiesen.', PFM_TD),
        ));
    }

    /**
     * AJAX: Remove reviewer assignment
     */
    public static function ajax_remove_reviewer() {
        check_ajax_referer('pfm_reviewer_panel_nonce', 'nonce');

        if (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            wp_send_json_error(array('message' => __('Keine Berechtigung.', PFM_TD)));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $reviewer_id = intval($_POST['reviewer_id'] ?? 0);

        if (!$post_id || !$reviewer_id) {
            wp_send_json_error(array('message' => __('Ungültige Parameter.', PFM_TD)));
        }

        $assigned = (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true);
        $assigned = array_diff($assigned, array($reviewer_id));
        update_post_meta($post_id, 'pfm_assigned_reviewers', array_values($assigned));

        wp_send_json_success(array(
            'message' => __('Reviewer-Zuweisung entfernt.', PFM_TD),
        ));
    }

    /**
     * AJAX: Submit review
     */
    public static function ajax_submit_review() {
        check_ajax_referer('pfm_reviewer_panel_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Bitte melden Sie sich an.', PFM_TD)));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $recommendation = sanitize_text_field($_POST['recommendation'] ?? '');
        $comments_to_author = wp_kses_post($_POST['comments_to_author'] ?? '');
        $confidential_comments = wp_kses_post($_POST['confidential_comments'] ?? '');

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Ungültige Publikation.', PFM_TD)));
        }

        // Validate recommendation is provided
        $valid_recommendations = array('accept', 'minor', 'major', 'reject');
        if (!in_array($recommendation, $valid_recommendations, true)) {
            wp_send_json_error(array('message' => __('Bitte wählen Sie eine Empfehlung aus.', PFM_TD)));
        }

        $user_id = get_current_user_id();
        $assigned = (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true);

        if (!in_array($user_id, $assigned, true) && !pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            wp_send_json_error(array('message' => __('Keine Berechtigung.', PFM_TD)));
        }

        // Check for existing review
        $existing = self::get_user_review($post_id, $user_id);
        if ($existing) {
            wp_send_json_error(array('message' => __('Sie haben bereits ein Review eingereicht.', PFM_TD)));
        }

        $user = wp_get_current_user();

        // Create review comment
        $comment_id = wp_insert_comment(array(
            'comment_post_ID' => $post_id,
            'comment_author' => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_content' => '(Review)',
            'user_id' => $user_id,
            'comment_approved' => 1,
            'comment_type' => 'pfm_review',
        ));

        if (!$comment_id) {
            wp_send_json_error(array('message' => __('Fehler beim Speichern des Reviews.', PFM_TD)));
        }

        // Save meta
        update_comment_meta($comment_id, 'pfm_recommendation', $recommendation);
        update_comment_meta($comment_id, 'pfm_comments_to_author', $comments_to_author);
        if ($confidential_comments) {
            update_comment_meta($comment_id, 'pfm_confidential_to_editor', $confidential_comments);
        }

        // Handle file upload
        if (!empty($_FILES['review_file']['name'])) {
            $sp_enabled = get_post_meta($post_id, '_pfm_sharepoint_enabled', true);
            $uploader = new PFM_SharePoint_Uploader();

            if ($sp_enabled && $uploader->is_available()) {
                // Upload to SharePoint
                $result = $uploader->upload_publication_file(
                    $post_id,
                    $_FILES['review_file'],
                    PFM_SharePoint_Uploader::TYPE_GUTACHTEN,
                    array(
                        'notes' => sprintf(__('Review von %s', PFM_TD), $user->display_name),
                        'reviewer_name' => $user->display_name,
                    )
                );

                if (!is_wp_error($result)) {
                    update_comment_meta($comment_id, 'pfm_review_sp_version_id', $result['id']);
                }
            } else {
                // Local upload
                $attachment_id = pfm_handle_upload($_FILES['review_file'], $post_id, false);
                if ($attachment_id) {
                    update_comment_meta($comment_id, 'pfm_review_attachment_id', $attachment_id);
                }
            }
        }

        // Notify editors
        pfm_notify_editors(
            __('Neues Review eingegangen', PFM_TD),
            sprintf(__('Ein Review zu "%s" wurde von %s abgegeben. Empfehlung: %s', PFM_TD),
                get_the_title($post_id),
                $user->display_name,
                self::get_recommendation_label($recommendation)
            )
        );

        wp_send_json_success(array(
            'message' => __('Review erfolgreich eingereicht!', PFM_TD),
            'comment_id' => $comment_id,
        ));
    }

    /**
     * AJAX: Get available reviewers
     */
    public static function ajax_get_reviewers() {
        check_ajax_referer('pfm_reviewer_panel_nonce', 'nonce');

        if (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            wp_send_json_error(array('message' => __('Keine Berechtigung.', PFM_TD)));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $assigned = $post_id ? (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true) : array();

        $reviewers = pfm_get_all_reviewers();
        $result = array();

        foreach ($reviewers as $reviewer) {
            $result[] = array(
                'id' => $reviewer->ID,
                'name' => $reviewer->display_name,
                'email' => $reviewer->user_email,
                'assigned' => in_array($reviewer->ID, $assigned, true),
            );
        }

        wp_send_json_success(array('reviewers' => $result));
    }
}

// Initialize
PFM_Reviewer_Panel::init();
