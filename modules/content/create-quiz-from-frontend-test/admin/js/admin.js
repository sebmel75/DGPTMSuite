(function ($) {
    'use strict';
    $(document).ready(function(){
        $(document).on('click', '[data-slug="quiz-maker-add-on-create-quiz-from-frontend"] .deactivate a', function () {

            swal({
                html:"<h2>Do you want to keep data or permanently delete the plugin?</h2><ul><li>Keep Data: Your data will be saved for upgrade.</li><li>Delete: Your data will be deleted completely.</li></ul>",
                footer: '<a href="" class="ays-quiz-fr-temporary-deactivation">Temporary deactivation</a>',
                type: 'question',
                showCloseButton: true,
                showCancelButton: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Keep Data',
                cancelButtonText: 'Delete',
                confirmButtonClass: "ays-quiz-fr-upgrade-button"
            }).then(function(result) {

                if( result.dismiss && result.dismiss == 'close' ){
                    return false;
                }

                var upgrade_plugin = false;
                if (result.value) {upgrade_plugin = true};
                var data = {action: 'deactivate_plugin_option_fr', upgrade_plugin: upgrade_plugin};
                $.ajax({
                    url: quiz_maker_fr_admin_ajax.ajax_url,
                    method: 'post',
                    dataType: 'json',
                    data: data,
                    success:function () {
                        window.location = $(document).find('[data-slug="quiz-maker-add-on-create-quiz-from-frontend"]').find('.deactivate').find('a').attr('href');
                    }
                });
            });
            return false;
        });

        $(document).on('click', '.ays-quiz-fr-temporary-deactivation', function (e) {
            e.preventDefault();

            $(document).find('.ays-quiz-fr-upgrade-button').trigger('click');

        });
    })

})(jQuery);
