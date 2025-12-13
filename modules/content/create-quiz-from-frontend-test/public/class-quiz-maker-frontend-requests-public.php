<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://ays-pro.com/
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Frontend_Requests
 * @subpackage Quiz_Maker_Frontend_Requests/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Quiz_Maker_Frontend_Requests
 * @subpackage Quiz_Maker_Frontend_Requests/public
 * @author     Quiz Maker team <info@ays-pro.com>
 */
class Quiz_Maker_Frontend_Requests_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_shortcode('ays_quiz_frontend_requests', array($this, 'ays_quiz_fr_generate'));
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		if( defined( 'AYS_QUIZ_PUBLIC_URL' ) ){
			wp_enqueue_style($this->plugin_name.'-font-awesome', AYS_QUIZ_PUBLIC_URL . '/css/quiz-maker-font-awesome.min.css', array(), $this->version, 'all');
		}
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/quiz-maker-frontend-requests-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/quiz-maker-frontend-requests-public.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( $this->plugin_name . '-ajax-public-fr', plugin_dir_url(__FILE__) . 'js/ays-quiz-fr-public-ajax.js', array('jquery'), $this->version, false);
		wp_localize_script($this->plugin_name . '-ajax-public-fr',  'ays_quiz_fr_public_ajax', array('ajax_url' => admin_url('admin-ajax.php')));

		wp_enqueue_script($this->plugin_name . '-sweetalert-js', QUIZ_MAKER_FRONTEND_REQUESTS_ADMIN_URL . '/js/quiz-maker-fr-sweetalert2.all.min.js', array('jquery'), $this->version, true );
	}

	public function get_fr_content(){
		global $wpdb;
		
		$question_category_table = $wpdb->prefix . "aysquiz_categories";
		$quiz_category_table = $wpdb->prefix . "aysquiz_quizcategories";
		$requests_table = $wpdb->prefix . "aysquiz_requests";

		$get_quiz_categories_sql = "SELECT id,title FROM {$quiz_category_table}";
		$results = $wpdb->get_results($get_quiz_categories_sql,'ARRAY_A');
		
		$get_question_categories_sql = "SELECT id,title FROM {$question_category_table}";
		$quest_results = $wpdb->get_results($get_question_categories_sql,'ARRAY_A');

		$quiz_question_types = array(
			'radio'	   	=> 'Radio',
			'checkbox' 	=> 'Checkbox',
			'select' 	=> 'Dropdown',
			'text' 		=> 'Text',
			'number'	=> 'Number',
		);

		$fr_content_array = array(
			'categories' 	   	  => $results,
			'quest_categories' 	  => $quest_results,
			'quiz_question_types' => $quiz_question_types,
		);

		return $fr_content_array;
	}

	public function front_requests_html(){

		if (! is_user_logged_in()) {
        	return false;
        }

		$content_data = $this->get_fr_content();
		$categories = (isset($content_data['categories']) && !empty($content_data['categories'])) ? $content_data['categories'] : array();
		$quest_categories = (isset($content_data['quest_categories']) && !empty($content_data['quest_categories'])) ? $content_data['quest_categories'] : array();
		$quiz_question_types = (isset($content_data['quiz_question_types']) && !empty($content_data['quiz_question_types'])) ? $content_data['quiz_question_types'] : array();

		$quest_cateogries_imp = '';
		$content =  '';

		$content .= '<div class="ays-quiz-fr-container">';
			$content .= '<div class="ays-quiz-fr-content">';
				$content .= '<div class="ays-quiz-fr-header">';
					$content .= '<span>'.__('Build your Quiz in a few minutes',$this->plugin_name).'</span>';
				$content .= '</div>';
				$content .= '<div class="ays-quiz-front-requests-body">';
					$content .= '<div class="ays-quiz-front-requests-preloader">';
						$content .= '<img src="'.QUIZ_MAKER_FRONTEND_REQUESTS_PUBLIC_URL.'/images/cogs.svg" alt="loader">';
					$content .= '</div>';
					$content .= '<form method="post" id="ays_quiz_front_request_form">';
						$content .= '<div class="ays-quiz-front-requests-body-content">';
							//Quiz Title
							$content .= '<div class="ays-quiz-fr-row">';
								$content .= '<div class="ays-quiz-fr-col-3">';
									$content .= '<div class="ays-quiz-fr-quiz-title-content">';
										$content .= '<label for="ays_quiz_fr_quiz_title"><span>'.__('Quiz Title',$this->plugin_name).'</span></label>';
									$content .= '</div>';
								$content .= '</div>';
								$content .= '<div class="ays-quiz-fr-col-9">';
									$content .= '<input type="text" name="ays_quiz_fr_quiz_title" id="ays_quiz_fr_quiz_title">';
								$content .= '</div>';
							$content .= '</div>'; 

							$content .= '<hr>';
							//Quiz Category
							$content .= '<div class="ays-quiz-fr-row">';
								$content .= '<div class="ays-quiz-fr-col-3">';
									$content .= '<div class="ays-quiz-fr-quiz-category-content">';
										$content .= '<label for="ays_quiz_fr_quiz_category"><span>'. __('Quiz Category',$this->plugin_name).'</span></label>';
									$content .= '</div>';
								$content .= '</div>';
								$content .= '<div class="ays-quiz-fr-col-9">';
									$content .= '<select type="text" name="ays_quiz_fr_quiz_category" id="ays_quiz_fr_quiz_category">';
										foreach ($categories as $key => $category) {
											$category_title = (isset($category['title']) && $category['title'] != '') ? sanitize_text_field($category['title']) : '';
											$category_id = (isset($category['id']) && $category['id'] != '') ? absint($category['id']) : 1;
											$content .= '<option value="'.$category_id.'">'.$category_title.'</option>';
										}
									$content .= '</select>';
								$content .= '</div>';
							$content .= '</div>';

							$content .= '<hr>';

							$content .= '<div class="ays-quiz-fr-row">';
								$content .= '<div class="ays-quiz-fr-col-12">';
									$content .= '<div class="ays_quiz_fr_question-container-title">';
										$content .= '<span class="ays_quiz_fr_question_container-title">'. __( 'Questions', $this->plugin_name ) .'</span>';
									$content .= '</div>';

									$content .= '<div class="ays-quiz-fr-quiz-add-question-container">';
										$content .= '<a href="javascript:void(0)"  class="ays-quiz-fr-add-question">';	
											$content .= '<i class="ays_fa_fr ays_fa_fr_plus_square"></i>';
											$content .= '<span>'. __( 'Add question', $this->plugin_name ) .'</span>';	
										$content .= '</a>';	
									$content .= '</div>';

								$content .= '</div>';
							$content .= '</div>';
							$content .= '<hr>';

							//Quiz Question Container
							$content .= '<div class="ays-quiz-fr-quiz-question-container" data-id="1">';
								$content .= '<div class="ays-quiz-fr-quiz-question-content" data-id="1">';
									$content .= '<div class="ays-quiz-fr-row">';
										$content .= '<div class="ays-quiz-fr-col-8">';
											// $content .='<div class="ays-quiz-fr-question-title-type">';

											//Quiz Question Name
											$content .= '<div class="ays-quiz-fr-quest-title">';
												$content .= '<span class="ays_quiz_fr_question_container-title">'.__('Question title',$this->plugin_name).'</span>';
												$content .= '<textarea id="ays_quiz_fr_quest_title_1" class="ays-quiz-fr-question" name="ays_quiz_fr_question[1][question]"></textarea>';
											$content .= '</div>';
										$content .= '</div>';

										$content .= '<div class="ays-quiz-fr-col-4">';
											//Quiz Question Type
											$content .= '<div class="ays-quiz-fr-question-type">';
												$content .= '<select id="ays_quiz_fr_quest_type_1" class="ays-quiz-fr-quest-type" name="ays_quiz_fr_question[1][type]" data-type="radio">';
													foreach ($quiz_question_types as $key => $quiz_question_type) {
														$content .= '<option value="'.$key.'">'.$quiz_question_type.'</option>';
													}
												$content .= '</select >';
											$content .= '</div>';

										//Quiz Question Category
										$content .='<div class="ays-quiz-fr-question-category">';	
												$content .= '<select id="ays_quiz_fr_quest_category_1" class="ays-quiz-fr-quest-category" name="ays_quiz_fr_question[1][category]">';
												foreach ($quest_categories as $key => $quest_category) {
													$quest_category_title = (isset($quest_category['title']) && $quest_category['title'] != '') ? sanitize_text_field($quest_category['title']) : '';
													$quest_category_id = (isset($quest_category['id']) && $quest_category['id'] != '') ? absint($quest_category['id']) : 1;
													$content .= '<option value="'.$quest_category_id.'">'.$quest_category_title.'</option>';
												}
												$content .= '</select >';
										$content .='</div>';

										$content .='</div>';
									$content .='</div>';

									//Quiz Question Answer
									$content .='<div class="ays-quiz-fr-answer">';

										$content .= '<span class="ays_quiz_fr_answers_container-title">'.__('Answers',$this->plugin_name).'</span>';

										$content .= '<div class="ays-quiz-fr-row">';
											$content .= '<div class="ays-quiz-fr-answer-content ays-quiz-fr-col-8">';
												$content .= '<div class="ays-quiz-fr-radio-answer">';
													$content .= '<div class="ays-quiz-fr-answer-row">';
														$content .= '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question[1][correct][]" value="1" title="' . __( "Correct answer", $this->plugin_name ) . '" checked>';
														$content .= '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question[1][answers][1][title]">';
														$content .= '<a href="javascript:void(0)" class="ays-quiz-fr-delete-answer" title="' . __( "Delete", $this->plugin_name ) . '">';
															$content .= '<i class="ays_fa_fr ays_fa_fr_times"></i>';
														$content .= '</a>';
													$content .= '</div>';
													$content .= '<div class="ays-quiz-fr-answer-row">';
														$content .= '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question[1][correct][]" value="2" title="' . __( "Correct answer", $this->plugin_name ) . '">';
														$content .= '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question[1][answers][2][title]">';
														$content .= '<a href="javascript:void(0)" class="ays-quiz-fr-delete-answer" title="' . __( "Delete", $this->plugin_name ) . '">';
															$content .= '<i class="ays_fa_fr ays_fa_fr_times"></i>';
														$content .= '</a>';
													$content .= '</div>';
													$content .= '<div class="ays-quiz-fr-answer-row">';
														$content .= '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question[1][correct][]" value="3" title="' . __( "Correct answer", $this->plugin_name ) . '">';
														$content .= '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question[1][answers][3][title]">';
														$content .= '<a href="javascript:void(0)" class="ays-quiz-fr-delete-answer" title="' . __( "Delete", $this->plugin_name ) . '">';
															$content .= '<i class="ays_fa_fr ays_fa_fr_times"></i>';
														$content .= '</a>';
													$content .= '</div>';
												$content .= '</div>';
											$content .= '</div>';
										$content .= '</div>';

										$content .= '<hr>';
										
										$content .= '<div class="ays-quiz-fr-questions-actions-container">';
											$content .= '<div class="ays-quiz-fr-quiz-add-answer-container">';
												$content .= '<a href="javascript:void(0)" class="ays-quiz-fr-add-answer">';
													$content .= '<i class="ays_fa_fr ays_fa_fr_plus_square"></i>';
													$content .= '<span>'.__('Add answer',$this->plugin_name).'</span>';	
												$content .= '</a>';	
											$content .= '</div>';

											$content .= '<div class="ays-quiz-fr-answer-duplicate-delete-content">';
												$content .= '<a href="javascript:void(0)" class="ays-quiz-fr-clone-question" title="' . __( "Duplicate", $this->plugin_name ) . '">';
													$content .= '<i class="ays_fa_fr ays_fa_fr_clone"></i>';
												$content .= '</a>';
												$content .= '<a href="javascript:void(0)" class="ays-quiz-fr-delete-question" title="' . __( "Delete", $this->plugin_name ) . '">';
													$content .= '<i class="ays_fa_fr ays_fa_fr_trash_o"></i>';
												$content .= '</a>';
											$content .= '</div>';
										$content .= '</div>';
									$content .='</div>';
								$content .= '</div>';
							$content .= '</div>'; 

							$content .= '<hr>';

							$content .= '<div class="ays-quiz-fr-quiz-add-question-container">';
								$content .= '<a href="javascript:void(0)"  class="ays-quiz-fr-add-question">';	
									$content .= '<i class="ays_fa_fr ays_fa_fr_plus_square"></i>';
									$content .= '<span>'. __( 'Add question', $this->plugin_name ) .'</span>';	
								$content .= '</a>';	
							$content .= '</div>'; 
							
							$content .= '<hr>';

							$content .= '<div class="ays-quiz-fr-quiz-submit-content">';
								$content .= '<input type="button" name="ays_quiz_front_requests_quiz_submit" id="ays_quiz_front_requests_quiz_submit" class="ays-quiz-front-requests-quiz-submit" value="Submit"/>';
							$content .= '</div>'; 
						$content .= '</div>'; 
					$content .= '</form>';
				$content .= '</div>';
			$content .= '</div>';
		$content .= '</div>';

		return $content;
	}

	public static function get_max_id() {
        global $wpdb;
        $quiz_table = $wpdb->prefix . 'aysquiz_quizes';

        $sql = "SELECT max(id) FROM {$quiz_table}";

        $result = intval($wpdb->get_var($sql));

        return $result;
    }
	
	public function insert_quiz_fr_data_to_db(){
		if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'insert_quiz_fr_data_to_db'){
			global $wpdb;

			$requests_table = $wpdb->prefix . "aysquiz_requests";
			$quiz_id = $this->get_max_id();
			$user_id = get_current_user_id();
            $user_ip = Quiz_Maker_Data::get_user_ip();
			
			$ays_quiz_fr_quiz_title = (isset($_REQUEST['ays_quiz_fr_quiz_title']) && $_REQUEST['ays_quiz_fr_quiz_title'] != '') ? sanitize_text_field($_REQUEST['ays_quiz_fr_quiz_title']) : 'Quiz';
			$ays_quiz_fr_quiz_category_id = (isset($_REQUEST['ays_quiz_fr_quiz_category']) && $_REQUEST['ays_quiz_fr_quiz_category'] != '') ? absint($_REQUEST['ays_quiz_fr_quiz_category']) : 1;

			// $ays_quiz_fr_question_data = (isset($_REQUEST['ays_quiz_fr_question']) && !empty($_REQUEST['ays_quiz_fr_question']) ) ? $_REQUEST['ays_quiz_fr_question'] : array();

			$options = array(

            );
			$quiz_data = array(
				'ays_quiz_fr_question_data'=> $_REQUEST['ays_quiz_fr_question'],
            );

        	$request_res = $wpdb->insert(
                $requests_table,						
                array(
                    'quiz_id'       => '',
                    'category_id'   => $ays_quiz_fr_quiz_category_id,
                    'user_id'       => $user_id,
                    'user_ip'      	=> $user_ip,
                    'quiz_title'    => $ays_quiz_fr_quiz_title,
                    'quiz_data' 	=> json_encode($quiz_data),
                    'request_date'  => current_time( 'mysql' ),
                    'status'        => 'Unpublished',
                    'read'   		=> '',
                    'approved'   	=> 'not-approved',
                    'options'       => json_encode($options),
                ),
                array(
                    '%d', // quiz_id
                    '%d', // category_id
                    '%d', // user_id
                    '%s', // user_ip
                    '%s', // quiz_title
                    '%s', // quiz_data
                    '%s', // request_date
                    '%s', // status
                    '%s', // read
                    '%s', // approved
                    '%s', // options
                )
            );

			$req_last_id = $wpdb->insert_id;

			$front_requests_options = (Quiz_Maker_Settings_Actions::ays_get_setting('front_requests') === false) ? json_encode(array()) : Quiz_Maker_Settings_Actions::ays_get_setting('front_requests');
			if (! empty($front_requests_options)) {
				$front_requests_options = json_decode($front_requests_options, true);
			}
			if (! empty($front_requests_options)) {
				$options = $front_requests_options;
			}

			// auto approve
			$options['ays_quiz_fr_auto_approve'] = (isset($options['ays_quiz_fr_auto_approve']) && $options['ays_quiz_fr_auto_approve'] != '') ? $options['ays_quiz_fr_auto_approve'] : 'off';
			$ays_quiz_fr_auto_approve = (isset($options['ays_quiz_fr_auto_approve']) && $options['ays_quiz_fr_auto_approve'] == 'on') ? true : false;
			
			// send email to admin
			$options['ays_quiz_fr_send_email'] = (isset($options['ays_quiz_fr_send_email']) && $options['ays_quiz_fr_send_email'] != '') ? $options['ays_quiz_fr_send_email'] : 'off';
			$ays_quiz_fr_send_email = (isset($options['ays_quiz_fr_send_email']) && $options['ays_quiz_fr_send_email'] == 'on') ? true : false;

			if($ays_quiz_fr_auto_approve){
				Frontend_Requests_Emails_List_Table::mark_as_approved_requests($req_last_id);
			}

			if($ays_quiz_fr_send_email) {
				$to = get_option( 'admin_email' );
				
				if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
					$page_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
					
					$nsite_url_base = get_site_url();
					$nsite_url_replaced = str_replace( array( 'http://', 'https://' ), '', $nsite_url_base );
					$nsite_url = trim( $nsite_url_replaced, '/' );
					$nfrom = "From:Quiz Maker<quiz_maker@".$nsite_url.">";
					$reply_to = "Reply-To: " . $to;

					$subject = "New Quiz Request";
					$message = "Someone has created a new quiz on your " . $page_url . " website.";

					$headers = $nfrom."\r\n";
					$headers .= $reply_to . "\r\n";
					$headers .= "MIME-Version: 1.0\r\n";
					$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

					$attachments = array();

					wp_mail( $to, $subject, $message, $headers, $attachments );

				}
			}

			if( $request_res ){
				$response_text = __( 'Your request has been sent', $this->plugin_name );
			}else{
				$response_text = __( 'Your request does not send, please try again.', $this->plugin_name );
			}


			$result = array(
				'status' => true,
				'data'   => $request_res ? true : false,
				'message' => $response_text,
			);

			ob_end_clean();
			$ob_get_clean = ob_get_clean();
			echo json_encode($result);
			wp_die();
		}else{
			$result = array(
				'status' => false,
				'data'   => false,
				'message' => __( "Your request does not send, please try again.", $this->plugin_name ),
			);

			ob_end_clean();
			$ob_get_clean = ob_get_clean();
			echo json_encode($result);
			wp_die();
		}
	}

	public function ays_quiz_fr_generate(){
		
		$this->enqueue_styles();
        $this->enqueue_scripts();
		
		$quiz_content_front_request = $this->front_requests_html();
				
		return str_replace(array("\r\n", "\n", "\r"), "\n", $quiz_content_front_request);
	}
}
