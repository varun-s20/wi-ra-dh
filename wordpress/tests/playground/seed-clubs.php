<?php
/**
 * Test fixture: the fourteen sample listings from the static Clubs preview
 * (_build/clubs-sample.json), so compare.py can check the WordPress Clubs page
 * against the design pixel for pixel. Logos come from the static source's
 * assets/img/samples, mounted at /wordpress/wra-samples. Entered in a random
 * order on purpose: the page must still list them A to Z.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_set_current_user( 1 );

$clubs = array(
	array( 'Wisconsin Example Radio Club', 'https://www.example.com/wisconsin/', '' ),
	array( 'Sample County Repeater Association', 'https://example.com/scra/', 'club-sample-county.webp' ),
	array( 'The Demo Valley Radio Society', 'https://example.net/dvrs/', 'club-demo-valley.webp' ),
	array( 'Anytown Amateur Radio Club', 'https://www.example.org/anytown-arc/', 'club-anytown.webp' ),
	array( 'Placeholder Emergency Communications Group', '', 'club-placeholder-ecg.jpg' ),
	array( 'Test Lakes Repeater Club', 'https://example.net/', '' ),
	array( 'North Woods Sample Amateur Radio Association', 'https://www.example.org/north-woods/', 'club-north-woods.webp' ),
	array( 'Example Amateur Radio Club', 'https://www.example.com/', '' ),
	array( 'Sample Equipment Sponsor Co.', 'https://www.example.com/sponsor/', 'club-sample-sponsor.webp' ),
	array( 'Badger State Sample Repeater Society', 'https://example.net/badger-state/', 'club-badger-state.webp' ),
	array( 'Rivertown Test Amateur Radio Club', 'https://example.net/rivertown/', '' ),
	array( 'Lake Example Emergency Net', 'https://example.org/leen/', 'club-lake-example.webp' ),
	array( 'Cedar Valley Amateur Radio Club', 'https://www.example.com/cedar-valley/', '' ),
	array( 'Dairyland Demo Radio Association', '', '' ),
);
$log = array();
foreach ( $clubs as $c ) {
	if ( get_posts( array( 'post_type' => 'wra_club', 'title' => $c[0], 'post_status' => 'any', 'numberposts' => 1 ) ) ) {
		continue;
	}
	$id = wp_insert_post( array( 'post_type' => 'wra_club', 'post_status' => 'publish', 'post_title' => $c[0] ) );
	if ( $c[1] ) {
		update_post_meta( $id, '_wra_club_url', $c[1] );
	}
	$src = '/wordpress/wra-samples/' . $c[2];
	if ( $c[2] && file_exists( $src ) ) {
		$tmp = wp_tempnam( $c[2] );
		copy( $src, $tmp );
		$att = media_handle_sideload( array( 'name' => $c[2], 'tmp_name' => $tmp ), $id );
		if ( ! is_wp_error( $att ) ) {
			set_post_thumbnail( $id, $att );
		}
	}
	$log[] = $id . ' ' . $c[0] . ( $c[2] && ! has_post_thumbnail( $id ) ? ' (logo missing)' : '' );
}
// A hidden club: must not appear on the site.
if ( ! get_posts( array( 'post_type' => 'wra_club', 'title' => 'Hidden Draft Radio Club', 'post_status' => 'any', 'numberposts' => 1 ) ) ) {
	wp_insert_post( array( 'post_type' => 'wra_club', 'post_status' => 'draft', 'post_title' => 'Hidden Draft Radio Club' ) );
}
if ( is_dir( '/wordpress/wralogs' ) ) {
	file_put_contents( '/wordpress/wralogs/seed-clubs.txt', implode( "\n", $log ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}
