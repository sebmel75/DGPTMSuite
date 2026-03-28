<?php
class Frontend_Requests_Emails_List_Table extends WP_List_Table{
    private $plugin_name;
    private $title_length;
   
    /** Class constructor */
    public function __construct($plugin_name) {
        $this->plugin_name = $plugin_name;
        $this->title_length = Quiz_Maker_Data::get_listtables_title_length('');
        parent::__construct( array(
            'singular' => __( 'Result', $this->plugin_name ), //singular name of the listed records
            'plural'   => __( 'Results', $this->plugin_name ), //plural name of the listed records
            'ajax'     => false //does this table support ajax?
        ) );
        add_action( 'admin_notices', array( $this, 'results_notices' ) );
    }

    /**
     * Override of table nav to avoid breaking with bulk actions & according nonce field
     */
    public function display_tablenav( $which ) {
        ?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">

            <div class="alignleft actions">
                <?php $this->bulk_actions( $which ); ?>
            </div>

            <?php
            $this->pagination( $which );
            ?>
            <br class="clear" />
        </div>
        <?php
    }
    
    protected function get_views() {
        $approved_count = $this->approved_records_count();
        $not_approved_count = $this->not_approved_records_count();
        $all_count = $this->all_record_count();
        $selected_all = "";
        $selected_0 = "";
        $selected_1 = "";
        if(isset($_GET['fstatus'])){
            switch($_GET['fstatus']){
                case "not-approved":
                    $selected_0 = " style='font-weight:bold;' ";
                    break;
                case "approved":
                    $selected_1 = " style='font-weight:bold;' ";
                    break;
                default:
                    $selected_all = " style='font-weight:bold;' ";
                    break;
            }
        }else{
            $selected_all = " style='font-weight:bold;' ";
        }

        $status_links = array(
            "all" => "<a ".$selected_all." href='?page=".esc_attr( $_REQUEST['page'] )."'>". __( 'All', $this->plugin_name )." (".$all_count.")</a>",
            "approved" => "<a ".$selected_1." href='?page=".esc_attr( $_REQUEST['page'] )."&fstatus=approved'>". __( 'Approved', $this->plugin_name )." (".$approved_count.")</a>",
            "not-approved"   => "<a ".$selected_0." href='?page=".esc_attr( $_REQUEST['page'] )."&fstatus=not-approved'>". __( 'Not Approved', $this->plugin_name )." (".$not_approved_count.")</a>"
        );
        return $status_links;
    }

    /**
     * Retrieve customers data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_requests( $per_page = 20, $page_number = 1 ) {

        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}aysquiz_requests";

        $where = array();

        if( isset( $_REQUEST['fstatus'] ) ){
            $fstatus = $_REQUEST['fstatus'];
            if($fstatus !== null){
                $where[] = " `approved` = '".$fstatus."' ";
            }
        }

        if( ! empty($where) ){
            $sql .= " WHERE " . implode( " AND ", $where );
        }
        
        
        if ( ! empty( $_REQUEST['orderby'] ) ) {
            $sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
            $sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' DESC';
        }
        else{
            $sql .= ' ORDER BY id DESC';
        }
        
        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

        $result = $wpdb->get_results( $sql, 'ARRAY_A' );

        return $result;
    }

    public function store_data(){
        global $wpdb;

        $ays_quiz_settings_table = $wpdb->prefix . "aysquiz_settings";
        if(isset($_POST['ays_fr_save']) && !empty($_POST['ays_fr_save'])){
            $ays_quiz_fr_auto_approve = ( isset($_POST['ays_quiz_front_request_auto_aprove']) && $_POST['ays_quiz_front_request_auto_aprove'] != "") ? $_POST['ays_quiz_front_request_auto_aprove'] : 'off';
            $ays_quiz_fr_send_email = ( isset($_POST['ays_quiz_front_request_send_email']) && $_POST['ays_quiz_front_request_send_email'] != "") ? $_POST['ays_quiz_front_request_send_email'] : 'off';

            $options = array(
                "ays_quiz_fr_auto_approve" => $ays_quiz_fr_auto_approve,
                "ays_quiz_fr_send_email" => $ays_quiz_fr_send_email,
            );

            $value = array(
                'meta_value'  => json_encode( $options ),
            );
            $value_s = array( '%s' );

            $result = $wpdb->update(
                $ays_quiz_settings_table,
                $value,
                array( 'meta_key' => 'front_requests' ),
                $value_s,
                array( '%s' )
            );
        }
    }

    public function get_requests_by_id( $id ){
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}aysquiz_requests WHERE id=" . absint( intval( $id ) );
        
        $result = $wpdb->get_row($sql, 'ARRAY_A');

        return $result;
    }

    public function get_requests_quiz_id(){
        global $wpdb;

        $sql = "SELECT quiz_id FROM {$wpdb->prefix}aysquiz_requests WHERE approved='approved'";
        $result = $wpdb->get_results($sql, 'ARRAY_A');

        return $result;
    }

    public function get_quizes_by_id( $id ){
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}aysquiz_quizes WHERE id=" . absint( intval( $id ) );

        $result = $wpdb->get_row($sql, 'ARRAY_A');

        return $result;
    }

    /**
     * Delete a customer record.
     *
     * @param int $id customer ID
     */
    public static function delete_requests( $id ) {
        global $wpdb;

        $wpdb->delete(
            "{$wpdb->prefix}aysquiz_requests",
            array( 'id' => $id ),
            array( '%d' )
        );
    }


    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}aysquiz_requests";

        return $wpdb->get_var( $sql );
    }

    public static function all_record_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}aysquiz_requests";

        return $wpdb->get_var( $sql );
    }

    public static function not_approved_records_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}aysquiz_requests";

        $where = array();

        $where[] = " `approved` = 'not-approved' ";

        if( ! empty($where) ){
            $sql .= " WHERE " . implode( " AND ", $where );
        }
        
        return $wpdb->get_var( $sql );
    }

    public function approved_records_count() {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}aysquiz_requests ";

        $where = array();

        $where[] = " `approved` = 'approved' ";

        if( ! empty($where) ){
            $sql .= " WHERE " . implode( " AND ", $where );
        }

        return $wpdb->get_var( $sql );
    }

    /**
     * Mark as read a customer record.
     *
     * @param int $id customer ID
     */
    public static function mark_as_approved_requests( $id ) {
        global $wpdb;

        $approved_id = (isset($id) && $id != '') ? intval($id) : null;

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

                    $ays_quiz_fr_answers = (isset($question['answers']) && !empty($question['answers'])) ? $question['answers'] : array();
                    $ays_quiz_fr_correct = (isset($question['correct']) && !empty($question['correct'])) ? $question['correct'] : array();

                    foreach ($ays_quiz_fr_answers as $ans_k => $answer) {
                        $ays_quiz_fr_answers[$ans_k]['correct'] = in_array( $ans_k, $ays_quiz_fr_correct ) ? 1 : 0;
                    }

                    $ays_quiz_fr_text_answer = (isset($question['text_answer']) && !empty($question['text_answer'])) ? $question['text_answer'] : array();
                    $ays_quiz_fr_number_answer = (isset($question['number_answer']) && !empty($question['number_answer'])) ? $question['number_answer'] : array();
                    $ays_quiz_fr_question_array['question'] = $ays_quiz_fr_question;
                    $ays_quiz_fr_question_array['type'] = $ays_quiz_fr_type;
                    $ays_quiz_fr_question_array['category'] = $ays_quiz_fr_category;
                    $ays_quiz_fr_question_array['answers'] = $ays_quiz_fr_answers;
                    $ays_quiz_fr_question_array['text_answer'] = $ays_quiz_fr_text_answer;
                    $ays_quiz_fr_question_array['number_answer'] = $ays_quiz_fr_number_answer;

                    $front_requests_questions[] = $ays_quiz_fr_question_array;
                }
            }
        }
        
        $questions_ids = '';
        $max_id = Quiz_Maker_Admin::get_max_id('quizes');
        $ordering = ( $max_id != NULL ) ? ( $max_id + 1 ) : 1;

        $create_date = current_time( 'mysql' );
        $user_id = get_current_user_id();

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
            'active_date_message' => __("The quiz has expired!"),
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
                $placeholder = '';
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
                $ordering = 1;
                foreach ($front_requests_question['answers'] as $ans_key => $fr_answer) {
                    $correct = (isset($fr_answer['correct']) && $fr_answer['correct'] != null) ? 1 : 0 ;
                    $front_req_answer = (isset($fr_answer['title']) && $fr_answer['title'] != '') ? $fr_answer['title'] : $fr_answer['title'] ;
                    $placeholder = '';
                    $wpdb->insert($answers_table, array(
                        'question_id' => $question_id,
                        'answer'      => esc_sql( trim($front_req_answer) ),
                        'correct' 	  => $correct,
                        'ordering' 	  => $ordering,
                        'placeholder' => $placeholder
                    ));
                    $ordering++;
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
        
    }

    /** Text displayed when no customer data is available */
    public function no_items() {
        echo __( 'There are no results yet.', $this->plugin_name );
    }


    /**
     * Render a column when no column specific method exist.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'user_id': 
            case 'quiz_title':
            case 'request_date':
            case 'status':
            case 'approved':
            case 'id':
                return $item[ $column_name ];
                break;
            default:
                return print_r( $item, true ); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" class="ays_result_delete" name="bulk-delete[]" value="%s" />', $item['id']
        );
    }

    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_quiz_title( $item ) {
        global $wpdb;

        $delete_nonce = wp_create_nonce( $this->plugin_name . '-delete-requests' );
        $ays_quiz_frontend_requests_quiz_title = stripcslashes($item['quiz_title']);
        
        $title = sprintf('<a href="?page=%s-each&quiz=%d">' . $ays_quiz_frontend_requests_quiz_title . '</a>', esc_attr($_REQUEST['page']), absint($item['id']), $ays_quiz_frontend_requests_quiz_title);

        $actions = array(
            'edit' => sprintf('<a href="?page=%s-each&quiz=%d">' . __('View', $this->plugin_name) . '</a>', esc_attr($_REQUEST['page']), absint($item['id']), $ays_quiz_frontend_requests_quiz_title),
            'delete' => sprintf( '<a class="ays_confirm_del" data-message="this report" href="?page=%s&action=%s&requests=%s&_wpnonce=%s">Delete</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce )
        );

        return $title . $this->row_actions( $actions );
    }
    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_user_id( $item ) {
        global $wpdb;

        $user = get_user_by('id',$item['user_id']);
        $user_name = $user->user_nicename;

        return $user_name;
    }

    function column_approved( $item ) {
        $request_id = (isset($item['id']) && $item['id'] != null) ? absint(intval($item['id'])) : null;
		if ($request_id == null) {
			return false;
		}
		$approved = (isset($item['approved']) && $item['approved'] == 'approved' ) ? true : false;
		$quiz_id  = (isset($item['quiz_id']) && $item['quiz_id'] != null) ? absint(intval($item['quiz_id'])) : null;

		if ($approved) {
			if ($quiz_id != null) {
				$check_if_quiz_exists = Quiz_Maker_Admin::ays_get_quiz_by_id($quiz_id);
				if ($check_if_quiz_exists) {
					return sprintf('<a href="?page=%s&action=%s&quiz=%s" target="_blank">%s</a>', 'quiz-maker', 'edit', absint($quiz_id) , __( 'Go to Quiz' , $this->plugin_name ) );
				}else{
					return sprintf('<input type="button" class="button primary ays_quiz_approve_button" value="%s" data-id="%d" data-type="%s" />', __("Create Again", $this->plugin_name) , $request_id , "create" );
				}
			}
		}else{
			return sprintf('<input type="button" class="button primary ays_quiz_approve_button" value="%s" data-id="%d" data-type="%s"/>', __("Approve", $this->plugin_name) , $request_id , "approve" );
		}
    }

    function column_status( $item ) {
        global $wpdb;
        $request_id = (isset($item['id']) && $item['id'] != null) ? absint(intval($item['id'])) : null;

		if ($request_id == null) {
			return false;
		}

        $quiz_ids = $this->get_requests_quiz_id();

        $quiz_status = '';

        foreach ($quiz_ids as $key => $quiz_id) {
            $id  = (isset($quiz_id['quiz_id']) && $quiz_id['quiz_id'] != null) ? absint(intval($quiz_id['quiz_id'])) : null;
            
            $requests_table = $wpdb->prefix."aysquiz_requests";
            $quizes_table = $wpdb->prefix."aysquiz_quizes";
            
            $quiz_status = '';
            
            if($id != null){
                $quiz_status_sql = "SELECT published FROM {$quizes_table} WHERE id=".$id;
                $quiz_status_result = $wpdb->get_var($quiz_status_sql);
            }

            $status = (isset($quiz_status_result) && $quiz_status_result == '1') ? $quiz_status_result : '0';
            
            if($status == '1'){
                $quiz_status = 'Published';
            }else if($status == '0'){
                $quiz_status = 'Unpublished';
            }
                            
            $wpdb->update(
                $requests_table,
                array(
                    'status' => $quiz_status,
                ),
                array( 'quiz_id' => $id ),
				array( '%s'),
				array( '%d' )
            );
        }
        return $item['status'];
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    function get_columns() {
        $columns = array(
            'cb'                    => '<input type="checkbox" />',
            'quiz_title'            => __( 'Quiz Title', $this->plugin_name ),
            'user_id'               => __( 'User Name', $this->plugin_name ),
            'request_date'          => __( 'Request Date', $this->plugin_name ),
            'status'                => __( 'Status', $this->plugin_name ),
            'approved'              => __( 'Approved', $this->plugin_name ),
            'id'                    => __( 'ID', $this->plugin_name ),
        );

        return $columns;
    }


    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'quiz_title'    => array( 'quiz_title', true ),
            'user_id'       => array( 'user_id', true ),
            'request_date'  => array( 'request_date', true ),
            'id'            => array( 'id', true ),
        );

        return $sortable_columns;
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions() {
        $actions = array(
            'mark-as-approved' => __( 'Mark as approved', $this->plugin_name),
            // 'mark-as-not-approved' => __( 'Mark as not approved', $this->plugin_name),
            'bulk-delete' => 'Delete'
        );

        return $actions;
    }


    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items() {

        $this->_column_headers = $this->get_column_info();

        /** Process bulk action */
        $this->process_bulk_action();

        $per_page     = $this->get_items_per_page( 'quiz_frontend_requests_per_page', 20 );

        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();

        $this->set_pagination_args( array(
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page //WE have to determine how many items to show on a page
        ) );

        $this->items = self::get_requests( $per_page, $current_page );
    }

    public function process_bulk_action() {
        //Detect when a bulk action is being triggered...
        $message = 'deleted';
        if ( 'delete' === $this->current_action() ) {

            // In our file that handles the request, verify the nonce.
            $nonce = esc_attr( $_REQUEST['_wpnonce'] );

            if ( ! wp_verify_nonce( $nonce, $this->plugin_name . '-delete-requests' ) ) {
                die( 'Go get a life script kiddies' );
            }
            else {
                self::delete_requests( absint( $_GET['requests'] ) );

                // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
                // add_query_arg() return the current url

                $url = esc_url_raw( remove_query_arg(array('action', 'requests', '_wpnonce')  ) ) . '&status=' . $message;
                wp_redirect( $url );
            }

        }

        // If the delete bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )
            || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )
        ) {

            $delete_ids = esc_sql( $_POST['bulk-delete'] );

            // loop over the array of record IDs and delete them
            foreach ( $delete_ids as $id ) {
                self::delete_requests( $id );

            }

            // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
            // add_query_arg() return the current url

            $url = esc_url_raw( remove_query_arg(array('action', 'requests', '_wpnonce')  ) ) . '&status=' . $message;
            wp_redirect( $url );
        }

        // If the mark-as-approved bulk action is triggered
        if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'mark-as-approved' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'mark-as-approved' ) ) {
            $approved = esc_sql( $_POST['bulk-delete'] );
            // loop over the array of record IDs and delete them
            foreach ( $approved as $id ) {
                self::mark_as_approved_requests( $id );
            }

            // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
            // add_query_arg() return the current url

            $url = esc_url_raw( remove_query_arg(array('action', 'requests', '_wpnonce') ) );

            $message = 'marked-as-approved';
            $url = add_query_arg( array(
                'status' => $message,
            ), $url );
            wp_redirect( $url );
        }

        // If the mark-as-unread bulk action is triggered
        // if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'mark-as-not-approved' ) || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'mark-as-not-approved' ) ) {

        //     $delete_ids = esc_sql( $_POST['bulk-delete'] );

        //     // loop over the array of record IDs and delete them
        //     foreach ( $delete_ids as $id ) {
        //         self::mark_as_not_approved_requests( $id );
        //     }

        //     // esc_url_raw() is used to prevent converting ampersand in url to "#038;"
        //     // add_query_arg() return the current url

        //     $url = esc_url_raw( remove_query_arg(array('action', 'result', '_wpnonce') ) );

        //     $message = 'mark-as-not-approved';
        //     $url = add_query_arg( array(
        //         'status' => $message,
        //     ), $url );

        //     wp_redirect( $url );
        // }

    }

    public function results_notices(){
        $status = (isset($_REQUEST['status'])) ? sanitize_text_field( $_REQUEST['status'] ) : '';

        if ( empty( $status ) )
            return;

        if ( 'deleted' == $status )
            $updated_message = esc_html( __( 'Requests deleted.', $this->plugin_name ) );

        if ( empty( $updated_message ) )
            return;

        ?>
        <div class="notice notice-success is-dismissible">
            <p> <?php echo $updated_message; ?> </p>
        </div>
        <?php
    }
}
