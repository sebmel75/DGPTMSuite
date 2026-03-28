<?php
    require_once( QUIZ_MAKER_FRONTEND_REQUESTS_ADMIN_PATH . "/partials/actions/quiz-maker-frontend-requests-actions-options.php" );
    $heading = __( "Frontend Requests", $this->plugin_name);
?>


<div class="wrap" id="ays-quiz-frontend-requests-wrap">
    <div class="container-fluid">
        <input type="hidden" name="ays_question_tab" value="">
        <h1 class="wp-heading-inline">
            <?php echo __( esc_html( get_admin_page_title() ), $this->plugin_name ); ?>
        </h1>
        <div class="nav-tab-wrapper">
            <a href="<?php echo $tab_url . "tab1"; ?>" class="no-js nav-tab <?php echo ($tab == 'tab1') ? 'nav-tab-active' : ''; ?>">
                <?php echo __("Requests", $this->plugin_name);?>
            </a>
            <a href="<?php echo $tab_url . "tab2"; ?>" class="no-js nav-tab <?php echo ($tab == 'tab2') ? 'nav-tab-active' : ''; ?>">
                <?php echo __("Shortcode", $this->plugin_name);?>
            </a>
        </div>
        <?php
        	include_once("quiz-maker-frontend-requests-actions-".$tab.".php");
        ?>
    </div>
</div>
