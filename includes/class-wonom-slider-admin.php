<?php
/**
 * Admin screen: menu, assets, app shell. The editor itself is a small vanilla-JS app
 * (assets/admin/admin.js) talking to the REST API.
 *
 * @package Wonom_Slider
 */

defined( 'ABSPATH' ) || exit;

class Wonom_Slider_Admin {

	const PAGE = 'wonom-slider';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'plugin_action_links_' . WONOM_SLIDER_BASENAME, array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Wonom Slider', 'wonom-slider' ),
			__( 'Wonom Slider', 'wonom-slider' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'page' ),
			'dashicons-images-alt2',
			58
		);
	}

	public static function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'toplevel_page_' . self::PAGE === $screen->id;
	}

	public static function body_class( $classes ) {
		return self::is_screen() ? $classes . ' wonom-app' : $classes;
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Slides', 'wonom-slider' ) . '</a>' );
		return $links;
	}

	public static function notices() {
		$msg = get_transient( 'wonom_slider_update_notice' );
		if ( $msg ) {
			delete_transient( 'wonom_slider_update_notice' );
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
		if ( get_transient( 'wonom_slider_activated' ) && ! self::is_screen() ) {
			delete_transient( 'wonom_slider_activated' );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Wonom Slider is active.', 'wonom-slider' ),
				esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
				esc_html__( 'Add your first slide →', 'wonom-slider' )
			);
		}
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'wonom-slider-admin', WONOM_SLIDER_URL . 'assets/admin/admin.css', array(), WONOM_SLIDER_VERSION );
		// Front-end styles + web fonts for the live miniatures in the slide list.
		wp_enqueue_style( 'wonom-slider', WONOM_SLIDER_URL . 'assets/public/slider.css', array(), WONOM_SLIDER_VERSION );
		$fonts = Wonom_Slider_Data::font_stylesheet_url( Wonom_Slider_Data::get_settings() );
		if ( $fonts ) {
			wp_enqueue_style( 'wonom-slider-fonts', $fonts, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		// Interface strings: English source in assets/admin/i18n.js, translated with wp.i18n from
		// languages/wonom-slider-<locale>-wonom-slider-admin-i18n.json (tools/build-translations.ps1).
		wp_enqueue_script( 'wonom-slider-admin-i18n', WONOM_SLIDER_URL . 'assets/admin/i18n.js', array( 'wp-i18n' ), WONOM_SLIDER_VERSION, true );
		wp_set_script_translations( 'wonom-slider-admin-i18n', 'wonom-slider', WONOM_SLIDER_PATH . 'languages' );
		wp_enqueue_script( 'wonom-slider-admin', WONOM_SLIDER_URL . 'assets/admin/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-api-fetch', 'wonom-slider-admin-i18n' ), WONOM_SLIDER_VERSION, true );

		$preview = add_query_arg(
			array(
				'wonom_slider_preview' => 1,
				'_wpnonce'             => wp_create_nonce( 'wonom_slider_preview' ),
			),
			home_url( '/' )
		);

		wp_localize_script(
			'wonom-slider-admin',
			'WONOM_SLIDER',
			array(
				'rest'       => esc_url_raw( rest_url( Wonom_Slider_Rest::NS ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'state'      => Wonom_Slider_Rest::state(),
				'previewUrl' => $preview,
				'version'    => WONOM_SLIDER_VERSION,
				'homeUrl'    => home_url( '/' ),
				'pluginsUrl' => admin_url( 'plugins.php' ),
				'langPlugin' => function_exists( 'pll_languages_list' ) ? 'Polylang' : ( defined( 'ICL_SITEPRESS_VERSION' ) ? 'WPML' : '' ),
			)
		);
	}

	/**
	 * App shell. Everything inside #wonom-slider-app is rendered by JS.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wonom-appbar">
			<a class="wonom-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>">
				<span class="wonom-logo" aria-hidden="true"><span class="dashicons dashicons-images-alt2"></span></span>
				<span class="wonom-brand-name">Wonom Slider</span>
				<span class="wonom-brand-version"><?php echo esc_html( WONOM_SLIDER_VERSION ); ?></span>
			</a>
			<nav class="wonom-nav" id="wonom-nav" aria-label="Wonom Slider">
				<a href="#slides" data-tab="slides" class="is-active"><?php esc_html_e( 'Slides', 'wonom-slider' ); ?></a>
				<a href="#calendar" data-tab="calendar"><?php esc_html_e( 'Calendar', 'wonom-slider' ); ?></a>
				<a href="#settings" data-tab="settings"><?php esc_html_e( 'Settings', 'wonom-slider' ); ?></a>
			</nav>
			<div class="wonom-appbar-actions">
				<span class="wonom-dirty" id="wonom-dirty" hidden><?php esc_html_e( 'Unsaved changes', 'wonom-slider' ); ?></span>
				<button type="button" class="button button-primary wonom-save" id="wonom-save" disabled><?php esc_html_e( 'Save changes', 'wonom-slider' ); ?></button>
			</div>
		</div>
		<div class="wrap wonom-wrap">
			<noscript><p><?php esc_html_e( 'The slider editor needs JavaScript.', 'wonom-slider' ); ?></p></noscript>
			<div id="wonom-slider-app" class="wonom-app-root" aria-live="polite"></div>
		</div>
		<div class="wonom-toasts" id="wonom-toasts" aria-live="polite"></div>
		<?php
	}
}
