<?php

?>
<div id="tab2" class="ays-quiz-tab-content ays-quiz-tab-content-active" >
<div class="wrap" style="position:relative;">
    <div class="container-fluid">
        <form method="post" class="ays-quiz-front-requests-shortcode-form" id="ays_quiz_front-requests_shortcode_form">
            <fieldset>
                <legend>
                    <strong style="font-size:30px;">[ ]</strong>
                    <h5 class="ays-subtitle"><?php echo __('Frontend Request Shortcode',$this->plugin_name)?></h5>
                </legend>
                <div class="form-group row">
                    <div class="col-sm-4">
                        <label for="ays_quiz_front_request_shortcode">
                            <?php echo __( "Shortcode", $this->plugin_name ); ?>
                            <a class="ays_help" data-toggle="tooltip" title="<?php echo __('Please copy the following shortcode and paste it into your preferred post. It will allow users to send a request for building a quiz with simple settings (quiz title, category, question type, answers, etc.). Find the list of the requests on the Requests page, which is located on the Quiz Maker left navbar. For accepting the request, the admin needs to click on the Approve button next to the given quiz.', $this->plugin_name); ?>">
                                <i class="ays_fa ays_fa_info_circle"></i>
                            </a>
                        </label>
                    </div>
                    <div class="col-sm-8">
                        <input type="text" id="ays_quiz_front_request_shortcode" class="ays-text-input" onclick="this.setSelectionRange(0, this.value.length)" readonly="" value='[ays_quiz_frontend_requests]'>
                    </div>
                </div>
                <hr>
                <div class="form-group row">
                    <div class="col-sm-4">
                        <label for="ays_quiz_front_request_auto_aprove">
                            <?php echo __( "Enable auto-approve", $this->plugin_name ); ?>
                            <a class="ays_help" data-toggle="tooltip" title="<?php echo __('If the option is enabled, the user requests from the Request Form shortcode will automatically be approved and added to the Quizzes page.', $this->plugin_name); ?>">
                                <i class="ays_fa ays_fa_info_circle"></i>
                            </a>
                        </label>
                    </div>
                    <div class="col-sm-8">
                        <input type="checkbox" id="ays_quiz_front_request_auto_aprove" name="ays_quiz_front_request_auto_aprove" value='on' <?php echo $ays_quiz_fr_auto_approve ? 'checked' : ''; ?>>
                    </div>
                </div>
                <hr>
                <div class="form-group row">
                    <div class="col-sm-4">
                        <label for="ays_quiz_front_request_send_email">
                            <?php echo __( "Send email to admin", $this->plugin_name ); ?>
                            <a class="ays_help" data-toggle="tooltip" title="<?php echo __('If you enable this option, the admin will receive an email each time a user creates a quiz on the Front-end.', $this->plugin_name); ?>">
                                <i class="ays_fa ays_fa_info_circle"></i>
                            </a>
                        </label>
                    </div>
                    <div class="col-sm-8">
                        <input type="checkbox" id="ays_quiz_front_request_send_email" name="ays_quiz_front_request_send_email" value='on' <?php echo $ays_quiz_fr_send_email ? 'checked' : ''; ?>>
                    </div>
                </div>
            </fieldset>

            <div>
            <?php 
                submit_button( __('Save', $this->plugin_name), 'primary', 'ays_fr_save' );
            ?>
            </div>
        </form>
</div>