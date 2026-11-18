<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Always removed: technical settings (they hold the GitHub, DeepL and Cloudflare keys),
 * transients and scheduled events – nothing of that is useful without the plugin.
 * Slides, slider settings and the campaign links are the shop's content: they are removed only
 * when "Delete data on uninstall" is switched on (Settings → Data), so a reinstall keeps them.
 *
 * @package Wonom_Slider
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wonom_slider_delete_data = (bool) get_option( 'wonom_slider_delete_on_uninstall' );

// Technical options and keys – always.
delete_option( 'wonom_slider_advanced' );
delete_option( 'wonom_slider_delete_on_uninstall' );
delete_option( 'wonom_slider_cron_last' );
delete_option( 'wonom_slider_cron_since' );

// Transients – always.
delete_site_transient( 'wonom_slider_github_release' );
delete_transient( 'wonom_slider_update_notice' );
delete_transient( 'wonom_slider_activated' );

// Scheduled events – always.
wp_clear_scheduled_hook( 'wonom_slider_schedule_tick' );
wp_clear_scheduled_hook( 'wonom_slider_cron_pulse' );

// Content – only on request.
if ( $wonom_slider_delete_data ) {
	delete_option( 'wonom_slider_slides' );
	delete_option( 'wonom_slider_settings' );
	// "Show in slider" link on Kampaaniariba campaigns (the campaigns themselves stay).
	delete_post_meta_by_key( '_wonom_slider_show' );
	delete_post_meta_by_key( '_wonom_slider_template' );
}
