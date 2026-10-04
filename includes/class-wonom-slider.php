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
		add_action( 'wonom_slider_saved', array( __CLASS__, 'flush_caches' ) );
		add_action( 'wonom_slider_saved', array( __CLASS__, 'schedule_next_change' ) );
		add_action( 'wonom_slider_schedule_tick', array( __CLASS__, 'flush_caches' ) );
		add_action( 'wonom_slider_schedule_tick', array( __CLASS__, 'schedule_next_change' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'wonom-slider', false, dirname( WONOM_SLIDER_BASENAME ) . '/languages' );
	}

	public function boot() {
		Wonom_Slider_Frontend::init();
		Wonom_Slider_Rest::init();
		Wonom_Slider_Updater::init();

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

	/**
	 * Best-effort purge of page caches so scheduled campaign slides appear on time.
	 */
	public static function flush_caches() {
		// WP Rocket.
		if ( function_exists( 'rocket_clean_home' ) ) {
			rocket_clean_home();
		}
		// LiteSpeed Cache.
		do_action( 'litespeed_purge_all' );
		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_posts' ) ) {
			w3tc_flush_posts();
		}
		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		// WP Fastest Cache.
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache();
		}
		// SG Optimizer.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		// Autoptimize.
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
		}
		// Cloudflare / other plugins may hook here.
		do_action( 'wonom_slider_flush_caches' );
	}

	/**
	 * Schedule a one-off cron event at the next start/end moment so caches get purged automatically.
	 */
	public static function schedule_next_change() {
		wp_clear_scheduled_hook( 'wonom_slider_schedule_tick' );
		$next_local = Wonom_Slider_Data::next_change_timestamp();
		if ( ! $next_local ) {
			return;
		}
		// Convert site-local timestamp to UTC for wp_schedule_single_event.
		$offset = (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		$tz     = wp_timezone();
		try {
			$dt     = new DateTime( '@' . $next_local );
			$local  = new DateTime( $dt->format( 'Y-m-d H:i:s' ), $tz );
			$utc_ts = $local->getTimestamp();
		} catch ( Exception $e ) {
			$utc_ts = $next_local - $offset;
		}
		wp_schedule_single_event( $utc_ts + 30, 'wonom_slider_schedule_tick' );
	}
}
