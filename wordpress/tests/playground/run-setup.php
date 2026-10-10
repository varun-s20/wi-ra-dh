<?php
/**
 * Rehearses the WRA one-time setup against the simulated live site:
 * run it, undo it and check everything is back, then run it again.
 * Results go to /wordpress/wralogs/setup-test.txt.
 */
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );

$out   = array();
$check = function ( $label, $ok ) use ( &$out ) {
	$out[] = ( $ok ? 'PASS ' : 'FAIL ' ) . $label;
};
$war = function () {
	$p = get_posts( array( 'title' => '', 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1 ) );
	foreach ( $p as $x ) {
		if ( false !== strpos( $x->post_title, 'Wisconsin Association of Repeaters' ) ) {
			return $x;
		}
	}
	return null;
};

$out[] = 'PLAN';
foreach ( wra_setup_plan() as $line ) {
	$out[] = '  - ' . $line;
}

// 1. run
$r = wra_setup_run();
$check( 'setup runs', ! is_wp_error( $r ) );
$about = get_page_by_path( 'about' );
$check( 'theme is wra-child', 'wra-child' === get_option( 'stylesheet' ) );
$check( 'about page on default template', 'default' === get_post_meta( $about->ID, '_wp_page_template', true ) );
$check( 'resources page created', (bool) get_page_by_path( 'resources' ) );
$check( 'faq page drafted', 'draft' === get_page_by_path( 'faq' )->post_status );
$w = $war();
$check( 'WAR post renamed', 'wisconsin-association-of-repeaters-transition' === $w->post_name );
$check( 'WAR post has approved title', 0 === strpos( $w->post_title, 'Wisconsin Association of Repeaters Announces Organizational Transition and Future' ) );
$check( 'WAR post Elementor builder parked', '' === get_post_meta( $w->ID, '_elementor_edit_mode', true ) );
$check( 'WAR post has featured image', (bool) get_post_thumbnail_id( $w ) );
$letter = get_page_by_path( 'an-open-letter-to-the-wisconsin-amateur-radio-community', OBJECT, 'post' );
$check( 'open letter byline set', 'Corey Becker, KD9HCW, President' === get_post_meta( $letter->ID, '_wra_byline', true ) );
$check( 'site icon set', (bool) get_option( 'site_icon' ) );
$check( 'running twice is refused', is_wp_error( wra_setup_run() ) );

// 2. undo
$u = wra_setup_undo();
$check( 'undo runs', ! is_wp_error( $u ) );
$check( 'theme back to hello-elementor', 'hello-elementor' === get_option( 'stylesheet' ) );
$check( 'about page template restored', 'elementor_header_footer' === get_post_meta( $about->ID, '_wp_page_template', true ) );
$check( 'resources page removed', ! get_page_by_path( 'resources' ) || 'trash' === get_page_by_path( 'resources' )->post_status );
$check( 'faq page published again', 'publish' === get_page_by_path( 'faq' )->post_status );
$w = get_post( $w->ID );
$check( 'WAR post slug restored', 'lorem-ipsum-dolor-sit-amet-consectetur-2' === $w->post_name );
$check( 'WAR post text restored', '<p>Old post text.</p>' === $w->post_content );
$check( 'WAR post Elementor builder restored', 'builder' === get_post_meta( $w->ID, '_elementor_edit_mode', true ) );
$check( 'open letter byline removed', '' === get_post_meta( $letter->ID, '_wra_byline', true ) );

// 3. run again (the state the local site is left in)
wp_delete_post( get_page_by_path( 'resources' ) ? get_page_by_path( 'resources' )->ID : 0, true );
wp_delete_post( get_page_by_path( 'terms-of-use' ) ? get_page_by_path( 'terms-of-use' )->ID : 0, true );
wp_delete_post( get_page_by_path( 'privacy-policy' ) ? get_page_by_path( 'privacy-policy' )->ID : 0, true );
$r = wra_setup_run();
$check( 'setup runs again after undo', ! is_wp_error( $r ) && 'wra-child' === get_option( 'stylesheet' ) );

file_put_contents( '/wordpress/wralogs/setup-test.txt', implode( "\n", $out ) . "\n" );
echo implode( "\n", $out ) . "\n";
