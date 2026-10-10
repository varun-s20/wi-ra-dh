<?php
/**
 * Plugin Name:       WRA Site
 * Plugin URI:        https://wi-ra.org/
 * Description:       Wisconsin Repeater Alliance site features that must survive a theme change: the ARCS status notice (WRA Settings), the Contact and Volunteer forms with saved Messages, the News byline field, and redirects from retired URLs.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Digital Heroes
 * License:           Proprietary, for wi-ra.org
 * Text Domain:       wra-site
 */
defined( 'ABSPATH' ) || exit;

define( 'WRA_SITE_VERSION', '1.0.1' );
define( 'WRA_SITE_DIR', plugin_dir_path( __FILE__ ) );

require_once WRA_SITE_DIR . 'inc/settings.php';
require_once WRA_SITE_DIR . 'inc/forms.php';
require_once WRA_SITE_DIR . 'inc/byline.php';
require_once WRA_SITE_DIR . 'inc/redirects.php';

register_activation_hook(
	__FILE__,
	function () {
		wra_site_register_message_type();
		if ( false === get_option( 'wra_site_options' ) ) {
			add_option( 'wra_site_options', wra_site_defaults() );
		}
	}
);
