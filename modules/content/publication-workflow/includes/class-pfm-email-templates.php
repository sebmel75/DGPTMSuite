<?php
/**
 * Publication Frontend Manager - Email Template System
 * Professionelle E-Mail-Vorlagen für alle Workflow-Stages
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Email_Templates {

    /**
     * Verfügbare E-Mail-Templates
     */
    public static function get_available_templates() {
        return array(
            'submission_received' => __('Einreichung erhalten (an Autor)', PFM_TD),
            'submission_notification' => __('Neue Einreichung (an Redaktion)', PFM_TD),
            'reviewer_assigned' => __('Review-Zuweisung (an Reviewer)', PFM_TD),
            'reviewer_invitation_link' => __('Review-Einladung mit Upload-Link (an Reviewer)', PFM_TD),
            'review_reminder' => __('Review-Erinnerung (an Reviewer)', PFM_TD),
            'review_reminder_link' => __('Review-Erinnerung mit Upload-Link (an Reviewer)', PFM_TD),
            'review_received' => __('Review erhalten (an Redaktion)', PFM_TD),
            'review_received_sp' => __('Review via SharePoint erhalten (an Redaktion)', PFM_TD),
            'decision_accept' => __('Akzeptiert (an Autor)', PFM_TD),
            'decision_revision' => __('Revision erforderlich (an Autor)', PFM_TD),
            'decision_revision_link' => __('Revision erforderlich mit Upload-Link (an Autor)', PFM_TD),
            'decision_reject' => __('Abgelehnt (an Autor)', PFM_TD),
            'revision_received' => __('Revision erhalten (an Redaktion)', PFM_TD),
            'published' => __('Veröffentlicht (an Autor)', PFM_TD),
        );
    }

    /**
     * Template-Platzhalter
     */
    public static function get_placeholders() {
        return array(
            '{author_name}' => __('Name des Autors', PFM_TD),
            '{author_email}' => __('E-Mail des Autors', PFM_TD),
            '{publication_title}' => __('Titel der Publikation', PFM_TD),
            '{publication_url}' => __('Link zur Publikation', PFM_TD),
            '{submission_date}' => __('Einreichungsdatum', PFM_TD),
            '{reviewer_name}' => __('Name des Reviewers', PFM_TD),
            '{review_deadline}' => __('Review-Deadline', PFM_TD),
            '{reviewer_deadline}' => __('Reviewer-Frist (Alias für review_deadline)', PFM_TD),
            '{editor_name}' => __('Name des Editors', PFM_TD),
            '{journal_name}' => __('Name des Journals', PFM_TD),
            '{doi}' => __('DOI', PFM_TD),
            '{decision_date}' => __('Entscheidungsdatum', PFM_TD),
            '{comments}' => __('Kommentare/Feedback', PFM_TD),
            '{upload_link}' => __('Token-basierter Upload-Link', PFM_TD),
            '{download_link}' => __('SharePoint Download-Link', PFM_TD),
        );
    }

    /**
     * Standard-Templates
     */
    public static function get_default_template($template_key) {
        $templates = array(
            'submission_received' => array(
                'subject' => __('Ihre Einreichung bei {journal_name}', PFM_TD),
                'body' => __('Sehr geehrte/r {author_name},

vielen Dank für Ihre Einreichung "{publication_title}" bei {journal_name}.

Ihre Einreichung wurde erfolgreich am {submission_date} registriert und wird nun von unserer Redaktion geprüft. Sie erhalten in Kürze weitere Informationen zum Status Ihrer Publikation.

Sie können den Status Ihrer Einreichung jederzeit unter folgendem Link einsehen:
{publication_url}

Bei Fragen stehen wir Ihnen gerne zur Verfügung.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            'submission_notification' => array(
                'subject' => __('Neue Einreichung: {publication_title}', PFM_TD),
                'body' => __('Eine neue Publikation wurde eingereicht:

Titel: {publication_title}
Autor: {author_name}
Datum: {submission_date}

Bitte überprüfen Sie die Einreichung und weisen Sie geeignete Reviewer zu:
{publication_url}', PFM_TD),
            ),

            'reviewer_assigned' => array(
                'subject' => __('Review-Anfrage: {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {reviewer_name},

Sie wurden als Reviewer für folgende Publikation zugewiesen:

Titel: {publication_title}
Autor: {author_name}
Review-Deadline: {review_deadline}

Bitte überprüfen Sie das Manuskript und reichen Sie Ihr Review bis zum angegebenen Datum ein:
{publication_url}

Vielen Dank für Ihre Unterstützung.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            'review_reminder' => array(
                'subject' => __('Erinnerung: Review-Deadline für {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {reviewer_name},

dies ist eine freundliche Erinnerung, dass Ihr Review für "{publication_title}" am {review_deadline} fällig ist.

Falls Sie das Review noch nicht eingereicht haben, bitten wir Sie, dies zeitnah zu tun:
{publication_url}

Bei Problemen oder Zeitverzögerungen kontaktieren Sie uns bitte.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            'review_received' => array(
                'subject' => __('Neues Review eingegangen: {publication_title}', PFM_TD),
                'body' => __('Ein Review für "{publication_title}" wurde von {reviewer_name} eingereicht.

Bitte überprüfen Sie das Review:
{publication_url}', PFM_TD),
            ),

            'decision_accept' => array(
                'subject' => __('Akzeptiert: {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {author_name},

wir freuen uns, Ihnen mitteilen zu können, dass Ihre Publikation "{publication_title}" zur Veröffentlichung in {journal_name} akzeptiert wurde.

Entscheidungsdatum: {decision_date}

Ihre Publikation wird in Kürze mit folgender DOI veröffentlicht: {doi}

{comments}

Herzlichen Glückwunsch!

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            'decision_revision' => array(
                'subject' => __('Revision erforderlich: {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {author_name},

vielen Dank für Ihre Einreichung "{publication_title}".

Nach sorgfältiger Prüfung durch unsere Reviewer benötigt Ihre Publikation Überarbeitungen, bevor wir eine finale Entscheidung treffen können.

Feedback der Reviewer:
{comments}

Bitte laden Sie die überarbeitete Version über folgenden Link hoch:
{publication_url}

Wir freuen uns auf Ihre überarbeitete Einreichung.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            'decision_reject' => array(
                'subject' => __('Entscheidung zu: {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {author_name},

vielen Dank für Ihre Einreichung "{publication_title}" bei {journal_name}.

Nach sorgfältiger Prüfung müssen wir Ihnen leider mitteilen, dass wir Ihre Publikation nicht veröffentlichen können.

{comments}

Wir wünschen Ihnen viel Erfolg bei einer alternativen Veröffentlichung.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            'revision_received' => array(
                'subject' => __('Revision eingereicht: {publication_title}', PFM_TD),
                'body' => __('Eine überarbeitete Version von "{publication_title}" wurde von {author_name} hochgeladen.

Bitte überprüfen Sie die Revision:
{publication_url}', PFM_TD),
            ),

            'published' => array(
                'subject' => __('Veröffentlicht: {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {author_name},

Ihre Publikation "{publication_title}" wurde erfolgreich veröffentlicht!

DOI: {doi}
Publikations-URL: {publication_url}

Herzlichen Glückwunsch zur Veröffentlichung in {journal_name}.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            // New templates for SharePoint and token-based uploads
            'reviewer_invitation_link' => array(
                'subject' => __('Review-Anfrage: {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {reviewer_name},

wir würden Sie gerne als Gutachter für folgende Publikation gewinnen:

Titel: {publication_title}
Autor: {author_name}
Eingereicht am: {submission_date}

Bitte laden Sie das Manuskript über folgenden Link herunter, begutachten Sie es und reichen Sie Ihr Gutachten über denselben Link ein:

{upload_link}

Review-Deadline: {review_deadline}

Der Link ist {reviewer_deadline} Tage gültig und kann nur einmal verwendet werden.

Vielen Dank für Ihre Unterstützung des Peer-Review-Prozesses.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            'review_reminder_link' => array(
                'subject' => __('Erinnerung: Review-Deadline für {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {reviewer_name},

dies ist eine freundliche Erinnerung an Ihre ausstehende Begutachtung für "{publication_title}".

Review-Deadline: {review_deadline}

Falls Sie das Review noch nicht eingereicht haben, nutzen Sie bitte folgenden Link:

{upload_link}

Bei Problemen oder Zeitverzögerungen kontaktieren Sie uns bitte.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),

            'review_received_sp' => array(
                'subject' => __('Gutachten eingereicht: {publication_title}', PFM_TD),
                'body' => __('Ein Gutachten für "{publication_title}" wurde via Upload-Link eingereicht.

Gutachter: {reviewer_name}

Das Gutachten wurde in SharePoint gespeichert:
{download_link}

Zur Publikationsübersicht:
{publication_url}', PFM_TD),
            ),

            'decision_revision_link' => array(
                'subject' => __('Revision erforderlich: {publication_title}', PFM_TD),
                'body' => __('Sehr geehrte/r {author_name},

vielen Dank für Ihre Einreichung "{publication_title}".

Nach sorgfältiger Prüfung durch unsere Gutachter benötigt Ihre Publikation Überarbeitungen, bevor wir eine finale Entscheidung treffen können.

Feedback der Gutachter:
{comments}

Bitte laden Sie die überarbeitete Version über folgenden Link hoch:

{upload_link}

Der Link ist 28 Tage gültig.

Wir freuen uns auf Ihre überarbeitete Einreichung.

Mit freundlichen Grüßen
{editor_name}
{journal_name}', PFM_TD),
            ),
        );

        return isset($templates[$template_key]) ? $templates[$template_key] : null;
    }

    /**
     * Hole gespeichertes Template (oder Standard)
     */
    public static function get_template($template_key) {
        $custom_templates = get_option('pfm_email_templates', array());

        if (isset($custom_templates[$template_key])) {
            return $custom_templates[$template_key];
        }

        return self::get_default_template($template_key);
    }

    /**
     * Speichere Template
     */
    public static function save_template($template_key, $subject, $body) {
        $templates = get_option('pfm_email_templates', array());
        $templates[$template_key] = array(
            'subject' => $subject,
            'body' => $body,
        );
        update_option('pfm_email_templates', $templates);
    }

    /**
     * Ersetze Platzhalter im Template
     */
    public static function replace_placeholders($text, $data) {
        foreach ($data as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        return $text;
    }

    /**
     * Sende E-Mail mit Template
     */
    public static function send_email($to, $template_key, $data) {
        $template = self::get_template($template_key);

        if (!$template) {
            return false;
        }

        $subject = self::replace_placeholders($template['subject'], $data);
        $body = self::replace_placeholders($template['body'], $data);

        // WordPress E-Mail-Header
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        // Optional: Von-Adresse setzen
        $settings = pfm_get_settings();
        if (!empty($settings['depositor_email'])) {
            $headers[] = 'From: ' . $settings['journal_title'] . ' <' . $settings['depositor_email'] . '>';
        }

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Sammle Daten für Platzhalter
     */
    public static function get_email_data($post_id, $additional_data = array()) {
        $post = get_post($post_id);
        $author = get_userdata($post->post_author);
        $settings = pfm_get_settings();

        $data = array(
            'author_name' => $author->display_name,
            'author_email' => $author->user_email,
            'publication_title' => get_the_title($post_id),
            'publication_url' => add_query_arg('pfm_id', $post_id, get_permalink($post_id)),
            'submission_date' => get_the_date('d.m.Y', $post_id),
            'journal_name' => $settings['journal_title'] ?: get_bloginfo('name'),
            'doi' => get_post_meta($post_id, 'doi', true),
            'decision_date' => current_time('d.m.Y'),
            'editor_name' => wp_get_current_user()->display_name,
        );

        return array_merge($data, $additional_data);
    }

    /**
     * Sende E-Mail bei Submission
     */
    public static function send_submission_emails($post_id) {
        $data = self::get_email_data($post_id);

        // An Autor
        self::send_email($data['author_email'], 'submission_received', $data);

        // An Redaktion
        $editors = pfm_get_editors();
        foreach ($editors as $editor) {
            self::send_email($editor->user_email, 'submission_notification', $data);
        }
    }

    /**
     * Sende Review-Zuweisung E-Mails
     */
    public static function send_reviewer_assignment_emails($post_id, $reviewer_ids, $deadline) {
        $data = self::get_email_data($post_id, array(
            'review_deadline' => $deadline,
        ));

        foreach ($reviewer_ids as $reviewer_id) {
            $reviewer = get_userdata($reviewer_id);
            if ($reviewer) {
                $reviewer_data = array_merge($data, array(
                    'reviewer_name' => $reviewer->display_name,
                ));
                self::send_email($reviewer->user_email, 'reviewer_assigned', $reviewer_data);
            }
        }
    }

    /**
     * Sende Entscheidungs-E-Mail
     */
    public static function send_decision_email($post_id, $decision, $comments = '') {
        $template_map = array(
            'accepted' => 'decision_accept',
            'revision_needed' => 'decision_revision',
            'rejected' => 'decision_reject',
        );

        if (!isset($template_map[$decision])) {
            return false;
        }

        $data = self::get_email_data($post_id, array(
            'comments' => $comments,
        ));

        return self::send_email($data['author_email'], $template_map[$decision], $data);
    }

    /**
     * Sende Reviewer-Einladung mit Upload-Link
     *
     * @param int    $post_id      Publication ID
     * @param string $email        Reviewer email
     * @param string $name         Reviewer name
     * @param string $upload_link  Token-based upload URL
     * @param string $deadline     Review deadline (formatted)
     * @return bool
     */
    public static function send_reviewer_invitation_with_link($post_id, $email, $name, $upload_link, $deadline) {
        $data = self::get_email_data($post_id, array(
            'reviewer_name' => $name,
            'review_deadline' => $deadline,
            'reviewer_deadline' => $deadline,
            'upload_link' => $upload_link,
        ));

        return self::send_email($email, 'reviewer_invitation_link', $data);
    }

    /**
     * Sende Revisions-Aufforderung mit Upload-Link
     *
     * @param int    $post_id      Publication ID
     * @param string $upload_link  Token-based upload URL
     * @param string $comments     Review comments/feedback
     * @return bool
     */
    public static function send_revision_request_with_link($post_id, $upload_link, $comments = '') {
        $data = self::get_email_data($post_id, array(
            'upload_link' => $upload_link,
            'comments' => $comments,
        ));

        return self::send_email($data['author_email'], 'decision_revision_link', $data);
    }

    /**
     * Sende Benachrichtigung über erhaltenes Review (SharePoint)
     *
     * @param int    $post_id       Publication ID
     * @param string $reviewer_name Reviewer name
     * @param string $download_link SharePoint download URL
     * @return bool
     */
    public static function send_review_received_notification_sp($post_id, $reviewer_name, $download_link) {
        $data = self::get_email_data($post_id, array(
            'reviewer_name' => $reviewer_name,
            'download_link' => $download_link,
        ));

        // Send to all editors
        $editors = pfm_get_editors();
        $success = true;

        foreach ($editors as $editor) {
            $result = self::send_email($editor->user_email, 'review_received_sp', $data);
            if (!$result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Render Template-Editor für Admin
     */
    public static function render_template_editor($template_key) {
        $template = self::get_template($template_key);
        $templates = self::get_available_templates();
        $placeholders = self::get_placeholders();

        ?>
        <div class="pfm-template-editor">
            <h3><?php echo esc_html($templates[$template_key] ?? $template_key); ?></h3>

            <div class="template-placeholders">
                <h4><?php _e('Verfügbare Platzhalter:', PFM_TD); ?></h4>
                <ul>
                    <?php foreach ($placeholders as $placeholder => $description): ?>
                        <li><code><?php echo esc_html($placeholder); ?></code> - <?php echo esc_html($description); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pfm_save_email_template', 'pfm_template_nonce'); ?>
                <input type="hidden" name="action" value="pfm_save_email_template">
                <input type="hidden" name="template_key" value="<?php echo esc_attr($template_key); ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="email_subject"><?php _e('Betreff', PFM_TD); ?></label></th>
                        <td>
                            <input type="text" id="email_subject" name="email_subject"
                                   value="<?php echo esc_attr($template['subject']); ?>"
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_body"><?php _e('Nachricht', PFM_TD); ?></label></th>
                        <td>
                            <textarea id="email_body" name="email_body" rows="15" class="large-text"><?php echo esc_textarea($template['body']); ?></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Template speichern', PFM_TD); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
}

/**
 * Helper: Hole alle Redakteure
 */
function pfm_get_editors() {
    $editors = get_users(array(
        'meta_key' => 'pfm_is_redaktion',
        'meta_value' => '1',
    ));

    $eics = get_users(array(
        'meta_key' => 'pfm_is_editor_in_chief',
        'meta_value' => '1',
    ));

    return array_unique(array_merge($editors, $eics), SORT_REGULAR);
}
