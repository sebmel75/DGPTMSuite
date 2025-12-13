<?php
/**
 * Event Tracker Constants
 *
 * Zentrale Konstanten für das Event Tracker Plugin
 *
 * @package Event_Tracker
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Tracker Constants Class
 */
class ET_Constants {

	/** CPTs & Optionen */
	const CPT                   = 'et_event';
	const CPT_MAIL_LOG          = 'et_mail';
	const CPT_MAIL_TPL          = 'et_mail_tpl';
	const OPT_KEY               = 'et_settings';

	/** Event-Metadaten */
	const META_START_TS         = '_et_start_ts';
	const META_END_TS           = '_et_end_ts';
	const META_REDIRECT_URL     = '_et_redirect_url';
	const META_ZOHO_ID          = '_et_zoho_id';
	const META_RECORDING_URL    = '_et_recording_url';
	const META_ADDITIONAL_DATES = '_et_additional_dates'; // Array: [['start'=>ts, 'end'=>ts], ...]
	const META_IFRAME_ENABLE    = '_et_iframe_enable'; // '1'|'0'
	const META_IFRAME_URL       = '_et_iframe_url';

	/** Mail-Log Metadaten */
	const META_MAIL_EVENT_ID    = '_et_mail_event_id';
	const META_MAIL_ZOHO_ID     = '_et_mail_zoho_id';
	const META_MAIL_RAW_HTML    = '_et_mail_raw_html';
	const META_MAIL_STATUS      = '_et_mail_status'; // sent|error|test|queued|stopped|draft
	const META_MAIL_HTTP_CODE   = '_et_mail_http_code';
	const META_MAIL_HTTP_BODY   = '_et_mail_http_body';

	/** Mail-Planung & Optionen */
	const META_MAIL_SUBJECT       = '_et_mail_subject';
	const META_MAIL_SCHED_KIND    = '_et_mail_sched_kind'; // now|at|event_start|until_start
	const META_MAIL_SCHED_TS      = '_et_mail_sched_ts';
	const META_MAIL_RECURRING     = '_et_mail_recurring';
	const META_MAIL_INTERVAL      = '_et_mail_interval_sec';
	const META_MAIL_STOPPED       = '_et_mail_manual_stop';
	const META_MAIL_IGNOREDATE    = '_et_mail_ignoredate';
	const META_MAIL_INTERVAL_FROM = '_et_mail_interval_from_ts';

	/** Locks */
	const META_MAIL_LOCK        = '_et_mail_processing_lock';

	/** Cron Hooks */
	const CRON_HOOK_SINGLE      = 'et_run_mail_job';

	/** User-Meta */
	const USER_META_ACCESS      = 'et_mailer_access';

	/** Query Keys */
	const QUERY_KEY_EVENT       = 'et_event';
	const QUERY_KEY_TRACKER     = 'et_tracker';

	/** Fallback */
	const MAX_JOBS_PER_TICK     = 5;
}
