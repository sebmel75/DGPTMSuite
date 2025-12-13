<?php
?>
<div id="tab1" class="ays-quiz-tab-content ays-quiz-tab-content-active">
    <div class="wrap">
        <div id="poststuff">
            <div id="post-body" class="metabox-holder">
                <div id="post-body-content">
                    <div class="meta-box-sortables ui-sortable">
                        <?php
                            $this->frontend_requests_obj->views();
                        ?>
                        <form method="post">
                            <?php
                                $this->frontend_requests_obj->prepare_items();
                                $this->frontend_requests_obj->display();
                            ?>
                        </form>
                    </div>
                </div>
            </div>
            <br class="clear">
        </div>
    </div>
</div>
