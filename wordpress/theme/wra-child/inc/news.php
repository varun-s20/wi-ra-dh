<?php
/**
 * News = ordinary WordPress posts. These helpers render them in the design's markup.
 */
defined( 'ABSPATH' ) || exit;

/** The one place News queries are built. */
function wra_news_query( array $args = array() ) {
	return new WP_Query(
		wp_parse_args(
			$args,
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 3,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		)
	);
}

/** Card summary: the post's Excerpt field, else the opening of the post. */
function wra_post_summary( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	if ( has_excerpt( $post ) ) {
		return wp_strip_all_tags( $post->post_excerpt );
	}
	return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 32, '…' );
}

/** <time> in the design's format, e.g. September 24, 2026. */
function wra_post_time( $post ) {
	return sprintf( '<time datetime="%s">%s</time>', esc_attr( get_the_date( 'Y-m-d', $post ) ), esc_html( get_the_date( 'F j, Y', $post ) ) );
}

/** Title, escaped, with 501(c)(3) kept on one line. */
function wra_post_title( $post ) {
	return wra_nowrap( esc_html( wp_strip_all_tags( get_the_title( $post ) ) ) );
}

/**
 * Byline: the post's "Byline" field (WRA Site plugin) or the organisation.
 * $fallback false returns '' when no byline is set (used for schema).
 */
function wra_byline( $post, $fallback = true ) {
	$post   = get_post( $post );
	$byline = function_exists( 'wra_site_byline' ) ? wra_site_byline( $post->ID ) : '';
	if ( '' === $byline && $fallback ) {
		$byline = 'Wisconsin Repeater Alliance';
	}
	return $byline;
}

/** Minutes to read, at 230 words a minute (same rule as the static build). */
function wra_reading_time( $post ) {
	$post  = get_post( $post );
	$words = str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	return max( 1, (int) ceil( $words / 230 ) );
}

/**
 * The post's image for cards and the article side panel: its featured image,
 * else the WRA logo. $attrs are passed through (loading, alt).
 */
function wra_post_image( $post, $size = 'medium_large', array $attrs = array() ) {
	$post = get_post( $post );
	if ( has_post_thumbnail( $post ) ) {
		return get_the_post_thumbnail( $post, $size, $attrs );
	}
	$attrs = wp_parse_args( $attrs, array( 'alt' => 'Wisconsin Repeater Alliance logo' ) );
	$extra = '';
	foreach ( $attrs as $k => $v ) {
		if ( 'alt' !== $k ) {
			$extra .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
		}
	}
	return sprintf( '<img src="%simg/brand/wra-logo-320.webp" width="320" height="306"%s alt="%s">', WRA_A, $extra, esc_attr( $attrs['alt'] ) );
}
