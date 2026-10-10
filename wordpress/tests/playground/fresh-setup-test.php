<?php
/**
 * Rehearses the WRA one-time setup on a brand-new WordPress (the staging case):
 * run, check, undo, check, run again. Results go to /wordpress/wralogs/fresh-test.txt.
 */
require '/wordpress/wp-load.php';
wp_set_current_user( 1 );

$out   = array();
$check = function ( $label, $ok, $detail = '' ) use ( &$out ) {
	$out[] = ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok || '' === $detail ? '' : "  ($detail)" );
};

$out[] = 'PLAN';
foreach ( wra_setup_plan() as $line ) {
	$out[] = '  - ' . $line;
}

$r = wra_setup_run();
$check( 'setup runs on a fresh install', ! is_wp_error( $r ), is_wp_error( $r ) ? $r->get_error_message() : '' );
$check( 'theme active', 'wra-child' === get_option( 'stylesheet' ) );
$front = get_post( (int) get_option( 'page_on_front' ) );
$check( 'front page created', $front && 'publish' === $front->post_status, $front ? $front->post_name : 'none' );
foreach ( array( 'about', 'frequency-coordination', 'repeater-coordination', 'arcs', 'membership', 'resources', 'news', 'contact', 'privacy-policy', 'terms-of-use' ) as $slug ) {
	$p = get_page_by_path( $slug );
	$check( "page /$slug/ published", $p && 'publish' === $p->post_status );
}
$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
$check( 'exactly the 5 News posts are published', 5 === count( $posts ), count( $posts ) . ': ' . implode( ', ', wp_list_pluck( $posts, 'post_name' ) ) );
$check( 'newest post is the 501(c)(3) announcement', $posts && 'wisconsin-repeater-alliance-receives-federal-501c3-public-charity-status' === $posts[0]->post_name );
$check( 'posts keep their original dates', $posts && '2026-09-24' === get_the_date( 'Y-m-d', $posts[0] ) );
$check( 'same-day posts keep the approved order (WAR newer than Welcome)', $posts && 'wisconsin-association-of-repeaters-transition' === $posts[3]->post_name && 'news-welcome-to-wra' === $posts[4]->post_name, implode( ', ', wp_list_pluck( $posts, 'post_name' ) ) );
$news = get_term_by( 'slug', 'news', 'category' );
$check( 'News category created', (bool) $news );
$all_news = true;
$all_img  = true;
foreach ( $posts as $p ) {
	$all_news = $all_news && in_array( (int) $news->term_id, wp_get_post_categories( $p->ID ), true );
	$all_img  = $all_img && (bool) get_post_thumbnail_id( $p );
}
$check( 'every post is in News', $all_news );
$check( 'every post has a featured image', $all_img );
$check( 'sample "Hello world!" binned', 'trash' === get_post_status( 1 ) );
$sample = get_posts( array( 'name' => 'sample-page__trashed', 'post_type' => 'page', 'post_status' => 'trash', 'numberposts' => 1 ) );
$check( 'sample page binned', (bool) $sample || 'trash' === get_post_status( 2 ) );
$check( 'site title set', 'Wisconsin Repeater Alliance' === get_option( 'blogname' ) );
$check( 'tagline set', 'Coordinating Wisconsin Amateur Radio' === get_option( 'blogdescription' ) );
$check( 'time zone America/Chicago', 'America/Chicago' === get_option( 'timezone_string' ) );
$check( 'permalinks post name', '/%postname%/' === get_option( 'permalink_structure' ) );
$check( 'site icon set', (bool) get_option( 'site_icon' ) );

$u = wra_setup_undo();
$check( 'undo runs', ! is_wp_error( $u ) );
$check( 'theme restored', 'hello-elementor' === get_option( 'stylesheet' ) );
$check( 'sample post restored', 'publish' === get_post_status( 1 ) );
$check( 'site title restored', 'Wisconsin Repeater Alliance' !== get_option( 'blogname' ), get_option( 'blogname' ) );
$check( 'News category removed', ! get_term_by( 'slug', 'news', 'category' ) );
$left = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1 ) );
$check( 'created posts binned, sample post back', 1 === count( $left ) && 'hello-world' === $left[0]->post_name, implode( ', ', wp_list_pluck( $left, 'post_name' ) ) );

// leave the site in the "after setup" state for browsing
foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'trash', 'numberposts' => -1 ) ) as $t ) {
	wp_delete_post( $t->ID, true );
}
$r = wra_setup_run();
$check( 'setup runs again after undo', ! is_wp_error( $r ) && 'wra-child' === get_option( 'stylesheet' ), is_wp_error( $r ) ? $r->get_error_message() : '' );
$check( 'still exactly 5 posts after re-run', 5 === count( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1 ) ) ) );

file_put_contents( '/wordpress/wralogs/fresh-test.txt', implode( "\n", $out ) . "\n" );
echo implode( "\n", $out ) . "\n";
