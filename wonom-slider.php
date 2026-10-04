<?php
/**
 * Plugin Name:       Wonom Slider
 * Plugin URI:        https://github.com/roosimagi-erx/wonom-slider
 * Description:       Lightweight, easy-to-manage hero slider for WooCommerce / WordPress home pages. Multiple slides, scheduled campaign banners, responsive images, GitHub auto-updates.
 * Version:           1.5.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Wonom Digital OÜ
 * Author URI:        https://wonom.ee
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wonom-slider
 * Domain Path:       /languages
 * Update URI:        https://github.com/roosimagi-erx/wonom-slider
 */

defined( 'ABSPATH' ) || exit;

define( 'WONOM_SLIDER_VERSION', '1.5.2' );
define( 'WONOM_SLIDER_FILE', __FILE__ );
define( 'WONOM_SLIDER_PATH', plugin_dir_path( __FILE__ ) );
define( 'WONOM_SLIDER_URL', plugin_dir_url( __FILE__ ) );
define( 'WONOM_SLIDER_BASENAME', plugin_basename( __FILE__ ) );

/*
 * GitHub repository used by the auto-updater.
 * Change these two values to match your repository (owner/name).
 * For a private repository define WONOM_SLIDER_GITHUB_TOKEN in wp-config.php.
 */
if ( ! defined( 'WONOM_SLIDER_GITHUB_OWNER' ) ) {
	define( 'WONOM_SLIDER_GITHUB_OWNER', 'roosimagi-erx' );
}
if ( ! defined( 'WONOM_SLIDER_GITHUB_REPO' ) ) {
	define( 'WONOM_SLIDER_GITHUB_REPO', 'wonom-slider' );
}

require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-data.php';
require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-frontend.php';
require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-admin.php';
require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-rest.php';
require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-updater.php';
require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider-cache.php';
require_once WONOM_SLIDER_PATH . 'includes/class-wonom-slider.php';

/**
 * Template tag: echo the slider anywhere in a theme.
 *
 * @param array $args Optional shortcode-like arguments.
 */
function wonom_slider( $args = array() ) {
	echo Wonom_Slider_Frontend::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped.
}

Wonom_Slider::instance();
