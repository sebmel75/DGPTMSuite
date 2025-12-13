<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://ays-pro.com/
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Frontend_Requests
 * @subpackage Quiz_Maker_Frontend_Requests/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Quiz_Maker_Frontend_Requests
 * @subpackage Quiz_Maker_Frontend_Requests/admin
 * @author     Quiz Maker team <info@ays-pro.com>
 */
class Quiz_Maker_Frontend_Requests_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;
	private $frontend_requests_obj; 
	private $quiz_settings_obj; 

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Quiz_Maker_Frontend_Requests_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Quiz_Maker_Frontend_Requests_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/quiz-maker-frontend-requests-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($hook_suffix) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Quiz_Maker_Frontend_Requests_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Quiz_Maker_Frontend_Requests_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if (false !== strpos($hook_suffix, "plugins.php")){
            wp_enqueue_script($this->plugin_name . '-sweetalert-js', QUIZ_MAKER_FRONTEND_REQUESTS_ADMIN_URL . '/js/quiz-maker-fr-sweetalert2.all.min.js', array('jquery'), $this->version, true );
        	wp_enqueue_script( $this->plugin_name.'-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), $this->version, false );
            wp_localize_script($this->plugin_name . '-admin',  'quiz_maker_fr_admin_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
        }

		wp_enqueue_script( $this->plugin_name . '-front-request', plugin_dir_url( __FILE__ ) . 'js/quiz-maker-frontend-requests-admin.js', array( 'jquery' ), $this->version, false );
		wp_localize_script($this->plugin_name . '-front-request', 'fr_quiz_maker_admin_ajax', array('ajaxUrl' => admin_url('admin-ajax.php')));
		wp_enqueue_script('jquery');

	}

	public function add_plugin_admin_menu(){
        $hook_quiz_maker_frontend_requests = add_submenu_page(
            PARENT_QUIZ_MAKER_NAME,
            __('Frontend Requests', $this->plugin_name),
            __('Frontend Requests', $this->plugin_name),
            'manage_options',
            PARENT_QUIZ_MAKER_NAME . "-frontend-requests-admin",
			array($this, 'display_add_on_page')
		);
		add_action("load-$hook_quiz_maker_frontend_requests", array($this, 'screen_option_quiz_frontend_requests'));

		$hook_each_requests = add_submenu_page(
            PARENT_QUIZ_MAKER_NAME . "-frontend-requests-admin",
			__('Requests per quiz', $this->plugin_name),
			__('Requests per quiz', $this->plugin_name),
			"manage_options",
			$this->plugin_name . '-admin-each',
			array($this, 'display_plugin_each_requests_page')
        );
	}
	
	public function screen_option_quiz_frontend_requests(){
        $option = 'per_page';
        $args = array(
            'label' => __('Quiz Frontend Requests', $this->plugin_name),
            'default' => 20,
            'option' => 'quiz_frontend_requests_per_page'
        );

		add_screen_option($option, $args);
		$this->frontend_requests_obj = new Frontend_Requests_Emails_List_Table($this->plugin_name);
		$this->quiz_settings_obj = new Quiz_Maker_Settings_Actions($this->plugin_name);
    }
	
	public static function set_screen($status, $option, $value){
        return $value;
    }

	public function display_plugin_each_requests_page()
	{
		include_once 'partials/quiz-maker-frontend-requests-admin-display.php';
	}

	public function display_add_on_page()
	{
		include_once('partials/actions/quiz-maker-frontend-requests-admin-actions.php');
	}

	public function deactivate_plugin_option(){
        error_reporting(0);
        $request_value = $_REQUEST['upgrade_plugin'];
        $upgrade_option = get_option('ays_quiz_frontend_requests_upgrade_plugin','');
        if($upgrade_option === ''){
            add_option('ays_quiz_frontend_requests_upgrade_plugin',$request_value);
        }else{
            update_option('ays_quiz_frontend_requests_upgrade_plugin',$request_value);
        }
        ob_end_clean();
        $ob_get_clean = ob_get_clean();
        echo json_encode(array('option'=>get_option('ays_quiz_frontend_requests_upgrade_plugin','')));
        wp_die();
    }

	public function get_fr_quiz_data(){
		global $wpdb;

		$request_table = $wpdb->prefix.'aysquiz_requests';
		$sql = "SELECT * FROM {$request_table}";
		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}

	public function get_fr_quiz_data_by_id($id){
		global $wpdb;

		$request_table = esc_sql($wpdb->prefix.'aysquiz_requests');

		$sql = "SELECT * FROM {$request_table} WHERE id=".$id;

		$result = $wpdb->get_row( $sql, 'ARRAY_A' );

		return $result;
	}

	public function get_quiz_cat_by_id( $id ){
		global $wpdb;

		$categories_table = esc_sql($wpdb->prefix.'aysquiz_quizcategories');

		$sql = "SELECT `title` FROM {$categories_table} WHERE id=".$id;

		$result = $wpdb->get_var( $sql );

		return $result;
	}
	public function get_question_cat_by_id( $id ){
		global $wpdb;

		$categories_table = esc_sql($wpdb->prefix.'aysquiz_categories');

		$sql = "SELECT `title` FROM {$categories_table} WHERE id=".$id;

		$result = $wpdb->get_var( $sql );

		return $result;
	}

	public function ays_quiz_fr_approve_data(){
		if(isset($_REQUEST['action']) && $_REQUEST['action'] != ''){
			error_reporting(0);
			global $wpdb;

			$approved_id = (isset($_REQUEST['approved_id']) && $_REQUEST['approved_id'] != '') ? intval($_REQUEST['approved_id']) : null;

			if($approved_id == null){
				return;
			}

			$requests_table = $wpdb->prefix.'aysquiz_requests';
			$sql = "SELECT * FROM {$requests_table} WHERE id=".$approved_id;
			$results = $wpdb->get_results( $sql, 'ARRAY_A' );

			$fr_quiz_id = '';
			$quiz_title = '';
			$category_id = '';
			$front_requests_questions = array();
			foreach ($results as $key => $result) {
				$quiz_data = json_decode($result['quiz_data'],true);
				$questions = (isset($quiz_data['ays_quiz_fr_question_data']) && !empty($quiz_data['ays_quiz_fr_question_data'])) ? $quiz_data['ays_quiz_fr_question_data'] : array();
				$ays_quiz_fr_question_array = array();
				$fr_quiz_id = (isset($result['quiz_id']) && $result['quiz_id'] != 0) ? intval($result['quiz_id']) : 0;
				$category_id = (isset($result['category_id']) && $result['category_id'] != null) ? intval($result['category_id']) : 1;
				$quiz_title = (isset($result['quiz_title']) && $result['quiz_title'] != '') ? $result['quiz_title'] : '';
				if(!empty($questions)){
					foreach ($questions as $question_key => $question) {
						$ays_quiz_fr_question = (isset($question['question']) && $question['question'] != '') ? wp_kses_post(wpautop($question['question'])) : 'Question default title';

						$ays_quiz_fr_type = (isset($question['type']) && $question['type'] != '') ? sanitize_text_field($question['type']) : 'radio';

						$ays_quiz_fr_category = (isset($question['category']) && $question['category'] != '') ? intval($question['category']) : '1';

						$ays_quiz_fr_correct = (isset($question['correct']) && $question['correct'] != '') ? $question['correct'] : array( '1' );

						$ays_quiz_fr_answers = (isset($question['answers']) && !empty($question['answers'])) ? $question['answers'] : array();

						$ays_quiz_fr_text_answer = (isset($question['text_answer']) && !empty($question['text_answer'])) ? $question['text_answer'] : array();

						$ays_quiz_fr_number_answer = (isset($question['number_answer']) && !empty($question['number_answer'])) ? $question['number_answer'] : array();

						$ays_quiz_fr_question_array['question'] = $ays_quiz_fr_question;
						$ays_quiz_fr_question_array['type'] = $ays_quiz_fr_type;
						$ays_quiz_fr_question_array['category'] = $ays_quiz_fr_category;
						$ays_quiz_fr_question_array['answers'] = $ays_quiz_fr_answers;
						$ays_quiz_fr_question_array['correct'] = $ays_quiz_fr_correct;
						$ays_quiz_fr_question_array['text_answer'] = $ays_quiz_fr_text_answer;
						$ays_quiz_fr_question_array['number_answer'] = $ays_quiz_fr_number_answer;
						
						$front_requests_questions[] = $ays_quiz_fr_question_array;
					}
				}
			}
			
			$questions_ids = '';
			$max_id = Quiz_Maker_Admin::get_max_id('quizes');
			$ordering = ( $max_id != NULL ) ? ( $max_id + 1 ) : 1;

			$user_id_sql = "SELECT `user_id` FROM {$requests_table} WHERE id=".$approved_id;
			$user_id_results = $wpdb->get_var( $user_id_sql);

			$create_date = current_time( 'mysql' );
			$current_user_id = get_current_user_id();

			$user_id = ( isset( $user_id_results ) && $user_id_results != '') ? absint($user_id_results) : $current_user_id; 
 
			$options = json_encode(array(
				'color' => '#27AE60',
				'bg_color' => '#fff',
				'text_color' => '#000',
				'height' => 350,
				'width' => 400,
				'timer' => 100,
				'information_form' => 'disable',
				'form_name' => '',
				'form_email' => '',
				'form_phone' => '',
				'enable_logged_users' => '',
				'image_width' => '',
				'image_height' => '',
				'enable_correction' => '',
				'enable_questions_counter' => 'on',
				'limit_users' => '',
				'limitation_message' => '',
				'redirect_url' => '',
				'redirection_delay' => '',
				'enable_progress_bar' => '',
				'randomize_questions' => '',
				'randomize_answers' => '',
				'enable_questions_result' => '',
				'custom_css' => '',
				'enable_restriction_pass' => '',
				'restriction_pass_message' => '',
				'user_role' => '',
				'result_text' => '',
				'enable_result' => '',
				'enable_timer' => 'off',
				'enable_pass_count' => 'on',
				'enable_quiz_rate' => '',
				'enable_rate_avg' => '',
				'enable_rate_comments' => '',
				'hide_score' => 'off',
				'rate_form_title' => '',
				'enable_box_shadow' => 'on',
				'box_shadow_color' => '#000',
				'quiz_border_radius' => '0',
				'quiz_bg_image' => '',
				'enable_border' => '',
				'quiz_border_width' => '1',
				'quiz_border_style' => 'solid',
				'quiz_border_color' => '#000',
				'quiz_timer_in_title' => '',
				'enable_restart_button' => 'off',
				'quiz_loader' => 'default',
				'autofill_user_data' => 'off',
				'quest_animation' => 'shake',
				'enable_bg_music' => 'off',
				'quiz_bg_music' => '',
				'answers_font_size' => '15',
				'show_create_date' => 'off',
				'show_author' => 'off',
				'enable_early_finish' => 'off',
				'answers_rw_texts' => 'on_passing',
				'disable_store_data' => 'off',
				'enable_background_gradient' => 'off',
				'background_gradient_color_1' => '#000',
				'background_gradient_color_2' => '#fff',
				'quiz_gradient_direction' => 'vertical',
				'redirect_after_submit' => 'off',
				'submit_redirect_url' => '',
				'submit_redirect_delay' => '',
				'progress_bar_style' => 'first',
				'enable_exit_button' => 'off',
				'exit_redirect_url' => '',
				'image_sizing' => 'cover',
				'quiz_bg_image_position' => 'center center',
				'custom_class' => '',
				'enable_social_links' => 'off',
				'social_links' => array(
					'linkedin_link' => '',
					'facebook_link' => '',
					'twitter_link' => ''
				),
				'show_quiz_title' => 'on',
				'show_quiz_desc' => 'on',
				'show_login_form' => 'off',
				'mobile_max_width' => '',
				'limit_users_by' => 'ip',
				'progress_live_bar_style' => 'default',

				// Develpoer version options
				'enable_copy_protection' => '',
				'activeInterval' => '',
				'deactiveInterval' => '',
				'active_date_check' => 'off',
				'active_date_message' => __("The quiz has expired!", $this->plugin_name),
				'checkbox_score_by' => 'on',
				'calculate_score' => 'by_correctness',
				'question_bank_type' => 'general',
				'enable_tackers_count' => 'off',
				'tackers_count' => '',

				// Integration option
				'enable_paypal' => '',
				'paypal_amount' => '',
				'paypal_currency' => '',
				'enable_mailchimp' => '',
				'mailchimp_list' => '',
				'enable_monitor' => '',
				'monitor_list' => '',
				'enable_slack' => '',
				'slack_conversation' => '',
				'active_camp_list' => '',
				'active_camp_automation' => '',
				'enable_active_camp' => '',
				'enable_google_sheets' => '',
				'spreadsheet_id' => '',

				// Email config options
				'send_results_user' => 'off', //AV
				'send_interval_msg' => 'off',
				'additional_emails' => '',
				'email_config_from_email' => '',
				'email_config_from_name' => '',
				'email_config_from_subject' => '',

				'quiz_attributes' => array(),
				"certificate_title" => '<span style="font-size:50px; font-weight:bold">Certificate of Completion</span>',
				"certificate_body" => '<span style="font-size:25px"><i>This is to certify that</i></span><br><br>
						<span style="font-size:30px"><b>%%user_name%%</b></span><br/><br/>
						<span style="font-size:25px"><i>has completed the quiz</i></span><br/><br/>
						<span style="font-size:30px">"%%quiz_name%%"</span> <br/><br/>
						<span style="font-size:20px">with a score of <b>%%score%%</b></span><br/><br/>
						<span style="font-size:25px"><i>dated</i></span><br>
						<span style="font-size:30px">%%current_date%%</span><br/><br/><br/>'
			));

	        $quiz_settings = new Quiz_Maker_Settings_Actions( $this->plugin_name );
	        $quiz_default_options = ($quiz_settings->ays_get_setting('quiz_default_options') === false) ? json_encode(array()) : $quiz_settings->ays_get_setting('quiz_default_options');
	        if (! empty($quiz_default_options)) {
	            $options = $quiz_default_options;
	        }

			$answers_table = $wpdb->prefix . 'aysquiz_answers';
			$questions_table = $wpdb->prefix . 'aysquiz_questions';
			$quizes_table = $wpdb->prefix . 'aysquiz_quizes';
			foreach ($front_requests_questions as $key => $front_requests_question) {

				$front_req_question = $front_requests_question['question'];
				$front_req_type = $front_requests_question['type'];
				$front_req_category = $front_requests_question['category'];
				$front_req_answers = $front_requests_question['answers'];

				$res = $wpdb->insert($questions_table, array(
					'category_id' => $front_req_category,
					'question' => $front_req_question,
					'published' => 1,
					'type' => $front_req_type,
					'create_date' => $create_date,
					'author_id' => $user_id,
					'options' => json_encode(array(
						'bg_image' => "",
						'use_html' => 'off',
						'enable_question_text_max_length' => 'off',
						'question_text_max_length' => '',
						'question_limit_text_type' => 'characters',
						'question_enable_text_message' => 'off',
						'enable_question_number_max_length' => 'off',
						'question_number_max_length' => '',
						'quiz_hide_question_text' => 'off',
					))
				));

				$question_id = $wpdb->insert_id;
				$questions_ids .= $question_id . ',';
				$text_answer = (isset($front_requests_question['text_answer']) && $front_requests_question['text_answer'] != '') ? $front_requests_question['text_answer'] : '';
				$number_answer = (isset($front_requests_question['number_answer']) && $front_requests_question['number_answer'] != '') ? $front_requests_question['number_answer'] : '';
				
				if($front_req_type == 'text'){
					$correct = 1; 
					$wpdb->insert($answers_table, array(
						'question_id' => $question_id,
						'answer'      => esc_sql( trim($text_answer) ),
						'correct' 	  => $correct,
						'ordering' 	  => 0,
						'placeholder' => $placeholder
					));
				} elseif ($front_req_type == 'number') {
					$correct = 1;
					$placeholder = '';
					$wpdb->insert($answers_table, array(
						'question_id' => $question_id,
						'answer'      => esc_sql( trim($number_answer) ),
						'correct' 	  => $correct,
						'ordering' 	  => 0,
						'placeholder' => $placeholder
					));
				} else {
					$corrects = (isset($front_requests_question['correct']) && !empty( $front_requests_question['correct'] ) ) ? $front_requests_question['correct'] : array( '1' );
					foreach ($front_requests_question['answers'] as $answer_key => $answer) {
						// foreach (array_values($answer) as $ans_key => $fr_answer) {
							$correct = in_array($answer_key, $corrects) ? 1 : 0 ;
							$front_req_answer = (isset($answer['title']) && $answer['title'] != '') ? $answer['title'] : $answer['title'] ;
	
							$wpdb->insert($answers_table, array(
								'question_id' => $question_id,
								'answer'      => esc_sql( trim($front_req_answer) ),
								'correct' 	  => $correct,
								'ordering' 	  => $answer_key,
								'placeholder' => $placeholder
							));
						// }
					}
				}
			}
			
			$questions_ids = rtrim($questions_ids, ",");

			$wpdb->insert($quizes_table, array(
				'title' => $quiz_title,
				'question_ids' => $questions_ids,
				'published' => 1,
				'create_date' => $create_date,
				'author_id' => $user_id,
				'options' => $options,
				'quiz_category_id' => $category_id,
				'ordering' => $ordering
			));

			$quiz_id = $wpdb->insert_id;

			$quiz_status = '';
						
			$request_res = $wpdb->update(
				$requests_table,
				array(
					'quiz_id'       => $quiz_id,
					'status'        => 'Published',
					'approved'      => 'approved',
				),
				array( 'id' => $approved_id ),
				array( '%d', '%s', '%s'),
				array( '%d' )
			);
			$result = array(
				'status' => true,
				'quiz_id' => $quiz_id,
			);

			ob_end_clean();
			$ob_get_clean = ob_get_clean();
			echo json_encode($result);
			wp_die();
		}
	}

}
