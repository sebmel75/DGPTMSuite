(function( $ ) {
	'use strict';

	$(document).ready(function(){

		$(document).on('click', '.ays_quiz_approve_button',function(){
			var action = 'approve_front_requests';
			var approvedId = $(this).data('id');
			var link = $(this).data('link');
			var $_this = $(this);
            $.ajax({
				url: fr_quiz_maker_admin_ajax.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data:{
					action: action,
					approved_id : approvedId
				},
                success:function(response){
					if (response.status) {
						var goToQuizLink = '<a href="?page=quiz-maker&amp;action=edit&amp;quiz='+response.quiz_id+'" target="_blank">Go to Quiz</a>';
						parent = $_this.parent();
						parent.append(goToQuizLink);
						$_this.remove();
					}else{
						swal.fire({
							type: 'info',
							html: "<h2>Can't load resource.</h2><br><h6>Maybe something went wrong.</h6>"
						});
					}
                }
            });
		});
		
	});

})( jQuery );
