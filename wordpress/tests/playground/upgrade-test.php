<?php
/**
 * Test helper for clubs.py (local Playground only, served from /wra-tests/).
 *   ?step=reset  makes the site look like WRA Site 1.0.x: no Clubs page, no stored version
 *   ?step=reset110  makes it look like WRA Site 1.1.0: the page still titled "Clubs"
 *   ?step=check  confirms the first admin visit after the update put it right
 *   ?step=cleanup  removes test clubs an interrupted run left behind
 */
require '/wordpress/wp-load.php';
header( 'Content-Type: text/plain' );
$step = isset( $_GET['step'] ) ? $_GET['step'] : '';

if ( 'reset' === $step ) {
	$page = get_page_by_path( 'clubs', OBJECT, 'page' );
	if ( $page ) {
		wp_delete_post( $page->ID, true );
	}
	delete_option( 'wra_site_version' );
	echo ( ! get_page_by_path( 'clubs', OBJECT, 'page' ) && false === get_option( 'wra_site_version' ) ) ? 'reset ok' : 'reset failed';
} elseif ( 'reset110' === $step ) {
	$page = get_page_by_path( 'clubs', OBJECT, 'page' );
	if ( $page ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_title' => 'Clubs' ) );
	}
	update_option( 'wra_site_version', '1.1.0' );
	echo ( $page && 'Clubs' === get_post( $page->ID )->post_title ) ? 'reset110 ok' : 'reset110 failed';
} elseif ( 'cleanup' === $step ) {
	// test clubs from an interrupted clubs.py run (never the seeded sample clubs)
	foreach ( get_posts( array( 'post_type' => 'wra_club', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
		if ( 'Zebra Test Radio Club' === $p->post_title || '' === trim( $p->post_title ) ) {
			wp_delete_post( $p->ID, true );
		}
	}
	echo 'cleanup ok';
} elseif ( 'check' === $step ) {
	$page = get_page_by_path( 'clubs', OBJECT, 'page' );
	$ok   = $page && 'publish' === $page->post_status && 'Radio Clubs & Sponsors' === html_entity_decode( $page->post_title ) && WRA_SITE_VERSION === get_option( 'wra_site_version' );
	echo $ok ? 'check ok' : 'check failed: ' . wp_json_encode( array( 'page' => $page ? array( $page->post_status, $page->post_title ) : null, 'version' => get_option( 'wra_site_version' ) ) );
}
