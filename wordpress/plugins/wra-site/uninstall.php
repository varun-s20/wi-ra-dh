<?php
/**
 * Deleting the plugin removes its settings only. Saved Messages, Clubs, the
 * Clubs page and post bylines are content and are kept.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wra_site_options' );
delete_option( 'wra_site_version' );
