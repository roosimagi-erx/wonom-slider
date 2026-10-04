<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Slides and settings are removed only if "Delete data on uninstall" was enabled.
 *
 * @package Wonom_Slider
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( get_option( 'wonom_slider_delete_on_uninstall' ) ) {
	delete_option( 'wonom_slider_slides' );
	delete_option( 'wonom_slider_settings' );
	delete_option( 'wonom_slider_delete_on_uninstall' );
}
delete_site_transient( 'wonom_slider_github_release' );
wp_clear_scheduled_hook( 'wonom_slider_schedule_tick' );
