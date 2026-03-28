(function( $ ) {
	'use strict';

	$(document).ready(function(){

		// $('input[type=radio]').change(function() {
		// 	$('input[type=radio]:checked').not(this).prop('checked', false);
		// });

		//Clone the question
		$(document).on('click','.ays-quiz-fr-clone-question', function(){
			var thisQuestion = $(this).parents('.ays-quiz-fr-quiz-question-content');
			var questContentClone = $(this).parents('.ays-quiz-fr-quiz-question-content').clone(true, false);
			var questContainer = $(this).parents('.ays-quiz-fr-quiz-question-container');
			var clonedElementType = thisQuestion.find('.ays-quiz-fr-quest-type').val();
			var clonedElementCategory = thisQuestion.find('select.ays-quiz-fr-quest-category').val();

			// var dataCount = thisQuestion.parents('.ays-quiz-fr-quiz-question-container').find('.ays-quiz-fr-quiz-question-content').length + 1;
			var dataCount = thisQuestion.parents('.ays-quiz-fr-quiz-question-container').attr('data-id');
			dataCount = parseInt( dataCount ) + 1;
			questContentClone.attr('data-id', dataCount);
			thisQuestion.parents('.ays-quiz-fr-quiz-question-container').attr('data-id', dataCount);


			var questType = questContentClone.find('select.ays-quiz-fr-quest-type');
			var questCategory = questContentClone.find('select.ays-quiz-fr-quest-category');
			var questTitle = questContentClone.find('textarea.ays-quiz-fr-question');
			var questTypeText = questContentClone.find('.ays-quiz-fr-answer_text').find('input.ays-quiz-fr-answer-input');
			var questTypeNumber = questContentClone.find('.ays-quiz-fr-answer_number').find('input.ays-quiz-fr-answer-input');

			questType.attr('name','ays_quiz_fr_question['+ dataCount +'][type]');
			questType.attr('id','ays_quiz_fr_quest_type_'+ dataCount +'');

			questCategory.attr('name','ays_quiz_fr_question['+ dataCount +'][category]');
			questCategory.attr('id','ays_quiz_fr_quest_category_'+ dataCount +'');
			
			questTitle.attr('name','ays_quiz_fr_question['+ dataCount +'][question]');
			questTitle.attr('id','ays_quiz_fr_question_'+ dataCount +'');
			
			questTypeText.attr('name','ays_quiz_fr_question['+ dataCount +'][text_answer]');
			questTypeNumber.attr('name','ays_quiz_fr_question['+ dataCount +'][number_answer]');

			if(clonedElementType != 'text' && clonedElementType != 'number') {
				var answerDiv = questContentClone.find('.ays-quiz-fr-answer').find('.ays-quiz-fr-radio-answer div');
				
				for (var i = 0; i <= answerDiv.length; i++) {
					answerDiv.eq(i).find('.ays-quiz-fr-right-answer').attr('name', 'ays_quiz_fr_question['+ dataCount +'][correct][]');
					answerDiv.eq(i).find('.ays-quiz-fr-answer-input').attr('name', 'ays_quiz_fr_question['+ dataCount +'][answers]['+(i+1)+'][title]');
				}
			}
			questContentClone.insertAfter(thisQuestion);

			questType.val(clonedElementType);
			questCategory.val(clonedElementCategory);
		});

		//Delete the question
		$(document).on('click','.ays-quiz-fr-delete-question',function(){
			if($(this).parents('.ays-quiz-fr-quiz-question-container').find('.ays-quiz-fr-quiz-question-content').length == 1){
                swal.fire({
                    type: 'warning',
                    text:'Sorry minimum count of questions should be 1'
                });
                return false;
            }
			// var questContent = $(this).parents('.ays-quiz-fr-quiz-question-content');
			// var answerCount = $(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-radio-answer');
			// var questType = $(this).parents('.ays-quiz-fr-quiz-question-content').find('select.ays-quiz-fr-quest-type');
			// var count = 1;
			// for (var i = 1; i < questContent.length; i++) {
			// 	count ++ ;
			// 	questContent.eq(i).attr('data-count',(i));
			// 	questContent.eq(i).find('.ays-quiz-fr-quest-title input.ays-quiz-fr-question').attr('id','ays_quiz_fr_quest_title_'+i+'');
			// 	questContent.eq(i).find('.ays-quiz-fr-quest-title input.ays-quiz-fr-question').attr('name','ays_quiz_fr_question['+i+'][question]');
				
			// 	questContent.eq(i).find('.ays-quiz-fr-question-category select.ays-quiz-fr-quest-category').attr('id','ays_quiz_fr_quest_category_'+i+'');
			// 	questContent.eq(i).find('.ays-quiz-fr-question-category select.ays-quiz-fr-quest-category').attr('name','ays_quiz_fr_question['+i+'][category]');
				
			// 	questContent.eq(i).find('.ays-quiz-fr-question-type select.ays-quiz-fr-quest-type').attr('id','ays_quiz_fr_quest_type_'+i+'');
			// 	questContent.eq(i).find('.ays-quiz-fr-question-type select.ays-quiz-fr-quest-type').attr('name','ays_quiz_fr_question['+i+'][type]');

			// 	questContent.eq(i).find('.ays-quiz-fr-text-answer input.ays-quiz-fr-answer-input').attr('name','ays_quiz_fr_question['+i+'][text_answer]');
			// }
			// for (var j = 0; j <= answerCount.length-1; j++) {
			// 	questContent.eq(count-1).find('.ays-quiz-fr-radio-answer div').eq(j).find('input.ays-quiz-fr-answer-input').attr('name','ays_quiz_fr_question['+(count-1)+'][answers]['+(j+1)+'][title]');
			// 	questContent.eq(count-1).find('.ays-quiz-fr-radio-answer').eq(j).find('input.ays-quiz-fr-right-answer').eq(j).attr('name','ays_quiz_fr_question['+(count-1)+'][correct][]');
			// }
			// $(this).parents('.ays-quiz-fr-quiz-question-container').next().remove();
			$(this).parents('.ays-quiz-fr-quiz-question-content').remove();
		});
		
		//Add New Question
		$(document).on('click', '.ays-quiz-fr-add-question', function(){
			var questContainer = $(this).parents('.ays-quiz-front-requests-body').find('.ays-quiz-fr-quiz-question-container');
			var dataCount = questContainer.attr('data-id');
			var questionCat = $(this).parents('.ays-quiz-front-requests-body').find('.ays-quiz-fr-question-category select').html();

			var count = parseInt(dataCount)+1;

			var content = "";
				content += '<div class="ays-quiz-fr-quiz-question-content" data-id="'+count+'">';
					content += '<div class="ays-quiz-fr-row">';
						content += '<div class="ays-quiz-fr-col-8">';
							content += '<div class="ays-quiz-fr-quest-title">';
								content += '<span class="ays_quiz_fr_answers_container-title">' + 'Question title' + '</span>';
								content += '<textarea type="text" id="ays_quiz_fr_question_'+count+'" class="ays-quiz-fr-question" name="ays_quiz_fr_question['+count+'][question]" placeholder="Question title"></textarea>';
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
								content += '<div class="ays-quiz-fr-radio-answer" data-answer="3">';
									content += '<div class="ays-quiz-fr-answer-row">';
										content += '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="1" title="' + "Correct answer" + '" checked>';
										content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+count+'][answers][1][title]">';
										content += '<a href="javascript:void(0)"  class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
											content += '<i class="ays_fa_fr ays_fa_fr_times"></i>';
										content += '</a>';
									content += '</div>';
									content += '<div class="ays-quiz-fr-answer-row">';
										content += '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="2" title="' + "Correct answer" + '">';
										content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+count+'][answers][2][title]">';
										content += '<a href="javascript:void(0)"  class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
											content += '<i class="ays_fa_fr ays_fa_fr_times"></i>';
										content += '</a>';
									content += '</div>';
									content += '<div class="ays-quiz-fr-answer-row">';
										content += '<input type="radio" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="3" title="' + "Correct answer" + '">';
										content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+count+'][answers][3][title]">';
										content += '<a href="javascript:void(0)"  class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
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
								content += '<a href="javascript:void(0)"  class="ays-quiz-fr-clone-question" title="' + "Duplicate" + '">';
									content += '<i class="ays_fa_fr ays_fa_fr_clone"></i>';
								content += '</a>';
								content += '<a href="javascript:void(0)"  class="ays-quiz-fr-delete-question" title="' + "Delete" + '">';
									content += '<i class="ays_fa_fr ays_fa_fr_trash_o"></i>';
								content += '</a>';
							content += '</div>';

						content += '</div>';
					content +='</div>';
				content += '</div>';

			questContainer.attr('data-id', count);
			questContainer.append(content);
		});

		//Change Type
		$(document).on('change','.ays-quiz-fr-quest-type',function(){
			var content = '';
			var questType = $(this).val();
			var dataCount = $(this).parents('.ays-quiz-fr-quiz-question-content').attr('data-id');
			var count = parseInt(dataCount);
			$(this).attr('data-type', questType);
			switch (questType) {
				case 'radio':
				case 'select':
				case 'checkbox':
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer').remove();
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer_text').remove();
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer_number').remove();
					var qtype = questType;
					if( questType == 'select' ){
						qtype = 'radio';
					}
					content +='<div class="ays-quiz-fr-answer">';
						content += '<span class="ays_quiz_fr_answers_container-title">' + 'Answers' + '</span>';
						content += '<div class="ays-quiz-fr-row">';
							content += '<div class="ays-quiz-fr-answer-content ays-quiz-fr-col-8">';
								content += '<div class="ays-quiz-fr-radio-answer">';
									content += '<div class="ays-quiz-fr-answer-row">';
										content += '<input type="' + qtype + '" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="1" title="' + "Correct answer" + '" checked>';
										content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+count+'][answers][1][title]">';
										content += '<a href="javascript:void(0)" class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
											content += '<i class="ays_fa_fr ays_fa_fr_times"></i>';
										content += '</a>';
									content += '</div>';
									content += '<div class="ays-quiz-fr-answer-row">';
										content += '<input type="' + qtype + '" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="2" title="' + "Correct answer" + '">';
										content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+count+'][answers][2][title]">';
										content += '<a href="javascript:void(0)" class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
											content += '<i class="ays_fa_fr ays_fa_fr_times"></i>';
										content += '</a>';
									content += '</div>';
									content += '<div class="ays-quiz-fr-answer-row">';
										content += '<input type="' + qtype + '" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+count+'][correct][]" value="3" title="' + "Correct answer" + '">';
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
					$(this).parents('.ays-quiz-fr-quiz-question-content').append(content);
					break;
				case 'text':
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer').remove();
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer_text').remove();
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer_number').remove();
					content +='<div class="ays-quiz-fr-answer_text">';
						content += '<span class="ays_quiz_fr_answers_container-title">' + 'Answer' + '</span>';
						content += '<div class="ays-quiz-fr-row">';
							content += '<div class="ays-quiz-fr-text-answer ays-quiz-fr-col-8">';
								content += '<div class="ays-quiz-fr-answer-row">';
									content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Correct answer" name="ays_quiz_fr_question['+count+'][text_answer]">';
								content += '</div>';
							content += '</div>';
						content += '</div>';

						content += '<hr>';
						
						content += '<div class="ays-quiz-fr-questions-actions-container">';
							content += '<div class="ays-quiz-fr-quiz-add-answer-container">';
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

					$(this).parents('.ays-quiz-fr-quiz-question-content').find('#ays_quiz_fr_quest_title_'+count).attr('name','ays_quiz_fr_question['+count+'][question]');
					$(this).parents('.ays-quiz-fr-quiz-question-content').append(content);
					break;
				case 'number':
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer').remove();
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer_text').remove();
					$(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-answer_number').remove();
					content +='<div class="ays-quiz-fr-answer_number">';
						content += '<span class="ays_quiz_fr_answers_container-title">' + 'Answer' + '</span>';
						content += '<div class="ays-quiz-fr-row">';
							content += '<div class="ays-quiz-fr-number-answer ays-quiz-fr-col-8">';
								content += '<div class="ays-quiz-fr-answer-row">';
									content += '<input type="number" class="ays-quiz-fr-answer-input" placeholder="Correct answer" name="ays_quiz_fr_question['+count+'][number_answer]">';
								content += '</div>';
							content += '</div>';
						content += '</div>';

						content += '<hr>';

						content += '<div class="ays-quiz-fr-questions-actions-container">';
							content += '<div class="ays-quiz-fr-quiz-add-answer-container">';
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

					$(this).parents('.ays-quiz-fr-quiz-question-content').find('#ays_quiz_fr_quest_title_'+count).attr('name','ays_quiz_fr_question['+count+'][question]');
					$(this).parents('.ays-quiz-fr-quiz-question-content').append(content);
					break;
				default:
					break;
			}
		});

		//Delete Answer 
		$(document).on('click','.ays-quiz-fr-delete-answer',function(){
			if($(this).parents('.ays-quiz-fr-radio-answer').find('div').length == 2){
                swal.fire({
                    type: 'warning',
                    text:'Sorry minimum count of answers should be 2'
                });
                return false;
            }
			
			var answerCount = $(this).parents('.ays-quiz-fr-answer-content').find('.ays-quiz-fr-radio-answer div');
			var questType = $(this).parents('.ays-quiz-fr-quiz-question-content').find('select.ays-quiz-fr-quest-type');
			var questContainer = $(this).parents('.ays-quiz-fr-quiz-question-content');
			var questCount = questContainer.attr('data-id');
			for (var i = 0; i <= answerCount.length; i++) {
				answerCount.eq(i).find('.ays-quiz-fr-answer-input').attr('name','ays_quiz_fr_question['+questCount+'][answers]['+ ( i + 1 ) +'][title]');
				answerCount.eq(i).find('.ays-quiz-fr-right-answer').attr('name','ays_quiz_fr_question['+questCount+'][correct][]');
				answerCount.eq(i).find('.ays-quiz-fr-right-answer').val( i+1 );
			}
			// $(this).parents('.ays-quiz-fr-answer-content').find('.ays-quiz-fr-radio-answer').attr('data-answer',''+answerCount.length-1+'');
			$(this).parent().remove();
			if( questContainer.find('.ays-quiz-fr-right-answer:checked').length == 0 ){
				questContainer.find('.ays-quiz-fr-answer-row:first-child .ays-quiz-fr-right-answer').prop('checked', true);
			}
		});

		// Add New Answer
		$(document).on('click','.ays-quiz-fr-add-answer',function(){
			var content = '';
			var answerCount = $(this).parents('.ays-quiz-fr-answer').find('.ays-quiz-fr-radio-answer div').length;
			var questCount = $(this).parents('.ays-quiz-fr-quiz-question-content').attr('data-id');
			var count = parseInt(answerCount)+1;
			var questType = $(this).parents('.ays-quiz-fr-quiz-question-content').find('.ays-quiz-fr-quest-type').val();

			switch (questType) {
				case 'radio':
				case 'checkbox':
				case 'select':
					var qtype = questType;
					if( questType == 'select' ){
						qtype = 'radio';
					}
					content += '<div class="ays-quiz-fr-answer-row">';
						content += '<input type="' + qtype + '" class="ays-quiz-fr-right-answer" name="ays_quiz_fr_question['+questCount+'][correct][]" value="'+count+'" title="' + "Correct answer" + '">';
						content += '<input type="text" class="ays-quiz-fr-answer-input" placeholder="Answer text" name="ays_quiz_fr_question['+questCount+'][answers]['+count+'][title]">';
						content += '<a href="javascript:void(0)"  class="ays-quiz-fr-delete-answer" title="' + "Delete" + '">';
							content += '<i class="ays_fa_fr ays_fa_fr_times"></i>';
						content += '</a>';
					content += '</div>';
					$(this).parents('.ays-quiz-fr-answer').find('.ays-quiz-fr-radio-answer').append(content);
					break;
				default:
					break;
			}
		});
	});

})( jQuery );
