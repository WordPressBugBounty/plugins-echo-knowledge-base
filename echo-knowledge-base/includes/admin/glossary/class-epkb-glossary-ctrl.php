<?php if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX controller for Glossary admin page
 */
class EPKB_Glossary_Ctrl {

	public function __construct() {
		add_action( 'wp_ajax_epkb_glossary_save_term', array( $this, 'save_term' ) );
		add_action( 'wp_ajax_nopriv_epkb_glossary_save_term', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

		add_action( 'wp_ajax_epkb_glossary_delete_term', array( $this, 'delete_term' ) );
		add_action( 'wp_ajax_nopriv_epkb_glossary_delete_term', array( 'EPKB_Utilities', 'user_not_logged_in' ) );

		add_action( 'wp_ajax_epkb_glossary_get_term', array( $this, 'get_term' ) );
		add_action( 'wp_ajax_nopriv_epkb_glossary_get_term', array( 'EPKB_Utilities', 'user_not_logged_in' ) );
	}

	/**
	 * Save (create or update) a glossary term
	 */
	public function save_term() {

		EPKB_Utilities::ajax_verify_nonce_and_capability_or_error_die( EPKB_Admin_UI_Access::EPKB_WP_EDITOR_CAPABILITY );

		$term_id    = (int) EPKB_Utilities::post( 'term_id', 0 );
		$term_name  = sanitize_text_field( EPKB_Utilities::post( 'term_name' ) );
		$definition = sanitize_textarea_field( EPKB_Utilities::post( 'definition' ) );
		$status     = EPKB_Utilities::post( 'status' ) === 'draft' ? 'draft' : 'publish';

		if ( empty( $term_name ) ) {
			EPKB_Utilities::ajax_show_error_die( esc_html__( 'Term name is required.', 'echo-knowledge-base' ) );
		}

		// Enforce max lengths
		$term_name  = mb_substr( $term_name, 0, 100 );
		$definition = mb_substr( $definition, 0, 500 );

		// Create new term
		if ( empty( $term_id ) ) {

			// Check uniqueness
			$existing = term_exists( $term_name, EPKB_Glossary_Taxonomy_Setup::GLOSSARY_TAXONOMY );
			if ( $existing ) {
				EPKB_Utilities::ajax_show_error_die( esc_html__( 'A term with this name already exists.', 'echo-knowledge-base' ) );
			}

			$result = wp_insert_term( $term_name, EPKB_Glossary_Taxonomy_Setup::GLOSSARY_TAXONOMY, array( 'description' => $definition ) );
			if ( is_wp_error( $result ) ) {
				EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 760, $result ) );
			}

			$term_id = $result['term_id'];

		// Update existing term
		} else {

			// Check uniqueness for different term
			$existing = term_exists( $term_name, EPKB_Glossary_Taxonomy_Setup::GLOSSARY_TAXONOMY );
			if ( $existing && (int) $existing['term_id'] !== $term_id ) {
				EPKB_Utilities::ajax_show_error_die( esc_html__( 'A term with this name already exists.', 'echo-knowledge-base' ) );
			}

			$result = wp_update_term( $term_id, EPKB_Glossary_Taxonomy_Setup::GLOSSARY_TAXONOMY, array(
				'name'        => $term_name,
				'description' => $definition,
			) );
			if ( is_wp_error( $result ) ) {
				EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 761, $result ) );
			}
		}

		update_term_meta( $term_id, 'epkb_glossary_status', $status );

		wp_die( wp_json_encode( array(
			'status'  => 'success',
			'message' => esc_html__( 'Term Saved', 'echo-knowledge-base' ),
			'data'    => array(
				'term_id'    => esc_attr( $term_id ),
				'name'       => esc_html( $term_name ),
				'definition' => esc_html( $definition ),
				'status'     => esc_attr( $status ),
			),
		) ) );
	}

	/**
	 * Delete a glossary term
	 */
	public function delete_term() {

		EPKB_Utilities::ajax_verify_nonce_and_capability_or_error_die( EPKB_Admin_UI_Access::EPKB_WP_EDITOR_CAPABILITY );

		$term_id = (int) EPKB_Utilities::post( 'term_id', 0 );
		if ( empty( $term_id ) ) {
			EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 762 ) );
		}

		$result = wp_delete_term( $term_id, EPKB_Glossary_Taxonomy_Setup::GLOSSARY_TAXONOMY );
		if ( empty( $result ) || is_wp_error( $result ) ) {
			EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 763, $result ) );
		}

		wp_die( wp_json_encode( array(
			'status'  => 'success',
			'message' => esc_html__( 'Term Deleted', 'echo-knowledge-base' ),
		) ) );
	}

	/**
	 * Get a single glossary term for editing
	 */
	public function get_term() {

		EPKB_Utilities::ajax_verify_nonce_and_capability_or_error_die( EPKB_Admin_UI_Access::EPKB_WP_EDITOR_CAPABILITY );

		$term_id = (int) EPKB_Utilities::post( 'term_id', 0 );
		if ( empty( $term_id ) ) {
			EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 764 ) );
		}

		$term = get_term( $term_id, EPKB_Glossary_Taxonomy_Setup::GLOSSARY_TAXONOMY );
		if ( empty( $term ) || is_wp_error( $term ) ) {
			EPKB_Utilities::ajax_show_error_die( EPKB_Utilities::report_generic_error( 765 ) );
		}

		$status = get_term_meta( $term_id, 'epkb_glossary_status', true );
		if ( empty( $status ) ) {
			$status = 'publish';
		}

		wp_die( wp_json_encode( array(
			'status'  => 'success',
			'message' => '',
			'data'    => array(
				'term_id'    => esc_attr( $term->term_id ),
				'name'       => esc_html( $term->name ),
				'definition' => esc_html( $term->description ),
				'status'     => esc_attr( $status ),
			),
		) ) );
	}
}
