<?php defined( 'ABSPATH' ) || exit();

/**
 * AI Widget Configuration Specifications
 * 
 * Defines AI widget-related configuration settings. This demonstrates how
 * the base configuration class can be extended for specific feature sets.
 *
 * @copyright   Copyright (C) 2018, Echo Plugins
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */
class EPKB_AI_Widget_Config_Specs extends EPKB_AI_Config_Base {

	const OPTION_NAME = 'epkb_ai_widget_configuration';

	/**
	 * Get all AI widget configuration specifications
	 *
	 * @return array
	 */
	public static function get_config_fields_specifications() {

		$widget_specs = array(

			/***  Widget Display Settings ***/
			'widget_enabled' => array(
				'name'        => 'widget_enabled',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),
			'widget_position' => array(
				'name'        => 'widget_position',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'bottom-right' => 'Bottom Right',
					'bottom-left'  => 'Bottom Left',
					'top-right'    => 'Top Right',
					'top-left'     => 'Top Left'
				),
				'default'     => 'bottom-right'
			),
			'widget_icon' => array(
				'name'        => 'widget_icon',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'chat'      => 'Chat Bubble',
					'question'  => 'Question Mark',
					'ai'        => 'AI Icon',
					'custom'    => 'Custom Icon'
				),
				'default'     => 'chat'
			),
			'widget_custom_icon_url' => array(
				'name'        => 'widget_custom_icon_url',
				'type'        => EPKB_Input_Filter::URL,
				'default'     => ''
			),

			/***  Widget Behavior Settings ***/
			'widget_auto_open' => array(
				'name'        => 'widget_auto_open',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),
			'widget_auto_open_delay' => array(
				'name'        => 'widget_auto_open_delay',
				'type'        => EPKB_Input_Filter::NUMBER,
				'default'     => 5,
				'min'         => 0,
				'max'         => 60
			),
			'widget_show_on_pages' => array(
				'name'        => 'widget_show_on_pages',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'all'        => 'All Pages',
					'kb_only'    => 'KB Pages Only',
					'selected'   => 'Selected Pages'
				),
				'default'     => 'kb_only'
			),
			'widget_selected_pages' => array(
				'name'        => 'widget_selected_pages',
				'type'        => EPKB_Input_Filter::CHECKBOXES_MULTI_SELECT,
				'default'     => array(),
				'options'     => array() // Will be populated dynamically
			),

			/***  Widget Style Settings ***/
			'widget_primary_color' => array(
				'name'        => 'widget_primary_color',
				'type'        => EPKB_Input_Filter::COLOR_HEX,
				'default'     => '#0073aa'
			),
			'widget_secondary_color' => array(
				'name'        => 'widget_secondary_color',
				'type'        => EPKB_Input_Filter::COLOR_HEX,
				'default'     => '#23282d'
			),
			'widget_text_color' => array(
				'name'        => 'widget_text_color',
				'type'        => EPKB_Input_Filter::COLOR_HEX,
				'default'     => '#ffffff'
			),
			'widget_size' => array(
				'name'        => 'widget_size',
				'type'        => EPKB_Input_Filter::SELECTION,
				'options'     => array(
					'small'   => 'Small (50px)',
					'medium'  => 'Medium (60px)',
					'large'   => 'Large (70px)'
				),
				'default'     => 'medium'
			),

			/***  Widget Messages ***/
			'widget_welcome_message' => array(
				'name'        => 'widget_welcome_message',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => 'Hi! How can I help you today?',
				'min'         => 0,
				'max'         => 200
			),
			'widget_placeholder_text' => array(
				'name'        => 'widget_placeholder_text',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => 'Type your question here...',
				'min'         => 0,
				'max'         => 100
			),
			'widget_offline_message' => array(
				'name'        => 'widget_offline_message',
				'type'        => EPKB_Input_Filter::TEXT,
				'default'     => 'AI assistance is currently unavailable. Please try again later.',
				'min'         => 0,
				'max'         => 200
			),

			/***  Widget Restrictions ***/
			'widget_require_login' => array(
				'name'        => 'widget_require_login',
				'type'        => EPKB_Input_Filter::CHECKBOX,
				'default'     => 'off'
			),
			'widget_max_queries_per_session' => array(
				'name'        => 'widget_max_queries_per_session',
				'type'        => EPKB_Input_Filter::NUMBER,
				'default'     => 10,
				'min'         => 0,
				'max'         => 100
			),
			'widget_rate_limit_minutes' => array(
				'name'        => 'widget_rate_limit_minutes',
				'type'        => EPKB_Input_Filter::NUMBER,
				'default'     => 60,
				'min'         => 0,
				'max'         => 1440
			)
		);

		return $widget_specs;
	}

	/**
	 * Get field options dynamically
	 * Overrides parent method to provide widget-specific options
	 *
	 * @param string $field_name
	 * @return array
	 */
	public static function get_field_options( $field_name ) {
		switch ( $field_name ) {
			case 'widget_selected_pages':
				// Return list of all pages
				$pages = get_pages();
				$options = array();
				foreach ( $pages as $page ) {
					$options[ $page->ID ] = $page->post_title;
				}
				return $options;
			default:
				return parent::get_field_options( $field_name );
		}
	}
}