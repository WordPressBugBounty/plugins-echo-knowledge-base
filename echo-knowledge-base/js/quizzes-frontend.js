jQuery( document ).ready( function( $ ) {

	$( document ).on( 'click', '.epkb-article-quiz__toggle', function() {
		const $button = $( this );
		const $quiz = $button.closest( '.epkb-article-quiz' );
		const $panel = $quiz.find( '.epkb-article-quiz__panel' );
		const isExpanded = $button.attr( 'aria-expanded' ) === 'true';

		$button.attr( 'aria-expanded', isExpanded ? 'false' : 'true' );
		$panel.prop( 'hidden', isExpanded );
		$quiz.toggleClass( 'epkb-article-quiz--open', ! isExpanded );
	} );

	$( document ).on( 'change', '.epkb-article-quiz__question input[type="radio"]', function() {
		const $input = $( this );
		const $question = $input.closest( '.epkb-article-quiz__question' );
		const $quiz = $input.closest( '.epkb-article-quiz' );
		const $feedback = $question.find( '.epkb-article-quiz__feedback' );
		const $feedbackState = $feedback.find( '.epkb-article-quiz__feedback-state' );

		if ( $question.hasClass( 'epkb-article-quiz__question--answered' ) ) {
			return;
		}

		const selectedChoice = parseInt( $input.val(), 10 );
		const correctChoice = parseInt( $question.data( 'correct-choice' ), 10 );
		const isCorrect = selectedChoice === correctChoice;

		$question
			.addClass( 'epkb-article-quiz__question--answered' )
			.toggleClass( 'epkb-article-quiz__question--correct', isCorrect )
			.toggleClass( 'epkb-article-quiz__question--incorrect', ! isCorrect );

		$question.find( 'input[type="radio"]' ).prop( 'disabled', true );
		$feedback
			.prop( 'hidden', false )
			.toggleClass( 'epkb-article-quiz__feedback--correct', isCorrect )
			.toggleClass( 'epkb-article-quiz__feedback--incorrect', ! isCorrect );
		$feedbackState.text( isCorrect ? epkbQuizFrontend.correct : epkbQuizFrontend.incorrect );

		updateSummary( $quiz );
	} );

	function updateSummary( $quiz ) {
		const $questions = $quiz.find( '.epkb-article-quiz__question' );
		const answeredCount = $questions.filter( '.epkb-article-quiz__question--answered' ).length;
		const totalCount = $questions.length;

		if ( answeredCount !== totalCount || totalCount === 0 ) {
			return;
		}

		const correctCount = $questions.filter( '.epkb-article-quiz__question--correct' ).length;
		const $summary = $quiz.find( '.epkb-article-quiz__summary' );
		const summaryPrefix = epkbQuizFrontend.summaryPrefix ? epkbQuizFrontend.summaryPrefix + ' ' : '';
		$summary.find( '.epkb-article-quiz__summary-score' ).text(
			summaryPrefix + correctCount + '/' + totalCount
		);
		$summary.prop( 'hidden', false );

		submitQuizAttempt( $quiz, correctCount, totalCount );
	}

	function submitQuizAttempt( $quiz, correctCount, totalCount ) {
		if ( $quiz.data( 'notificationSubmitted' ) === 1 || epkbQuizFrontend.notificationsEnabled !== 'on' || ! epkbQuizFrontend.ajaxUrl || ! epkbQuizFrontend.nonce ) {
			return;
		}

		const answers = [];
		let hasInvalidAnswer = false;

		$quiz.find( '.epkb-article-quiz__question' ).each( function() {
			const $question = $( this );
			const questionId = String( $question.data( 'question-id' ) || '' );
			const selectedChoice = parseInt( $question.find( 'input[type="radio"]:checked' ).val(), 10 );

			if ( ! questionId || isNaN( selectedChoice ) ) {
				hasInvalidAnswer = true;
				return false;
			}

			answers.push( {
				question_id: questionId,
				selected_choice: selectedChoice
			} );
		} );

		if ( hasInvalidAnswer ) {
			return;
		}

		$quiz.data( 'notificationSubmitted', 1 );
		$.ajax( {
			url: epkbQuizFrontend.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'epkb_submit_quiz_attempt',
				_wpnonce_epkb_ajax_action: epkbQuizFrontend.nonce,
				quiz_id: $quiz.data( 'quiz-id' ),
				correct_count: correctCount,
				total_count: totalCount,
				answers_json: JSON.stringify( answers )
			}
		} );
	}
} );
