<?php
/**
 * Plugin bootstrap.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

final class Wonom_Slider {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'boot' ), 5 );
		register_activation_hook( WONOM_SLIDER_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( WONOM_SLIDER_FILE, array( __CLASS__, 'deactivate' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'wonom-slider', false, dirname( WONOM_SLIDER_BASENAME ) . '/languages' );
	}

	public function boot() {
		Wonom_Slider_Frontend::init();
		Wonom_Slider_Rest::init();
		Wonom_Slider_Updater::init();
		Wonom_Slider_Cache::init();

		if ( is_admin() ) {
			Wonom_Slider_Admin::init();
		}

		if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
			require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-elementor.php';
			Wonom_Slider_Elementor::init();
		} else {
			add_action(
				'elementor/loaded',
				function () {
					require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-elementor.php';
					Wonom_Slider_Elementor::init();
				}
			);
		}
	}

	public static function activate() {
		if ( false === get_option( Wonom_Slider_Data::OPTION_SETTINGS, false ) ) {
			add_option( Wonom_Slider_Data::OPTION_SETTINGS, Wonom_Slider_Data::default_settings(), '', false );
		}
		if ( false === get_option( Wonom_Slider_Data::OPTION_SLIDES, false ) ) {
			add_option( Wonom_Slider_Data::OPTION_SLIDES, array(), '', false );
		}
		set_transient( 'wonom_slider_activated', 1, 60 );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( Wonom_Slider_Cache::CRON_HOOK );
	}
}
