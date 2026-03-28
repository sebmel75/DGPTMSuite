<?php
    $request_id = isset($_GET['quiz']) ? intval($_GET['quiz']) : null;

    if($request_id === null){
        wp_redirect( admin_url('admin.php') . '?page=' . $this->plugin_name . '-admin' );
    } 

    $data = $this->get_fr_quiz_data_by_id( $request_id );
    
    $title = ( isset( $data['quiz_title'] ) && $data['quiz_title'] != '' ) ? stripslashes( $data['quiz_title'] ) : '';

    $category_id = ( isset( $data['category_id'] ) && $data['category_id'] != '' ) ? absint( $data['category_id'] ) : 1;

    $quiz_cat_title = $this->get_quiz_cat_by_id( $category_id );
    $cat_title = ( isset( $quiz_cat_title ) && $quiz_cat_title != '' ) ? stripslashes( $quiz_cat_title ) : 'Uncategorized';

    $user_id = ( isset( $data['user_id'] ) && $data['user_id'] != '' ) ? absint( $data['user_id'] ) : 1;

    $quiz_data = ( isset( $data['quiz_data'] ) && $data['quiz_data'] != '' ) ? json_decode( $data['quiz_data'], true ) : '';

    $questions = ( isset( $quiz_data['ays_quiz_fr_question_data'] ) && !empty( $quiz_data['ays_quiz_fr_question_data'] ) ) ? $quiz_data['ays_quiz_fr_question_data'] : array();
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
    <div class="ays-quiz-frontend-request-content">
        <div class="form-group row">
            <div class="col-sm-3">
                <p class="ays-frontend-request-data-text ays-frontend-request-data"><?php echo __('Quiz Title', $this->plugin_name); ?></p>
            </div>
            <div class="col-sm-9">
                <p class="ays-frontend-request-data-text"><?php echo $title; ?></p>
            </div>
        </div>
        <hr>
        <div class="form-group row">
            <div class="col-sm-3">
                <p class="ays-frontend-request-data-text ays-frontend-request-data"><?php echo __('Quiz category', $this->plugin_name); ?></p>
            </div>
            <div class="col-sm-9">
                <p class="ays-frontend-request-data-text"><?php echo $cat_title; ?></p>
            </div>
        </div>
        <hr>
        <div class="form-group row">
            <div class="col-sm-3">
                <p class="ays-frontend-request-data-text ays-frontend-request-data"><?php echo __('Question', $this->plugin_name); ?></p>
            </div>
            <div class="col-sm-9">
            <p class="ays-frontend-request-data-text ays-frontend-request-data"><?php echo __('Question category', $this->plugin_name); ?></p>
            </div>
        </div>
        <hr>
        <?php
            foreach ( $questions as $key => $question ):
                $type = ( isset( $question['type'] ) && $question['type'] != '' ) ? stripslashes( $question['type'] ) : '';
                $text_answer = ( isset( $question['text_answer'] ) && $question['text_answer'] != '' ) ? stripslashes( esc_html( $question['text_answer'] ) ) : '';
                $number_answer = ( isset( $question['number_answer'] ) && $question['number_answer'] != '' ) ? stripslashes( esc_html( $question['number_answer'] ) ) : '';
                $corrects = ( isset( $question['correct'] ) && !empty( $question['correct'] ) ) ? $question['correct'] : array();
                $quest_cat_id = ( isset( $question['category'] ) && $question['category'] != '' ) ? absint( $question['category'] ) : 1;
                $quest_cat_title = $this->get_question_cat_by_id( $quest_cat_id );
                $quest_category_title = ( isset( $quest_cat_title ) && $quest_cat_title != '' ) ? stripslashes( esc_html($quest_cat_title) ) : 'Uncategorized';
        ?>
                <div class="form-group row">
                    <div class="col-sm-3">
                        <p class="ays-frontend-request-data-text">
                            <?php echo ( isset($question['question'] ) && $question['question'] != '' ) ? stripslashes( esc_html( $question['question'] ) ) : ''; ?>
                        </p>
                    </div>
                    <div class="col-sm-9">
                        <p class="ays-frontend-request-data-text">
                            <?php echo $quest_category_title ?>
                        </p>
                    </div>
                </div>
                <hr>
                <?php if( $type == 'text' ): 
                    $answer_title = $text_answer;
                ?>
                    <div class="form-group row">
                        <div class="col-sm-9">
                            <div class="row">
                                <p class="ays-frontend-request-data-text-answer">
                                    <?php echo  $answer_title; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php elseif( $type == 'number' ): 
                    $answer_title = $number_answer;
                ?>
                <div class="form-group row">
                    <div class="col-sm-9">
                        <div class="row">
                            <p class="ays-frontend-request-data-number-answer">
                                <?php echo  $answer_title; ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php else: 
                    $answers = ( isset( $question['answers'] ) && !empty( $question['answers'] ) ) ? $question['answers'] : array();
                    foreach ( $answers as $ans_key => $answer):
                        $ans_title = ( isset( $answer['title'] ) && $answer['title'] != '' ) ? stripslashes( esc_html( $answer['title'] ) ) : '';
                        switch ( $type ) {
                            case 'radio':
                            case 'dropdown':
                                $inp_type = 'radio';
                                $answer_title = $ans_title;
                                break;
                            case 'checkbox':
                                $inp_type = 'checkbox';
                                $answer_title = $ans_title;
                                break;
                            default:
                                $inp_type = 'radio';
                                $answer_title = $ans_title;
                                break;
                        }
                        $checked = '';
                        if( is_array( $corrects ) ){
                            if( in_array( $ans_key, $corrects ) ){
                                $checked = 'checked';
                            }else{
                                $checked = '';
                            }
                        }else{
                            if($ans_key == $corrects[$ans_key]){
                                $checked = 'checked';
                            }else{
                                $checked = 'checked';
                            }
                        }
                    ?>
                        <div class="form-group row">
                            <div class="col-sm-9">
                                <div class="row">
                                    <div class="ays-frontend-request-data-text-input-type">
                                        <input type="<?php echo  $inp_type; ?>" <?php echo $checked; ?> disabled>
                                    </div>
                                    <div>
                                        <p class="ays-frontend-request-data-text-answer">
                                            <?php echo $answer_title; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endforeach;
                    ?> 
                <?php endif; ?>
            <hr>
        <?php
            endforeach;
        ?>
    </div>
</div>

