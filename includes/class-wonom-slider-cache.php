<?php
/**
 * Page-cache purging (same approach as Wonom Kampaaniariba).
 *
 * The slider is rendered by PHP straight into the page, so a cached page keeps the
 * slides as they were when it was cached. The cache of the pages that show the slider is
 * therefore purged when slides or settings are saved, at every scheduled start/end moment,
 * and after a plugin update (when automatic purging is on).
 *
 * Server load rules (since 1.9.4, after the 19.11.2026 outage):
 * - only the slider pages are purged (home pages per language, pages where the slider was
 *   found or rendered, URLs added in the settings) – never the whole site, never Elementor CSS;
 * - Cloudflare gets only those URLs, never purge_everything, and nothing from a test host;
 * - saves are debounced: one purge DELAY seconds after the last save (slides + settings = one).
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Cache {

	const CRON_HOOK   = 'wonom_slider_schedule_tick';
	const PULSE_HOOK  = 'wonom_slider_cron_pulse';
	const DEFER_HOOK  = 'wonom_slider_deferred_purge';
	const OPT_LAST    = 'wonom_slider_cron_last';     // last time WP-Cron really ran one of our events
	const OPT_SINCE   = 'wonom_slider_cron_since';    // when the pulse was first scheduled (grace period)
	const OPT_PENDING = 'wonom_slider_purge_pending'; // time of the debounced purge
	const OPT_PAGES   = 'wonom_slider_pages';         // post IDs of pages that show the slider
	const OPT_SCANNED = 'wonom_slider_pages_scanned'; // one-off search for slider pages done
	const DELAY       = 30;                           // seconds between the last save and the purge
	const MAX_URLS    = 30;                           // Cloudflare Free: 30 files per purge request
	const MAX_PAGES   = 40;

	private static $purged = false;
	private static $errors = array();
	private static $last   = array();   // what the last purge did (for the settings screen)

	public static function init() {
		add_action( 'wonom_slider_saved', array( __CLASS__, 'auto_purge' ) );
		add_action( 'wonom_slider_saved', array( __CLASS__, 'schedule_next_change' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'schedule_next_change' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'record_pulse' ) );
		add_action( self::PULSE_HOOK, array( __CLASS__, 'record_pulse' ) );
		add_action( self::DEFER_HOOK, array( __CLASS__, 'run_deferred' ) );
		add_action( self::DEFER_HOOK, array( __CLASS__, 'record_pulse' ) );
		add_action( 'init', array( __CLASS__, 'ensure_pulse' ) );
		add_action( 'admin_init', array( __CLASS__, 'catch_up' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 20, 2 );
		// Remember which pages contain the slider (Elementor widget, block or shortcode).
		add_action( 'save_post', array( __CLASS__, 'scan_post' ), 20 );
		add_action( 'updated_post_meta', array( __CLASS__, 'scan_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'scan_meta' ), 10, 3 );
	}

	/* ---------- WP-Cron health ----------
	 * The scheduled purge only happens if WP-Cron runs. On a cached shop WP-Cron is often switched
	 * off and driven by the server, which can stop unnoticed. An hourly pulse records when cron
	 * really ran, so the settings screen can tell – checking DISABLE_WP_CRON alone says nothing. */

	public static function ensure_pulse() {
		if ( ! wp_next_scheduled( self::PULSE_HOOK ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::PULSE_HOOK );
			if ( ! get_option( self::OPT_SINCE ) ) {
				update_option( self::OPT_SINCE, time(), false );
			}
		}
	}

	public static function record_pulse() {
		update_option( self::OPT_LAST, time(), false );
	}

	/**
	 * Safety net: when a scheduled start/end purge is overdue (cron did not run), do it on the next
	 * admin page load instead of waiting – the shop owner visiting the admin fixes the cached page.
	 */
	public static function catch_up() {
		if ( wp_doing_ajax() ) {
			return;
		}
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( $next && $next < time() - 5 * MINUTE_IN_SECONDS ) {
			self::purge();
			self::schedule_next_change();
		}
		// A debounced purge that WP-Cron did not run within 90 s.
		$pending = (int) get_option( self::OPT_PENDING, 0 );
		if ( $pending && $pending < time() - 90 ) {
			self::run_deferred();
		}
	}

	/**
	 * Debounced purge: every save (re)starts a DELAY-second timer, the purge runs once after the
	 * last one – saving slides and settings together, or several campaign edits, purge once.
	 */
	public static function request_purge() {
		$at = time() + self::DELAY;
		wp_clear_scheduled_hook( self::DEFER_HOOK );
		wp_schedule_single_event( $at, self::DEFER_HOOK );
		update_option( self::OPT_PENDING, $at, false );
	}

	public static function run_deferred() {
		delete_option( self::OPT_PENDING );
		wp_clear_scheduled_hook( self::DEFER_HOOK );
		self::purge();
	}

	/**
	 * Cron health for the settings screen: ok | late | waiting (just installed, no pulse yet).
	 */
	public static function cron_health() {
		$last  = (int) get_option( self::OPT_LAST, 0 );
		$since = (int) get_option( self::OPT_SINCE, 0 );
		if ( $last && time() - $last <= 75 * MINUTE_IN_SECONDS ) {
			$state = 'ok';
		} elseif ( ! $last && $since && time() - $since < 20 * MINUTE_IN_SECONDS ) {
			$state = 'waiting';
		} else {
			$state = 'late';
		}
		return array(
			'state'    => $state,
			'last'     => $last ? wp_date( 'd.m.Y H:i', $last ) : '',
			'last_ago' => $last ? (int) floor( ( time() - $last ) / MINUTE_IN_SECONDS ) : null,
		);
	}

	public static function enabled() {
		$adv = Wonom_Slider_Data::get_advanced();
		return ! empty( $adv['auto_purge'] );
	}

	public static function auto_purge() {
		if ( self::enabled() ) {
			self::request_purge();
		}
	}

	public static function last_errors() {
		return self::$errors;
	}

	/**
	 * Purge the slider pages (see urls()) in every known page cache plus Cloudflare. Only URL-based
	 * purges are used – a cache layer without one is left alone rather than emptied completely.
	 * Elementor's CSS cache is not touched: the slider's CSS comes from this plugin's own files.
	 * Runs at most once per request unless forced.
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
		$urls         = self::urls();

		$fp = self::flyingpress( $urls );
		if ( $fp ) {
			$done[] = 'FlyingPress';
		}
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $urls );
			$done[] = 'WP Rocket';
		}
		if ( has_action( 'litespeed_purge_url' ) ) {
			foreach ( $urls as $u ) {
				do_action( 'litespeed_purge_url', $u );
			}
			$done[] = 'LiteSpeed Cache';
		}
		if ( function_exists( 'w3tc_flush_url' ) ) {
			foreach ( $urls as $u ) {
				w3tc_flush_url( $u );
			}
			$done[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			foreach ( $urls as $u ) {
				wpsc_delete_url_cache( $u );
			}
			$done[] = 'WP Super Cache';
		}
		if ( has_action( 'cache_enabler_clear_page_cache_by_url' ) ) {
			foreach ( $urls as $u ) {
				do_action( 'cache_enabler_clear_page_cache_by_url', $u );
			}
			$done[] = 'Cache Enabler';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			foreach ( $urls as $u ) {
				sg_cachepress_purge_cache( $u );
			}
			$done[] = 'SiteGround Optimizer';
		}

		if ( self::cloudflare( $urls ) ) {
			$done[] = 'Cloudflare';
		}

		self::$last = array(
			'time'  => time(),
			'urls'  => $urls,
			'done'  => $done,
			'fp'    => $fp,
		);
		update_option( 'wonom_slider_last_purge', self::$last, false );

		/**
		 * Purge your own cache layer (server, CDN…).
		 *
		 * @param string[] $done Caches purged so far.
		 * @param string[] $urls The slider page URLs that were purged.
		 */
		do_action( 'wonom_slider_flush_caches', $done, $urls );

		return $done;
	}

	/**
	 * FlyingPress: purge (and preload) only the given URLs. Returns how it was done, '' if not present.
	 *
	 * @param string[] $urls URLs.
	 * @return string
	 */
	private static function flyingpress( $urls ) {
		$purge = 'FlyingPress\Purge';
		if ( ! class_exists( $purge ) || ! $urls ) {
			return '';
		}
		$how = '';
		try {
			if ( method_exists( $purge, 'purge_urls' ) ) {
				$purge::purge_urls( $urls );
				$how = 'purge_urls';
			} elseif ( method_exists( $purge, 'purge_url' ) ) {
				foreach ( $urls as $u ) {
					$purge::purge_url( $u );
				}
				$how = 'purge_url';
			} else {
				$how = self::flyingpress_delete_files( $urls ) ? 'files' : '';
			}
			// Warm only these pages – never a whole-site preload.
			$preload = 'FlyingPress\Preload';
			if ( $how && class_exists( $preload ) ) {
				if ( method_exists( $preload, 'preload_urls' ) ) {
					$preload::preload_urls( $urls );
					$how .= '+preload_urls';
				} elseif ( method_exists( $preload, 'preload_url' ) ) {
					foreach ( $urls as $u ) {
						$preload::preload_url( $u );
					}
					$how .= '+preload_url';
				}
			}
		} catch ( \Throwable $e ) {
			self::$errors[] = 'FlyingPress: ' . $e->getMessage();
		}
		return $how;
	}

	/**
	 * Fallback for FlyingPress versions without a URL purge: delete the cached files of these URLs
	 * (wp-content/cache/flying-press/<host>/<path>/index*), never anything above or beside them.
	 */
	private static function flyingpress_delete_files( $urls ) {
		$base = defined( 'FLYING_PRESS_CACHE_DIR' ) ? FLYING_PRESS_CACHE_DIR : WP_CONTENT_DIR . '/cache/flying-press/';
		$root = realpath( $base );
		if ( ! $root ) {
			return false;
		}
		$any = false;
		foreach ( $urls as $u ) {
			$p    = wp_parse_url( $u );
			$path = isset( $p['path'] ) ? rawurldecode( $p['path'] ) : '/';
			$dir  = realpath( trailingslashit( $root ) . ( isset( $p['host'] ) ? $p['host'] : '' ) . '/' . ltrim( $path, '/' ) );
			if ( ! $dir || 0 !== strpos( $dir, $root ) || ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( (array) glob( trailingslashit( $dir ) . 'index*' ) as $f ) {
				if ( is_file( $f ) ) {
					wp_delete_file( $f );
					$any = true;
				}
			}
		}
		return $any;
	}

	/* ---------- which pages show the slider ---------- */

	/**
	 * URLs of the pages that show the slider: the home page in every language, pages where the
	 * slider was found (Elementor widget, block, shortcode) or rendered, and URLs from the settings.
	 *
	 * @return string[]
	 */
	public static function urls() {
		$urls = self::home_urls();
		foreach ( self::page_ids() as $id ) {
			$urls = array_merge( $urls, self::post_urls( $id ) );
		}
		$adv = Wonom_Slider_Data::get_advanced();
		foreach ( preg_split( '/\R/', (string) $adv['purge_urls'] ) as $line ) {
			$u = self::normalize_url( $line );
			if ( $u ) {
				$urls[] = $u;
			}
		}
		$urls = array_values( array_unique( array_filter( $urls ) ) );
		/**
		 * Filter the URLs purged when the slider changes.
		 *
		 * @param string[] $urls
		 */
		$urls = (array) apply_filters( 'wonom_slider_purge_urls', $urls );
		return array_slice( array_values( array_unique( $urls ) ), 0, self::MAX_URLS );
	}

	private static function home_urls() {
		$urls = array( home_url( '/' ) );
		foreach ( Wonom_Slider_Data::get_languages() as $l ) {
			if ( function_exists( 'pll_home_url' ) ) {
				$urls[] = pll_home_url( $l['slug'] );
			} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
				$urls[] = apply_filters( 'wpml_permalink', home_url( '/' ), $l['slug'], true );
			}
		}
		return $urls;
	}

	/** A published, public post and its translations. */
	private static function post_urls( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) ) {
			return array();
		}
		$urls = array( get_permalink( $post ) );
		foreach ( Wonom_Slider_Data::get_languages() as $l ) {
			$tid = 0;
			if ( function_exists( 'pll_get_post' ) ) {
				$tid = (int) pll_get_post( $post->ID, $l['slug'] );
			} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
				$tid = (int) apply_filters( 'wpml_object_id', $post->ID, $post->post_type, false, $l['slug'] );
			}
			if ( $tid && $tid !== $post->ID && 'publish' === get_post_status( $tid ) ) {
				$urls[] = get_permalink( $tid );
			}
		}
		return $urls;
	}

	/** A line from the settings: full URL or path on this site. */
	private static function normalize_url( $line ) {
		$line = trim( (string) $line );
		if ( '' === $line ) {
			return '';
		}
		if ( '/' === $line[0] ) {
			$line = home_url( $line );
		}
		$u    = esc_url_raw( $line );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return ( $u && wp_parse_url( $u, PHP_URL_HOST ) === $host ) ? $u : '';
	}

	public static function page_ids() {
		self::maybe_initial_scan();
		$ids = get_option( self::OPT_PAGES, array() );
		return array_values( array_filter( array_map( 'intval', is_array( $ids ) ? $ids : array() ) ) );
	}

	private static function set_page( $id, $on ) {
		$ids = get_option( self::OPT_PAGES, array() );
		$ids = array_map( 'intval', is_array( $ids ) ? $ids : array() );
		$has = in_array( (int) $id, $ids, true );
		if ( $on && ! $has ) {
			$ids[] = (int) $id;
			update_option( self::OPT_PAGES, array_slice( $ids, -self::MAX_PAGES ), true );
		} elseif ( ! $on && $has ) {
			update_option( self::OPT_PAGES, array_values( array_diff( $ids, array( (int) $id ) ) ), true );
		}
	}

	/** The slider was rendered on this page (called from the front end, only for new pages it writes). */
	public static function remember_page( $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return;
		}
		$ids = get_option( self::OPT_PAGES, array() );
		if ( ! is_array( $ids ) || ! in_array( $id, array_map( 'intval', $ids ), true ) ) {
			self::set_page( $id, true );
		}
	}

	private static function has_slider( $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return false;
		}
		if ( false !== strpos( $post->post_content, '[wonom_slider' ) || false !== strpos( $post->post_content, '<!-- wp:wonom/slider' ) ) {
			return true;
		}
		$ed = get_post_meta( $id, '_elementor_data', true );
		return is_string( $ed ) && false !== strpos( $ed, '"widgetType":"wonom_slider"' );
	}

	public static function scan_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$type = get_post_type( $post_id );
		if ( ! $type || ! is_post_type_viewable( $type ) ) {
			return;
		}
		self::set_page( $post_id, self::has_slider( $post_id ) );
	}

	public static function scan_meta( $meta_id, $post_id, $key ) {
		if ( '_elementor_data' === $key ) {
			self::scan_post( (int) $post_id );
		}
	}

	/** One-off search for pages that already contain the slider (runs once, then on request). */
	public static function maybe_initial_scan( $force = false ) {
		if ( ! $force && get_option( self::OPT_SCANNED ) ) {
			return;
		}
		update_option( self::OPT_SCANNED, time(), false );
		global $wpdb;
		$like_w = '%' . $wpdb->esc_like( '"widgetType":"wonom_slider"' ) . '%';
		$like_s = '%' . $wpdb->esc_like( '[wonom_slider' ) . '%';
		$like_b = '%' . $wpdb->esc_like( '<!-- wp:wonom/slider' ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$ids = array_merge(
			(array) $wpdb->get_col( $wpdb->prepare( "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_elementor_data' AND p.post_status = 'publish' AND pm.meta_value LIKE %s LIMIT 100", $like_w ) ),
			(array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND ( post_content LIKE %s OR post_content LIKE %s ) LIMIT 100", $like_s, $like_b ) )
		);
		// phpcs:enable
		$keep = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$type = get_post_type( $id );
			if ( $type && is_post_type_viewable( $type ) ) {
				$keep[] = $id;
			}
		}
		$old = get_option( self::OPT_PAGES, array() );
		$old = array_map( 'intval', is_array( $old ) ? $old : array() );
		update_option( self::OPT_PAGES, array_slice( array_values( array_unique( array_merge( $old, $keep ) ) ), -self::MAX_PAGES ), true );
	}

	/**
	 * A test or staging copy must never purge the shared Cloudflare zone of the live shop.
	 */
	public static function is_live_host() {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$live = ! in_array( wp_get_environment_type(), array( 'staging', 'development', 'local' ), true )
			&& ! preg_match( '/^(test|staging|stage|dev|demo|preview|beta|uat)[.-]/i', $host );
		/**
		 * Filter whether this site is the live one (Cloudflare purges only run there).
		 *
		 * @param bool   $live
		 * @param string $host
		 */
		return (bool) apply_filters( 'wonom_slider_is_live_host', $live, $host );
	}

	/**
	 * Cloudflare edge purge of the given URLs only ("files", max. 30 per request) – never
	 * purge_everything, and never from a test host (the zone is shared with the live shop).
	 *
	 * @param string[] $urls URLs.
	 * @return bool
	 */
	private static function cloudflare( $urls ) {
		$adv   = Wonom_Slider_Data::get_advanced();
		$zone  = trim( (string) $adv['cf_zone'] );
		$token = trim( (string) $adv['cf_token'] );
		if ( '' === $zone || '' === $token || ! $urls || ! self::is_live_host() ) {
			return false;
		}
		$ok = true;
		foreach ( array_chunk( array_values( $urls ), 30 ) as $chunk ) {
			$res = wp_remote_post(
				'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone ) . '/purge_cache',
				array(
					'timeout' => 15,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'files' => $chunk ) ),
				)
			);
			if ( is_wp_error( $res ) ) {
				self::$errors[] = 'Cloudflare: ' . $res->get_error_message();
				$ok             = false;
				continue;
			}
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( empty( $body['success'] ) ) {
				self::$errors[] = 'Cloudflare: ' . ( ! empty( $body['errors'][0]['message'] ) ? $body['errors'][0]['message'] : 'unknown error' );
				$ok             = false;
			}
		}
		return $ok;
	}

	/**
	 * After this plugin was updated the markup/CSS may have changed – purge the slider pages,
	 * but only when automatic purging is on, and debounced like a save.
	 */
	public static function on_upgrade( $upgrader, $data ) {
		if ( ! is_array( $data ) || empty( $data['type'] ) || 'plugin' !== $data['type'] ) {
			return;
		}
		$plugins = ! empty( $data['plugins'] ) && is_array( $data['plugins'] ) ? $data['plugins'] : ( ! empty( $data['plugin'] ) ? array( $data['plugin'] ) : array() );
		if ( in_array( WONOM_SLIDER_BASENAME, $plugins, true ) && self::enabled() ) {
			self::request_purge();
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
		$pending = (int) get_option( self::OPT_PENDING, 0 );
		$last    = get_option( 'wonom_slider_last_purge', array() );
		$fp      = '';
		if ( class_exists( '\FlyingPress\Purge' ) ) {
			$fp = method_exists( '\FlyingPress\Purge', 'purge_urls' ) ? 'purge_urls' : ( method_exists( '\FlyingPress\Purge', 'purge_url' ) ? 'purge_url' : 'files' );
			if ( class_exists( '\FlyingPress\Preload' ) ) {
				$fp .= method_exists( '\FlyingPress\Preload', 'preload_urls' ) ? ' + preload_urls' : ( method_exists( '\FlyingPress\Preload', 'preload_url' ) ? ' + preload_url' : '' );
			}
		}
		return array(
			'enabled'     => self::enabled(),
			'next_tick'   => $next ? wp_date( 'Y-m-d H:i', $next ) : '',
			'wp_cron_off' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'cron'        => self::cron_health(),
			'detected'    => self::detected(),
			'urls'        => self::urls(),
			'live_host'   => self::is_live_host(),
			'pending'     => $pending ? wp_date( 'H:i:s', $pending ) : '',
			'last'        => is_array( $last ) && ! empty( $last['time'] ) ? array(
				'time' => wp_date( 'd.m.Y H:i:s', (int) $last['time'] ),
				'done' => isset( $last['done'] ) ? (array) $last['done'] : array(),
				'fp'   => isset( $last['fp'] ) ? (string) $last['fp'] : '',
			) : null,
			'fp_method'   => $fp,
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
		if ( function_exists( 'rocket_clean_files' ) ) {
			$list[] = 'WP Rocket';
		}
		if ( has_action( 'litespeed_purge_url' ) ) {
			$list[] = 'LiteSpeed Cache';
		}
		if ( function_exists( 'w3tc_flush_url' ) ) {
			$list[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			$list[] = 'WP Super Cache';
		}
		if ( has_action( 'cache_enabler_clear_page_cache_by_url' ) ) {
			$list[] = 'Cache Enabler';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			$list[] = 'SiteGround Optimizer';
		}
		$adv = Wonom_Slider_Data::get_advanced();
		if ( $adv['cf_zone'] && $adv['cf_token'] && self::is_live_host() ) {
			$list[] = 'Cloudflare';
		}
		return $list;
	}
}
