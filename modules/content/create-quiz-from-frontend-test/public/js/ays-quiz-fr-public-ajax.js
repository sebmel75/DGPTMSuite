(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	 $.fn.serializeFormJSON = function () {
        var o = {},
            a = this.serializeArray();
        $.each(a, function () {
            if (o[this.name]) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
        });
        return o;
    };

	$(document).ready(function(){
		$(document).on('click','.ays-quiz-front-requests-quiz-submit',function(){

			$(this).parents('.ays-quiz-front-requests-body').find('.ays-quiz-front-requests-preloader').css('display', 'flex');
			if($(this).parents('.ays-quiz-front-requests-body').find('#ays_quiz_fr_quiz_title').val() == ''){            
				swal.fire({
					type: 'error',
					text: "Quiz title can't be empty"
				});
				$(this).parents('.ays-quiz-front-requests-body').find('.ays-quiz-front-requests-preloader').css('display', 'none');
				return false;
			}
			var franswers = $(this).parents('.ays-quiz-front-requests-body').find('.ays-quiz-fr-radio-answer').find('.ays-quiz-fr-answer-input');
			var emptyAnswers = 0;
			for(var j = 0; j < franswers.length; j++){
				var parent =  franswers.eq(j).parents('.ays-quiz-fr-quiz-question-content');
				var questionType = parent.find('.ays-quiz-fr-quest-type').val();
	
				if ( questionType == 'text' ) {
					var answerVal = parent.find('.ays-quiz-fr-answer_text').find('.ays-quiz-fr-answer-input').val();
					if(answerVal == ''){
						emptyAnswers++;
						break;
					}
				} else {
					if(franswers.eq(j).val() == ''){
						emptyAnswers++;
						break;
					}
				}
			}

			if(emptyAnswers > 0){
				swal.fire({
					type: 'error',
					text: "You must fill all answers"
				});
				$(this).parents('.ays-quiz-front-requests-body').find('.ays-quiz-front-requests-preloader').css('display', 'none');
				return false;
			}

			var form = $(this).parents('form#ays_quiz_front_request_form');
            var data = form.serializeFormJSON();

            var action = 'insert_quiz_fr_data_to_db';
			var loader = $(this).parents('.ays-quiz-front-requests-body').find('.ays-quiz-front-requests-preloader');
            data.action = action;
            $.ajax({
				url: ays_quiz_fr_public_ajax.ajax_url,
                method: 'POST',
                dataType: 'json',
                data:data,
                success:function (response){
					loader.css('display', 'none');
					if ( response.status == true ) {
						var status = 'success';
						if( response.data == false ){
							status = 'error';
						}

						swal.fire({
							type: status,
							text: response.message
						});
					}else{
						swal.fire({
							type: 'error',
							text: response.message
						});
					}
					aysResetRequestForm( form );
                }
            });
		});
	});

	function aysResetRequestForm( form ){
		form.find('#ays_quiz_fr_quiz_title').val('');
		form.find('#ays_quiz_fr_quiz_category').val('1');

		var dataCount = form.find('.ays-quiz-fr-quiz-question-container .ays-quiz-fr-quiz-question-content').length;
		var questionCat = form.find('.ays-quiz-fr-question-category select').html();

		var count = 1;

		var content = "";
			content += '<div class="ays-quiz-fr-quiz-question-content" data-id="'+count+'">';
				content += '<div class="ays-quiz-fr-row">';
					content += '<div class="ays-quiz-fr-col-8">';
						content += '<div class="ays-quiz-fr-quest-title">';
							content += '<span class="ays_quiz_fr_answers_container-title">' + 'Question title' + '</span>';
							content += '<textarea id="ays_quiz_fr_question_'+count+'" class="ays-quiz-fr-question" name="ays_quiz_fr_question['+count+'][question]"></textarea>';
						content += '</div>';
					content +='</div>';
					content += '<div class="ays-quiz-fr-col-4">';
						content += '<div class="ays-quiz-fr-question-type">';
							content += '<select id="ays_quiz_fr_quest_type_'+count+'" class="ays-quiz-fr-quest-type" name="ays_quiz_fr_question['+count+'][type]">';
								content += '<option value="radio">Radio</option>';
								content += '<option value="checkbox">Checkbox</option>';
								content += '<option value="select">Dropdown</option>';
								content += '<option value="text">Text</option>';
								content += '<option value="number">Number</option>';
							content += '</select >';
						content += '</div>';

						content +='<div class="ays-quiz-fr-question-category">';	
								content += '<select id="ays_quiz_fr_quest_category_'+count+'" name="ays_quiz_fr_question['+count+'][category]" class="ays-quiz-fr-quest-category">';
									content += questionCat;
								content += '</select >';
						content +='</div>';
					content +='</div>';
				content +='</div>';

				content +='<div class="ays-quiz-fr-answer">';
					content += '<span class="ays_quiz_fr_answers_container-title">' + 'Answers' + '</span>';
					content += '<div class="ays-quiz-fr-row">';
						content += '<div class="ays-quiz-fr-answer-content ays-quiz-fr-col-8">';
							content += '<div class="ays-quiz-fr-radio-answer">';
								content += '<div class="ays-quiz-fr-answer-row">';
									content += '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="1" title="' + "Correct answer" + '" checked>';
									content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+count+'][answers][1][title]">';
									content += '<a href="javascript:void(0)" class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
										content += '<i class="ays_fa_fr ays_fa_fr_times"></i>';
									content += '</a>';
								content += '</div>';
								content += '<div class="ays-quiz-fr-answer-row">';
									content += '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="2" title="' + "Correct answer" + '">';
									content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+count+'][answers][2][title]">';
									content += '<a href="javascript:void(0)" class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
										content += '<i class="ays_fa_fr ays_fa_fr_times"></i>';
									content += '</a>';
								content += '</div>';
								content += '<div class="ays-quiz-fr-answer-row">';
									content += '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="3" title="' + "Correct answer" + '">';
									content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+count+'][answers][3][title]">';
									content += '<a href="javascript:void(0)" class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
										content += '<i class="ays_fa_fr ays_fa_fr_times"></i>';
									content += '</a>';
								content += '</div>';
							content += '</div>';
						content += '</div>';
					content += '</div>';

					content += '<hr>';
					
					content += '<div class="ays-quiz-fr-questions-actions-container">';
						content += '<div class="ays-quiz-fr-quiz-add-answer-container">';
							content += '<a href="javascript:void(0)" class="ays-quiz-fr-add-answer">';
								content += '<i class="ays_fa_fr ays_fa_fr_plus_square"></i>';
								content += '<span>' + 'Add answer' + '</span>';	
							content += '</a>';	
						content += '</div>';

						content += '<div class="ays-quiz-fr-answer-duplicate-delete-content">';
							content += '<a href="javascript:void(0)" class="ays-quiz-fr-clone-question" title="' + "Duplicate" + '">';
								content += '<i class="ays_fa_fr ays_fa_fr_clone"></i>';
							content += '</a>';
							content += '<a href="javascript:void(0)" class="ays-quiz-fr-delete-question" title="' + "Delete" + '">';
								content += '<i class="ays_fa_fr ays_fa_fr_trash_o"></i>';
							content += '</a>';
						content += '</div>';
					content += '</div>';
				content +='</div>';

			content += '</div>';

		var questContainer = form.find('.ays-quiz-fr-quiz-question-container');
		questContainer.html(content);
	}

})( jQuery );
