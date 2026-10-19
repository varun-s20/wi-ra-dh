<?php
/**
 * Plugin Name:       WRA Site
 * Plugin URI:        https://wi-ra.org/
 * Description:       Wisconsin Repeater Alliance site features that must survive a theme change: the Radio Clubs & Sponsors directory (wp-admin → Clubs & Sponsors), the ARCS status notice (WRA Settings), the Contact and Volunteer forms with saved Messages, the News byline field, and redirects from retired URLs.
 * Version:           1.1.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Digital Heroes
 * License:           Proprietary, for wi-ra.org
 * Text Domain:       wra-site
 */
defined( 'ABSPATH' ) || exit;

define( 'WRA_SITE_VERSION', '1.1.1' );
define( 'WRA_SITE_DIR', plugin_dir_path( __FILE__ ) );

require_once WRA_SITE_DIR . 'inc/settings.php';
require_once WRA_SITE_DIR . 'inc/forms.php';
require_once WRA_SITE_DIR . 'inc/byline.php';
require_once WRA_SITE_DIR . 'inc/redirects.php';
require_once WRA_SITE_DIR . 'inc/clubs.php';

/**
 * One-time steps for a new version. Runs on activation, and on the first admin
 * page load after the plugin is updated by uploading a new zip (which does not
 * re-run activation).
 */
function wra_site_upgrade() {
	$was = (string) get_option( 'wra_site_version', '1.0.0' );
	if ( version_compare( $was, '1.1.0', '<' ) ) {
		wra_site_ensure_clubs_page();
	}
	if ( version_compare( $was, '1.1.1', '<' ) ) {
		// The directory was renamed Radio Clubs & Sponsors (Corey, Oct 19). Rename the
		// page record too, unless someone has already given it another title.
		$page = get_page_by_path( 'clubs', OBJECT, 'page' );
		if ( $page && 'Clubs' === $page->post_title ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_title' => 'Radio Clubs & Sponsors' ) );
		}
	}
	update_option( 'wra_site_version', WRA_SITE_VERSION );
}

add_action(
	'admin_init',
	function () {
		if ( WRA_SITE_VERSION !== get_option( 'wra_site_version' ) && current_user_can( 'activate_plugins' ) ) {
			wra_site_upgrade();
		}
	}
);

register_activation_hook(
	__FILE__,
	function () {
		wra_site_register_message_type();
		wra_site_register_club_type();
		if ( false === get_option( 'wra_site_options' ) ) {
			add_option( 'wra_site_options', wra_site_defaults() );
		}
		wra_site_upgrade();
	}
);
