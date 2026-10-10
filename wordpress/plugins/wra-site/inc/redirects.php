<?php
/**
 * Permanent redirects from retired URLs to where that content lives now.
 * Renamed posts are listed too: WordPress's own old-slug redirect does not
 * survive a fresh install or an All-in-One WP Migration import.
 */
defined( 'ABSPATH' ) || exit;

function wra_site_redirect_map() {
	return array(
		'/faq/'                  => '/resources/#faq',
		'/volunteer/'            => '/resources/#volunteer',
		'/membership-pay-dues/'  => '/membership/#dues',
		// the WAR transition post's address on the old site
		'/lorem-ipsum-dolor-sit-amet-consectetur-2/' => '/wisconsin-association-of-repeaters-transition/',
		// URLs used by the design preview, in case anyone saved them
		'/coordination/'         => '/frequency-coordination/',
		'/coordination/process/' => '/repeater-coordination/',
		'/privacy/'              => '/privacy-policy/',
		'/terms/'                => '/terms-of-use/',
	);
}

add_action(
	'template_redirect',
	function () {
		$req  = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( ! $req ) {
			return;
		}
		$path = trailingslashit( '/' . ltrim( substr( $req, strlen( $base ) ), '/' ) );
		// the static design preview's .html addresses
		if ( preg_match( '#^/([a-z0-9-]+)\.html/$#', $path, $m ) ) {
			$static = array(
				'index'                => '/',
				'about'                => '/about/',
				'coordination'         => '/frequency-coordination/',
				'coordination-process' => '/repeater-coordination/',
				'arcs'                 => '/arcs/',
				'membership'           => '/membership/',
				'resources'            => '/resources/',
				'news'                 => '/news/',
				'contact'              => '/contact/',
				'privacy'              => '/privacy-policy/',
				'terms'                => '/terms-of-use/',
			);
			if ( isset( $static[ $m[1] ] ) ) {
				wp_safe_redirect( home_url( $static[ $m[1] ] ), 301 );
				exit;
			}
		}
		$map = wra_site_redirect_map();
		if ( isset( $map[ $path ] ) ) {
			wp_safe_redirect( home_url( $map[ $path ] ), 301 );
			exit;
		}
	},
	1
);
