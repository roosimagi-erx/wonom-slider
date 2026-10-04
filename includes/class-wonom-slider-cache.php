<?php
/**
 * Page-cache purging (same approach as Wonom Kampaaniariba).
 *
 * The slider is rendered by PHP straight into the page, so a cached page keeps the
 * slides as they were when it was cached. The cache is therefore purged when slides
 * or settings are saved, at every scheduled start/end moment, and after a plugin update.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Cache {

	const CRON_HOOK = 'wonom_slider_schedule_tick';

	private static $purged = false;
	private static $errors = array();

	public static function init() {
		add_action( 'wonom_slider_saved', array( __CLASS__, 'auto_purge' ) );
		add_action( 'wonom_slider_saved', array( __CLASS__, 'schedule_next_change' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'schedule_next_change' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 20, 2 );
	}

	public static function enabled() {
		$adv = Wonom_Slider_Data::get_advanced();
		return ! empty( $adv['auto_purge'] );
	}

	public static function auto_purge() {
		if ( self::enabled() ) {
			self::purge();
		}
	}

	public static function last_errors() {
		return self::$errors;
	}

	/**
	 * Purge every known page cache plus Cloudflare. Each call is guarded, so a missing
	 * plugin does nothing. Runs at most once per request unless forced.
	 *
	 * @param bool $force Purge even if already purged in this request.
	 * @return string[] Names of the caches that were purged.
	 */
	public static function purge( $force = false ) {
		if ( self::$purged && ! $force ) {
			return array();
		}
		self::$purged = true;
		self::$errors = array();
		$done         = array();

		// FlyingPress – purge_pages keeps fonts and images.
		if ( class_exists( '\FlyingPress\Purge' ) ) {
			if ( method_exists( '\FlyingPress\Purge', 'purge_pages' ) ) {
				\FlyingPress\Purge::purge_pages();
				$done[] = 'FlyingPress';
			} elseif ( method_exists( '\FlyingPress\Purge', 'purge_everything' ) ) {
				\FlyingPress\Purge::purge_everything();
				$done[] = 'FlyingPress';
			}
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$done[] = 'WP Rocket';
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$done[] = 'LiteSpeed Cache';
		}
		if ( function_exists( 'w3tc_flush_posts' ) ) {
			w3tc_flush_posts();
			$done[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$done[] = 'WP Super Cache';
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
			$done[] = 'WP Fastest Cache';
		}
		if ( has_action( 'cache_enabler_clear_complete_cache' ) ) {
			do_action( 'cache_enabler_clear_complete_cache' );
			$done[] = 'Cache Enabler';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
			$done[] = 'SiteGround Optimizer';
		}
		if ( has_action( 'breeze_clear_all_cache' ) ) {
			do_action( 'breeze_clear_all_cache' );
			$done[] = 'Breeze';
		}
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
			$done[] = 'Autoptimize';
		}
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
			$done[] = 'Elementor';
		}

		if ( self::cloudflare() ) {
			$done[] = 'Cloudflare';
		}

		/**
		 * Purge your own cache layer (server, CDN…).
		 *
		 * @param string[] $done Caches purged so far.
		 */
		do_action( 'wonom_slider_flush_caches', $done );

		return $done;
	}

	/**
	 * Cloudflare edge purge (free/Pro plans only allow purging the whole zone).
	 */
	private static function cloudflare() {
		$adv   = Wonom_Slider_Data::get_advanced();
		$zone  = trim( (string) $adv['cf_zone'] );
		$token = trim( (string) $adv['cf_token'] );
		if ( '' === $zone || '' === $token ) {
			return false;
		}
		$res = wp_remote_post(
			'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone ) . '/purge_cache',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'purge_everything' => true ) ),
			)
		);
		if ( is_wp_error( $res ) ) {
			self::$errors[] = 'Cloudflare: ' . $res->get_error_message();
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! empty( $body['success'] ) ) {
			return true;
		}
		self::$errors[] = 'Cloudflare: ' . ( ! empty( $body['errors'][0]['message'] ) ? $body['errors'][0]['message'] : 'unknown error' );
		return false;
	}

	/**
	 * After this plugin was updated the markup/CSS may have changed – purge.
	 */
	public static function on_upgrade( $upgrader, $data ) {
		if ( ! is_array( $data ) || empty( $data['type'] ) || 'plugin' !== $data['type'] ) {
			return;
		}
		$plugins = ! empty( $data['plugins'] ) && is_array( $data['plugins'] ) ? $data['plugins'] : ( ! empty( $data['plugin'] ) ? array( $data['plugin'] ) : array() );
		if ( in_array( WONOM_SLIDER_BASENAME, $plugins, true ) ) {
			self::purge();
		}
	}

	/**
	 * Schedule a one-off purge at the next start/end moment of any slide,
	 * so campaign slides appear and disappear on time even on cached pages.
	 */
	public static function schedule_next_change() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		$next_local = Wonom_Slider_Data::next_change_timestamp();
		if ( ! $next_local ) {
			return;
		}
		try {
			$dt     = new DateTime( '@' . $next_local );
			$local  = new DateTime( $dt->format( 'Y-m-d H:i:s' ), wp_timezone() );
			$utc_ts = $local->getTimestamp();
		} catch ( Exception $e ) {
			$utc_ts = $next_local - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		}
		wp_schedule_single_event( $utc_ts + 5, self::CRON_HOOK );
	}

	/**
	 * Info for the settings screen.
	 */
	public static function status() {
		$next = wp_next_scheduled( self::CRON_HOOK );
		return array(
			'enabled'   => self::enabled(),
			'next_tick' => $next ? wp_date( 'Y-m-d H:i', $next ) : '',
			'wp_cron_off' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'detected'  => self::detected(),
		);
	}

	/**
	 * Which cache layers are present on this site.
	 */
	public static function detected() {
		$list = array();
		if ( class_exists( '\FlyingPress\Purge' ) ) {
			$list[] = 'FlyingPress';
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			$list[] = 'WP Rocket';
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			$list[] = 'LiteSpeed Cache';
		}
		if ( function_exists( 'w3tc_flush_posts' ) ) {
			$list[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			$list[] = 'WP Super Cache';
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			$list[] = 'WP Fastest Cache';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			$list[] = 'SiteGround Optimizer';
		}
		if ( class_exists( 'autoptimizeCache' ) ) {
			$list[] = 'Autoptimize';
		}
		$adv = Wonom_Slider_Data::get_advanced();
		if ( $adv['cf_zone'] && $adv['cf_token'] ) {
			$list[] = 'Cloudflare';
		}
		return $list;
	}
}
