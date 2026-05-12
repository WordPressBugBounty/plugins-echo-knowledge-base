<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX controller for quizzes admin page.
 */
class EPKB_Quizzes_Ctrl {

	const SOURCE_LOCK_PREFIX = 'epkb_quiz_source_lock_';
	const SOURCE_LOCK_TIMEOUT = 10 * MINUTE_IN_SECONDS;

	public function __construct() {
		add_action( 'wp_ajax_epkb_save_quiz', array( $this, 'save_quiz' ) );
		add_action( 'wp_ajax_nopriv_epkb_save_quiz', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

		add_action( 'wp_ajax_epkb_get_quiz', array( $this, 'get_quiz' ) );
		add_action( 'wp_ajax_nopriv_epkb_get_quiz', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

		add_action( 'wp_ajax_epkb_get_quiz_by_article', array( $this, 'get_quiz_by_article' ) );
		add_action( 'wp_ajax_nopriv_epkb_get_quiz_by_article', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

		add_action( 'wp_ajax_epkb_delete_quiz', array( $this, 'delete_quiz' ) );
		add_action( 'wp_ajax_nopriv_epkb_delete_quiz', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

		add_action( 'wp_ajax_epkb_generate_quiz', array( $this, 'generate_quiz' ) );
		add_action( 'wp_ajax_nopriv_epkb_generate_quiz', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

		add_action( 'wp_ajax_epkb_submit_quiz_attempt', array( $this, 'submit_quiz_attempt' ) );
		add_action( 'wp_ajax_nopriv_epkb_submit_quiz_attempt', array( $this, 'submit_quiz_attempt' ) );
	}

	/**
	 * Save or update quiz.
	 */
	public function save_quiz() {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_quizzes_write' );

		$quiz_id = absint( EPKB_Utilities::post( 'quiz_id', 0 ) );
		$existing_quiz = $this->get_existing_quiz_or_error( $quiz_id );
		if ( is_wp_error( $existing_quiz ) ) {
			wp_send_json_error( array( 'message' => $existing_quiz->get_error_message() ) );
		}

		$title = sanitize_text_field( EPKB_Utilities::post( 'quiz_title' ) );
		$intro = EPKB_Utilities::post( 'quiz_intro', '', 'wp_editor' );
		$source_article_id = absint( EPKB_Utilities::post( 'source_article_id', 0 ) );
		$post_status = EPKB_Utilities::post( 'post_status' ) === 'publish' ? 'publish' : 'draft';
		$question_count_mode = EPKB_Quizzes_Utilities::sanitize_question_count_mode( EPKB_Utilities::post( 'question_count_mode', 'auto' ) );
		$questions = $this->decode_questions_from_request();

		if ( is_wp_error( $questions ) ) {
			wp_send_json_error( array( 'message' => $questions->get_error_message() ) );
		}

		if ( $title === '' ) {
			wp_send_json_error( array( 'message' => __( 'Quiz title is required.', 'echo-knowledge-base' ) ) );
		}

		if ( $post_status === 'publish' && empty( $questions ) ) {
			wp_send_json_error( array( 'message' => __( 'Add at least one question before publishing.', 'echo-knowledge-base' ) ) );
		}

		if ( count( $questions ) > 10 ) {
			wp_send_json_error( array( 'message' => __( 'A quiz can have at most 10 questions in this MVP.', 'echo-knowledge-base' ) ) );
		}

		$result = $this->with_source_article_lock( $source_article_id, function() use ( $existing_quiz, $intro, $post_status, $question_count_mode, $questions, $source_article_id, $title ) {
			$source_validation = $this->validate_source_assignment( $source_article_id, $existing_quiz );
			if ( is_wp_error( $source_validation ) ) {
				return $source_validation;
			}

			$is_new_quiz = empty( $existing_quiz );
			$generation_meta = empty( $existing_quiz ) ? array() : get_post_meta( $existing_quiz->ID, EPKB_Quizzes_Utilities::META_GENERATION_META, true );
			$generation_meta = EPKB_Quizzes_Utilities::sanitize_generation_meta( is_array( $generation_meta ) ? $generation_meta : array() );
			$generation_meta['question_count_mode'] = $question_count_mode;

			$saved_quiz_id = wp_insert_post( array(
				'ID'             => empty( $existing_quiz ) ? 0 : $existing_quiz->ID,
				'post_type'      => EPKB_Quizzes_CPT_Setup::QUIZ_POST_TYPE,
				'post_title'     => $title,
				'post_content'   => $intro,
				'post_status'    => $post_status,
				'comment_status' => 'closed',
			), true );

			if ( empty( $saved_quiz_id ) || is_wp_error( $saved_quiz_id ) ) {
				return new WP_Error( 'quiz_save_failed', EPKB_Utilities::report_generic_error( 901, $saved_quiz_id ) );
			}

			update_post_meta( $saved_quiz_id, EPKB_Quizzes_Utilities::META_SOURCE_ARTICLE_ID, $source_article_id );
			update_post_meta( $saved_quiz_id, EPKB_Quizzes_Utilities::META_QUESTIONS, $questions );
			update_post_meta( $saved_quiz_id, EPKB_Quizzes_Utilities::META_GENERATION_META, $generation_meta );

			$saved_quiz = EPKB_Quizzes_Utilities::get_quiz( $saved_quiz_id );

			return array(
				'message'             => $post_status === 'publish' ? __( 'Quiz published.', 'echo-knowledge-base' ) : __( 'Quiz saved as draft.', 'echo-knowledge-base' ),
				'quiz'                => EPKB_Quizzes_Utilities::get_quiz_payload( $saved_quiz ),
				'quiz_row_html'       => EPKB_Quizzes_Page::display_quiz_list_row( $saved_quiz, true ),
				'is_new_quiz'         => $is_new_quiz,
			);
		} );

		if ( is_wp_error( $result ) ) {
			$this->send_duplicate_or_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get quiz by ID.
	 */
	public function get_quiz() {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_quizzes_write' );

		$quiz = $this->get_existing_quiz_or_error( absint( EPKB_Utilities::post( 'quiz_id', 0 ) ) );
		if ( is_wp_error( $quiz ) ) {
			wp_send_json_error( array( 'message' => $quiz->get_error_message() ) );
		}

		wp_send_json_success( array(
			'quiz' => EPKB_Quizzes_Utilities::get_quiz_payload( $quiz ),
		) );
	}

	/**
	 * Get quiz by selected article.
	 */
	public function get_quiz_by_article() {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_quizzes_write' );

		$article_id = absint( EPKB_Utilities::post( 'source_article_id', 0 ) );
		if ( empty( $article_id ) ) {
			wp_send_json_success( array( 'quiz' => null ) );
		}

		$quiz = EPKB_Quizzes_Utilities::get_quiz_by_source_article( $article_id );
		wp_send_json_success( array(
			'quiz' => empty( $quiz ) ? null : EPKB_Quizzes_Utilities::get_quiz_payload( $quiz ),
		) );
	}

	/**
	 * Delete quiz.
	 */
	public function delete_quiz() {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_quizzes_write' );

		$quiz = $this->get_existing_quiz_or_error( absint( EPKB_Utilities::post( 'quiz_id', 0 ) ) );
		if ( is_wp_error( $quiz ) ) {
			wp_send_json_error( array( 'message' => $quiz->get_error_message() ) );
		}

		$result = wp_delete_post( $quiz->ID, true );
		if ( empty( $result ) || is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => EPKB_Utilities::report_generic_error( 902, $result ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Quiz deleted.', 'echo-knowledge-base' ),
			'quiz_id' => (int) $quiz->ID,
		) );
	}

	/**
	 * Generate quiz from source article and save draft.
	 */
	public function generate_quiz() {

		EPKB_Utilities::ajax_verify_nonce_and_admin_permission_or_error_die( 'admin_eckb_access_quizzes_write' );

		if ( ! EPKB_Admin_UI_Access::is_user_access_to_context_allowed( 'admin_eckb_access_quizzes_write' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to use AI quiz generation.', 'echo-knowledge-base' ) ) );
		}

		if ( ! EPKB_Utilities::is_ai_features_pro_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'AI quiz generation requires AI Features Pro.', 'echo-knowledge-base' ) ) );
		}

		if ( ! EPKB_AI_Utilities::is_ai_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Configure AI before generating quizzes.', 'echo-knowledge-base' ) ) );
		}

		$quiz_id = absint( EPKB_Utilities::post( 'quiz_id', 0 ) );
		$existing_quiz = $this->get_existing_quiz_or_error( $quiz_id );
		if ( is_wp_error( $existing_quiz ) ) {
			wp_send_json_error( array( 'message' => $existing_quiz->get_error_message() ) );
		}

		$title = sanitize_text_field( EPKB_Utilities::post( 'quiz_title' ) );
		$intro = EPKB_Utilities::post( 'quiz_intro', '', 'wp_editor' );
		$source_article_id = absint( EPKB_Utilities::post( 'source_article_id', 0 ) );
		$question_count_mode = EPKB_Quizzes_Utilities::sanitize_question_count_mode( EPKB_Utilities::post( 'question_count_mode', 'auto' ) );

		$result = $this->with_source_article_lock( $source_article_id, function() use ( $existing_quiz, $intro, $question_count_mode, $source_article_id, $title ) {
			$source_validation = $this->validate_source_assignment( $source_article_id, $existing_quiz, true );
			if ( is_wp_error( $source_validation ) ) {
				return $source_validation;
			}

			$article = get_post( $source_article_id );
			$generated_quiz = EPKB_Quizzes_Utilities::generate_quiz_from_article( $article, $question_count_mode );
			if ( is_wp_error( $generated_quiz ) ) {
				return $generated_quiz;
			}

			$is_new_quiz = empty( $existing_quiz );
			$stored_title = $title === '' ? $generated_quiz['title'] : $title;
			$stored_intro = wp_strip_all_tags( $intro ) === '' ? wpautop( $generated_quiz['intro'] ) : $intro;

			$saved_quiz_id = wp_insert_post( array(
				'ID'             => empty( $existing_quiz ) ? 0 : $existing_quiz->ID,
				'post_type'      => EPKB_Quizzes_CPT_Setup::QUIZ_POST_TYPE,
				'post_title'     => $stored_title,
				'post_content'   => $stored_intro,
				'post_status'    => 'draft',
				'comment_status' => 'closed',
			), true );

			if ( empty( $saved_quiz_id ) || is_wp_error( $saved_quiz_id ) ) {
				return new WP_Error( 'quiz_generate_save_failed', EPKB_Utilities::report_generic_error( 903, $saved_quiz_id ) );
			}

			update_post_meta( $saved_quiz_id, EPKB_Quizzes_Utilities::META_SOURCE_ARTICLE_ID, $source_article_id );
			update_post_meta( $saved_quiz_id, EPKB_Quizzes_Utilities::META_QUESTIONS, $generated_quiz['questions'] );
			update_post_meta( $saved_quiz_id, EPKB_Quizzes_Utilities::META_GENERATION_META, EPKB_Quizzes_Utilities::sanitize_generation_meta( $generated_quiz['generation_meta'] ) );

			$saved_quiz = EPKB_Quizzes_Utilities::get_quiz( $saved_quiz_id );

			return array(
				'message'             => __( 'Quiz generated and saved as draft.', 'echo-knowledge-base' ),
				'quiz'                => EPKB_Quizzes_Utilities::get_quiz_payload( $saved_quiz ),
				'quiz_row_html'       => EPKB_Quizzes_Page::display_quiz_list_row( $saved_quiz, true ),
				'is_new_quiz'         => $is_new_quiz,
			);
		} );

		if ( is_wp_error( $result ) ) {
			$this->send_duplicate_or_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Send quiz completion notification.
	 */
	public function submit_quiz_attempt() {

		$nonce_validation = $this->verify_public_quiz_nonce();
		if ( is_wp_error( $nonce_validation ) ) {
			wp_send_json_error( array( 'message' => $nonce_validation->get_error_message() ), 403 );
		}

		if ( ! EPKB_Quizzes_Utilities::is_feature_enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Quizzes are disabled.', 'echo-knowledge-base' ) ), 400 );
		}

		$kb_config = EPKB_Quizzes_Utilities::get_shared_config();
		$to_email = empty( $kb_config['quizzes_notification_email'] ) ? '' : sanitize_email( $kb_config['quizzes_notification_email'] );
		if ( empty( $to_email ) ) {
			wp_send_json_success( array( 'email_sent' => false ) );
		}

		if ( ! is_email( $to_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Quiz notification email is invalid.', 'echo-knowledge-base' ) ), 400 );
		}

		$quiz = EPKB_Quizzes_Utilities::get_quiz( absint( EPKB_Utilities::post( 'quiz_id', 0 ) ) );
		if ( empty( $quiz ) || $quiz->post_status !== 'publish' ) {
			wp_send_json_error( array( 'message' => __( 'Quiz not found.', 'echo-knowledge-base' ) ), 404 );
		}

		$quiz_payload = EPKB_Quizzes_Utilities::get_quiz_payload( $quiz );
		$source_state = EPKB_Quizzes_Utilities::get_source_article_state( $quiz_payload['source_article_id'] );
		if ( empty( $quiz_payload['questions'] ) || ! $source_state['is_renderable'] ) {
			wp_send_json_error( array( 'message' => __( 'Quiz cannot be submitted.', 'echo-knowledge-base' ) ), 400 );
		}

		$answers = $this->decode_quiz_attempt_answers_from_request();
		if ( is_wp_error( $answers ) ) {
			wp_send_json_error( array( 'message' => $answers->get_error_message() ), 400 );
		}

		$attempt_result = $this->get_quiz_attempt_result( $quiz_payload, $answers );
		if ( is_wp_error( $attempt_result ) ) {
			wp_send_json_error( array( 'message' => $attempt_result->get_error_message() ), 400 );
		}

		$subject = sprintf( __( 'Quiz Taken: %s', 'echo-knowledge-base' ), $quiz_payload['title'] );
		$email_error = EPKB_Utilities::send_email( $this->get_quiz_attempt_email_message( $quiz_payload, $attempt_result ), true, $to_email, '', '', $subject );
		if ( ! empty( $email_error ) ) {
			wp_send_json_error( array( 'message' => $email_error ), 500 );
		}

		wp_send_json_success( array( 'email_sent' => true ) );
	}

	/**
	 * Decode questions JSON from request.
	 *
	 * @return array|WP_Error
	 */
	private function decode_questions_from_request() {

		$questions_json = isset( $_POST['questions_json'] ) ? wp_unslash( $_POST['questions_json'] ) : '[]'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$decoded_questions = json_decode( $questions_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_questions_json', __( 'Quiz questions could not be read.', 'echo-knowledge-base' ) );
		}

		$questions = EPKB_Quizzes_Utilities::normalize_questions( $decoded_questions );
		return is_wp_error( $questions ) ? $questions : $questions;
	}

	/**
	 * Verify public quiz AJAX nonce.
	 *
	 * @return true|WP_Error
	 */
	private function verify_public_quiz_nonce() {

		$wp_nonce = EPKB_Utilities::post( '_wpnonce_epkb_ajax_action' );
		if ( empty( $wp_nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $wp_nonce ) ), '_wpnonce_epkb_ajax_action' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Refresh the page and try again.', 'echo-knowledge-base' ) );
		}

		return true;
	}

	/**
	 * Decode completed quiz answers JSON from request.
	 *
	 * @return array|WP_Error
	 */
	private function decode_quiz_attempt_answers_from_request() {

		$answers_json = isset( $_POST['answers_json'] ) ? wp_unslash( $_POST['answers_json'] ) : '[]'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$decoded_answers = json_decode( $answers_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded_answers ) ) {
			return new WP_Error( 'invalid_answers_json', __( 'Quiz answers could not be read.', 'echo-knowledge-base' ) );
		}

		$answers = array();
		foreach ( $decoded_answers as $answer ) {
			if ( ! is_array( $answer ) ) {
				return new WP_Error( 'invalid_answer', __( 'Quiz answers are invalid.', 'echo-knowledge-base' ) );
			}

			$question_id = empty( $answer['question_id'] ) ? '' : sanitize_key( $answer['question_id'] );
			if ( $question_id === '' || ! isset( $answer['selected_choice'] ) || ! is_numeric( $answer['selected_choice'] ) ) {
				return new WP_Error( 'invalid_answer', __( 'Quiz answers are invalid.', 'echo-knowledge-base' ) );
			}

			$answers[ $question_id ] = (int) $answer['selected_choice'];
		}

		return $answers;
	}

	/**
	 * Build trusted quiz attempt result from stored quiz answers.
	 *
	 * @param array $quiz_payload
	 * @param array $answers
	 * @return array|WP_Error
	 */
	private function get_quiz_attempt_result( $quiz_payload, $answers ) {

		$total_count = count( $quiz_payload['questions'] );
		$correct_count = 0;
		$rows = array();

		foreach ( $quiz_payload['questions'] as $question ) {
			if ( ! isset( $answers[ $question['id'] ] ) ) {
				return new WP_Error( 'incomplete_answers', __( 'Answer every quiz question before submitting.', 'echo-knowledge-base' ) );
			}

			$selected_choice = (int) $answers[ $question['id'] ];
			$correct_choice = (int) $question['correct_choice'];
			if ( $selected_choice < 0 || ! isset( $question['choices'][ $selected_choice ] ) || ! isset( $question['choices'][ $correct_choice ] ) ) {
				return new WP_Error( 'invalid_answer_choice', __( 'Quiz answers are invalid.', 'echo-knowledge-base' ) );
			}

			$is_correct = $selected_choice === $correct_choice;
			$correct_count += $is_correct ? 1 : 0;
			$rows[] = array(
				'question'        => $question['question'],
				'selected_answer' => $question['choices'][ $selected_choice ],
				'correct_answer'  => $question['choices'][ $correct_choice ],
				'is_correct'      => $is_correct,
			);
		}

		return array(
			'completed_at'  => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'user_label'    => $this->get_quiz_attempt_user_label(),
			'correct_count' => $correct_count,
			'total_count'   => $total_count,
			'percent'       => $total_count === 0 ? 0 : round( ( $correct_count / $total_count ) * 100 ),
			'rows'          => $rows,
		);
	}

	/**
	 * Get current quiz taker label.
	 *
	 * @return string
	 */
	private function get_quiz_attempt_user_label() {

		$user = wp_get_current_user();
		if ( $user instanceof WP_User && $user->exists() ) {
			$user_name = empty( $user->display_name ) ? $user->user_login : $user->display_name;
			return empty( $user->user_email ) ? $user_name : $user_name . ' <' . $user->user_email . '>';
		}

		return __( 'Anonymous visitor', 'echo-knowledge-base' );
	}

	/**
	 * Build quiz attempt notification email.
	 *
	 * @param array $quiz_payload
	 * @param array $attempt_result
	 * @return string
	 */
	private function get_quiz_attempt_email_message( $quiz_payload, $attempt_result ) {

		$article_link = empty( $quiz_payload['source_article_url'] )
			? esc_html( $quiz_payload['source_article_label'] )
			: '<a href="' . esc_url( $quiz_payload['source_article_url'] ) . '">' . esc_html( $quiz_payload['source_article_label'] ) . '</a>';

		$rows_html = '';
		foreach ( $attempt_result['rows'] as $row ) {
			$rows_html .= '<tr>' .
				'<td style="padding:8px;border:1px solid #ddd;">' . esc_html( $row['question'] ) . '</td>' .
				'<td style="padding:8px;border:1px solid #ddd;">' . esc_html( $row['selected_answer'] ) . '</td>' .
				'<td style="padding:8px;border:1px solid #ddd;">' . esc_html( $row['is_correct'] ? __( 'Correct', 'echo-knowledge-base' ) : __( 'Incorrect', 'echo-knowledge-base' ) ) . '</td>' .
				'<td style="padding:8px;border:1px solid #ddd;">' . esc_html( $row['correct_answer'] ) . '</td>' .
			'</tr>';
		}

		return '<html><body style="font-family:Arial,sans-serif;color:#222;">' .
			'<h2>' . esc_html__( 'Quiz Taken', 'echo-knowledge-base' ) . '</h2>' .
			'<p><strong>' . esc_html__( 'Article:', 'echo-knowledge-base' ) . '</strong> ' . $article_link . '</p>' .
			'<p><strong>' . esc_html__( 'Quiz:', 'echo-knowledge-base' ) . '</strong> ' . esc_html( $quiz_payload['title'] ) . '</p>' .
			'<p><strong>' . esc_html__( 'User:', 'echo-knowledge-base' ) . '</strong> ' . esc_html( $attempt_result['user_label'] ) . '</p>' .
			'<p><strong>' . esc_html__( 'When Taken:', 'echo-knowledge-base' ) . '</strong> ' . esc_html( $attempt_result['completed_at'] ) . '</p>' .
			'<p><strong>' . esc_html__( 'Result:', 'echo-knowledge-base' ) . '</strong> ' . esc_html( $attempt_result['correct_count'] . '/' . $attempt_result['total_count'] . ' (' . $attempt_result['percent'] . '%)' ) . '</p>' .
			'<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;max-width:900px;">' .
				'<thead><tr>' .
					'<th style="padding:8px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Question', 'echo-knowledge-base' ) . '</th>' .
					'<th style="padding:8px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Selected Answer', 'echo-knowledge-base' ) . '</th>' .
					'<th style="padding:8px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Result', 'echo-knowledge-base' ) . '</th>' .
					'<th style="padding:8px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Correct Answer', 'echo-knowledge-base' ) . '</th>' .
				'</tr></thead>' .
				'<tbody>' . $rows_html . '</tbody>' .
			'</table>' .
			'<p><strong>' . esc_html__( 'Site:', 'echo-knowledge-base' ) . '</strong> <a href="' . esc_url( home_url() ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a></p>' .
		'</body></html>';
	}

	/**
	 * Validate source article for save or generation.
	 *
	 * @param int $source_article_id
	 * @param WP_Post|null $existing_quiz
	 * @param bool $require_renderable_source
	 * @return true|WP_Error
	 */
	private function validate_source_assignment( $source_article_id, $existing_quiz = null, $require_renderable_source = false ) {

		if ( empty( $source_article_id ) ) {
			return new WP_Error( 'source_required', __( 'Select a source article.', 'echo-knowledge-base' ) );
		}

		$current_source_article_id = empty( $existing_quiz ) ? 0 : absint( get_post_meta( $existing_quiz->ID, EPKB_Quizzes_Utilities::META_SOURCE_ARTICLE_ID, true ) );
		$is_same_source = ! empty( $existing_quiz ) && $current_source_article_id === $source_article_id;
		$source_state = EPKB_Quizzes_Utilities::get_source_article_state( $source_article_id );

		if ( ( ! $is_same_source || $require_renderable_source ) && ! $source_state['is_assignable'] ) {
			return new WP_Error( 'invalid_source', empty( $source_state['warning_message'] ) ? __( 'The selected source article is not eligible for quizzes.', 'echo-knowledge-base' ) : $source_state['warning_message'] );
		}

		if ( $require_renderable_source && ! $source_state['is_renderable'] ) {
			return new WP_Error( 'invalid_render_source', empty( $source_state['warning_message'] ) ? __( 'The selected source article cannot be used to generate a quiz.', 'echo-knowledge-base' ) : $source_state['warning_message'] );
		}

		$duplicate_quiz = EPKB_Quizzes_Utilities::get_quiz_by_source_article( $source_article_id );
		if ( ! empty( $duplicate_quiz ) && ( empty( $existing_quiz ) || (int) $duplicate_quiz->ID !== (int) $existing_quiz->ID ) ) {
			return new WP_Error( 'quiz_exists', __( 'A quiz already exists for this article.', 'echo-knowledge-base' ), array(
				'quiz' => EPKB_Quizzes_Utilities::get_quiz_payload( $duplicate_quiz ),
			) );
		}

		return true;
	}

	/**
	 * Return existing quiz or validation error.
	 *
	 * @param int $quiz_id
	 * @return WP_Post|null|WP_Error
	 */
	private function get_existing_quiz_or_error( $quiz_id ) {

		if ( empty( $quiz_id ) ) {
			return null;
		}

		$quiz = EPKB_Quizzes_Utilities::get_quiz( $quiz_id );
		if ( empty( $quiz ) ) {
			return new WP_Error( 'quiz_not_found', __( 'Quiz not found.', 'echo-knowledge-base' ) );
		}

		return $quiz;
	}

	/**
	 * Run a callback while holding a per-source quiz lock.
	 *
	 * @param int $source_article_id
	 * @param callable $callback
	 * @return mixed
	 */
	private function with_source_article_lock( $source_article_id, $callback ) {

		$lock_name = $this->acquire_source_article_lock( $source_article_id );
		if ( is_wp_error( $lock_name ) ) {
			return $lock_name;
		}

		try {
			return call_user_func( $callback );
		} finally {
			$this->release_source_article_lock( $lock_name );
		}
	}

	/**
	 * Acquire a short-lived write lock for a source article.
	 *
	 * @param int $source_article_id
	 * @return string|WP_Error
	 */
	private function acquire_source_article_lock( $source_article_id ) {

		$source_article_id = absint( $source_article_id );
		if ( empty( $source_article_id ) ) {
			return new WP_Error( 'source_required', __( 'Select a source article.', 'echo-knowledge-base' ) );
		}

		$lock_name = self::SOURCE_LOCK_PREFIX . $source_article_id;
		$lock_time = time();

		if ( add_option( $lock_name, $lock_time, '', false ) ) {
			return $lock_name;
		}

		$existing_lock_time = absint( get_option( $lock_name, 0 ) );
		if ( ! empty( $existing_lock_time ) && ( $lock_time - $existing_lock_time ) > self::SOURCE_LOCK_TIMEOUT ) {
			delete_option( $lock_name );
			if ( add_option( $lock_name, $lock_time, '', false ) ) {
				return $lock_name;
			}
		}

		return new WP_Error( 'quiz_source_locked', __( 'Another quiz update is already in progress for that article. Please try again in a moment.', 'echo-knowledge-base' ) );
	}

	/**
	 * Release a source-article lock.
	 *
	 * @param string $lock_name
	 */
	private function release_source_article_lock( $lock_name ) {

		if ( ! empty( $lock_name ) ) {
			delete_option( $lock_name );
		}
	}

	/**
	 * Send duplicate-quiz or regular validation error.
	 *
	 * @param WP_Error $error
	 */
	private function send_duplicate_or_error( $error ) {

		if ( $error->get_error_code() === 'quiz_exists' ) {
			$error_data = $error->get_error_data();
			wp_send_json_error( array(
				'message' => __( 'That article already has a quiz. Opening the existing quiz instead.', 'echo-knowledge-base' ),
				'code'    => 'quiz_exists',
				'quiz'    => empty( $error_data['quiz'] ) ? null : $error_data['quiz'],
			) );
		}

		wp_send_json_error( array( 'message' => $error->get_error_message() ) );
	}
}
