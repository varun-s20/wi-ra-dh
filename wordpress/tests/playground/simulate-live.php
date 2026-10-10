<?php
/**
 * Recreates wi-ra.org's current WordPress state in a local Playground site, so the
 * one-time setup can be rehearsed against it: Elementor "Full Width" pages, the
 * FAQ/Volunteer/Pay Dues pages, and the five News posts with their live slugs
 * (including the Elementor-built WAR post on its placeholder slug).
 */
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );

update_option( 'permalink_structure', '/%postname%/' );
update_option( 'blogname', 'Wisconsin Repeater Alliance' );

$pages = array(
	'home'                   => 'Home',
	'about'                  => 'About WRA',
	'frequency-coordination' => 'What Is Frequency Coordination?',
	'repeater-coordination'  => 'Repeater Coordination',
	'arcs'                   => 'Amateur Radio Coordination System (ARCS)',
	'membership'             => 'Membership',
	'membership-pay-dues'    => 'How to Pay Membership Dues',
	'volunteer'              => 'Volunteer Opportunities',
	'faq'                    => 'FAQ',
	'contact'                => 'Contact Us',
	'news'                   => 'News &amp; Announcements',
);
foreach ( $pages as $slug => $title ) {
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => '<p>Old Elementor page content.</p>' ) );
	update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	if ( 'home' === $slug ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );
	}
}

$posts = array(
	array( 'news-welcome-to-wra', 'Welcome to the new Wisconsin Repeater Alliance website', '2026-08-26 10:00:00', false ),
	array( 'lorem-ipsum-dolor-sit-amet-consectetur-2', 'Wisconsin Association of Repeaters Announces Organizational Transition', '2026-08-26 11:00:00', true ),
	array( 'an-open-letter-to-the-wisconsin-amateur-radio-community', 'An Open Letter to the Wisconsin Amateur Radio Community', '2026-09-11 10:00:00', false ),
	array( 'arcs-amateur-radio-coordination-system', 'ARCS, Amateur Radio Coordination System', '2026-09-12 10:00:00', false ),
	array( 'wisconsin-repeater-alliance-receives-federal-501c3-public-charity-status', 'Wisconsin Repeater Alliance Receives Federal 501(c)(3) Public Charity Status', '2026-09-24 10:00:00', false ),
);
foreach ( $posts as $p ) {
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $p[0], 'post_title' => $p[1], 'post_date' => $p[2], 'post_content' => '<p>Old post text.</p>' ) );
	if ( $p[3] ) {
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	}
}
wp_delete_post( 1, true ); // "Hello world!"
wp_delete_post( 2, true ); // "Sample Page"
flush_rewrite_rules();
echo "simulated live state\n";
