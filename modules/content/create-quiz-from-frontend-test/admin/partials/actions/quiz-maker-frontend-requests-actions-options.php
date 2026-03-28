<?php

    $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'tab1';

    $tab_url = "?page=".$this->plugin_name."-admin&tab=";

    $front_requests_data = $this->get_fr_quiz_data();

    if (isset($_POST['ays_fr_save'])) {
        $this->frontend_requests_obj->store_data();
    }

    $options = array(
        'ays_quiz_fr_auto_approve' => 'off',
    );

    $front_requests_options = ($this->quiz_settings_obj->ays_get_setting('front_requests') === false) ? json_encode(array()) : $this->quiz_settings_obj->ays_get_setting('front_requests');

    if (! empty($front_requests_options) ) {
        $front_requests_options = json_decode($front_requests_options, true);
    }

    if (! empty($front_requests_options) ) {
        $options = $front_requests_options;
    }

    // auto approve
    $options['ays_quiz_fr_auto_approve'] = ! isset( $options['ays_quiz_fr_auto_approve'] ) ? 'off' : $options['ays_quiz_fr_auto_approve'];
    $ays_quiz_fr_auto_approve = (isset($options['ays_quiz_fr_auto_approve']) && $options['ays_quiz_fr_auto_approve'] == 'on') ? true : false;

    // send email to admin
    $options['ays_quiz_fr_send_email'] = ! isset( $options['ays_quiz_fr_send_email'] ) ? 'off' : $options['ays_quiz_fr_send_email'];
    $ays_quiz_fr_send_email = (isset($options['ays_quiz_fr_send_email']) && $options['ays_quiz_fr_send_email'] == 'on') ? true : false;

?>