<?php
/**
 * Elementor integration: registers a "Wonom Slider" widget.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Elementor {

	public static function init() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		// Make sure assets exist inside the Elementor editor / preview iframe.
		add_action( 'elementor/preview/enqueue_styles', array( 'Wonom_Slider_Frontend', 'enqueue_assets' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( 'Wonom_Slider_Frontend', 'register_assets' ) );
	}

	public static function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'wonom',
			array(
				'title' => 'Wonom',
				'icon'  => 'fa fa-plug',
			)
		);
	}

	public static function register_widget( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-elementor-widget.php';
		$widgets_manager->register( new Wonom_Slider_Elementor_Widget() );
	}
}
