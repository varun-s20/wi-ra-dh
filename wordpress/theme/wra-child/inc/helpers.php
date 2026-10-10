<?php
/**
 * Helpers called by the templates. Kept small and side-effect free.
 */
defined( 'ABSPATH' ) || exit;

/** Echo a site URL for a root-relative path, e.g. wra_u( '/about/' ). */
function wra_u( $path ) {
	echo esc_url( home_url( $path ) );
}

/**
 * Select the page being rendered: loads its title, description, breadcrumbs and
 * nav section from the generated inc/page-meta.php. Called at the top of every
 * generated template, before get_header().
 */
function wra_page( $key ) {
	static $all = null;
	if ( null === $all ) {
		$all = require WRA_DIR . '/inc/page-meta.php';
	}
	$GLOBALS['wra'] = array_merge(
		array( 'key' => $key, 'og_type' => 'website' ),
		isset( $all[ $key ] ) ? $all[ $key ] : array()
	);
}

/** Metadata for a single News post (single.php). */
function wra_page_post( $post ) {
	$title = wp_strip_all_tags( get_the_title( $post ) );
	// Breadcrumb label: whole title under 60 characters, else cut at a word near 57 (same rule as the static build).
	$short = mb_strlen( $title ) < 60 ? $title : preg_replace( '/\s+\S*$/u', '', mb_substr( $title, 0, 57 ) ) . '…';
	$GLOBALS['wra'] = array(
		'key'         => 'post',
		'title'       => $title . ' – Wisconsin Repeater Alliance',
		'og_title'    => $title,
		'description' => wra_post_summary( $post ),
		'nav'         => 'news',
		'path'        => wp_parse_url( get_permalink( $post ), PHP_URL_PATH ),
		'noindex'     => false,
		'og_type'     => 'article',
		'crumbs'      => array( array( 'News', '/news/' ), array( esc_html( $short ), null ) ),
		'article'     => array(
			'date'   => get_the_date( 'Y-m-d', $post ),
			'author' => wra_byline( $post, false ),
		),
		'image'       => get_the_post_thumbnail_url( $post, 'large' ),
	);
}

/** Metadata for anything without its own template (added pages, archives, search). */
function wra_page_generic() {
	$GLOBALS['wra'] = array(
		'key'      => 'generic',
		'nav'      => is_page() ? '' : 'news',
		'og_type'  => 'website',
		'noindex'  => is_search(),
		'crumbs'   => array( array( esc_html( wp_strip_all_tags( is_singular() ? get_the_title() : wp_get_document_title() ) ), null ) ),
	);
	if ( is_singular() ) {
		$GLOBALS['wra']['path'] = wp_parse_url( get_permalink(), PHP_URL_PATH );
	}
}

/** True once a WRA template has claimed the request (drives the style neutraliser). */
function wra_is_ours() {
	return ! empty( $GLOBALS['wra'] );
}

function wra_meta( $field, $default = '' ) {
	return isset( $GLOBALS['wra'][ $field ] ) ? $GLOBALS['wra'][ $field ] : $default;
}

/** Breadcrumbs for the current page, same markup as the static build. */
function wra_crumbs() {
	$items = array( '<li><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li>' );
	foreach ( (array) wra_meta( 'crumbs', array() ) as $crumb ) {
		list( $label, $path ) = $crumb;
		$label   = wp_kses( $label, array( 'span' => array( 'class' => array() ) ) );
		$items[] = $path
			? '<li><a href="' . esc_url( home_url( $path ) ) . '">' . $label . '</a></li>'
			: '<li aria-current="page">' . $label . '</li>';
	}
	echo '<nav class="crumbs" aria-label="Breadcrumb"><ol>' . implode( '', $items ) . '</ol></nav>';
}

/** Marks the top-level nav item for the current section. */
function wra_nav_current( $section ) {
	if ( wra_meta( 'nav' ) === $section ) {
		echo ' data-current';
	}
}

/** aria-current="page" on links that point at the page being viewed. */
function wra_aria_current( $path ) {
	$here = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/', PHP_URL_PATH );
	$there = wp_parse_url( home_url( $path ), PHP_URL_PATH );
	if ( $here && $there && trailingslashit( $here ) === trailingslashit( $there ) ) {
		echo ' aria-current="page"';
	}
}

/** Hidden fields every website form carries: which form, when it was shown, and a honeypot. */
function wra_form_fields( $form ) {
	printf(
		'<input type="hidden" name="_form" value="%1$s"><input type="hidden" name="_ts" value="%2$d">' .
		'<div class="wra-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="_hp" value="" tabindex="-1" autocomplete="off"></label></div>',
		esc_attr( $form ),
		time()
	);
}

/** Keep "501(c)(3)" and "509(a)(2)" from breaking across lines in already-escaped text. */
function wra_nowrap( $escaped ) {
	return preg_replace( '/\b(501\(c\)\(3\)|509\(a\)\(2\))/', '<span class="nowrap">$1</span>', $escaped );
}
