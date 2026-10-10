<?php
/**
 * Theme setup, template routing and the style neutraliser.
 */
defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'responsive-embeds' );
	},
	20
);

/*
 * Hello Elementor: switch off its stylesheets, its header/footer layer and its
 * description tag. Its Elementor location registration stays on, which stops
 * Elementor Pro from injecting a Theme Builder header into these templates.
 */
add_filter( 'hello_elementor_enqueue_style', '__return_false' );
add_filter( 'hello_elementor_enqueue_theme_style', '__return_false' );
add_filter( 'hello_elementor_header_footer', '__return_false' );
add_filter( 'hello_elementor_description_meta_tag', '__return_false' );

/*
 * Template routing. The live pages were built with Elementor's "Full Width"
 * page template, which would otherwise win over page-{slug}.php. Our design
 * templates take precedence whenever one exists for the request.
 */
add_filter(
	'template_include',
	function ( $template ) {
		$pick = '';
		if ( is_front_page() ) {
			$pick = 'front-page.php';
		} elseif ( is_page() ) {
			$slug = get_post_field( 'post_name', get_queried_object_id() );
			if ( $slug && file_exists( WRA_DIR . "/page-{$slug}.php" ) ) {
				$pick = "page-{$slug}.php";
			}
		} elseif ( is_singular( 'post' ) ) {
			$pick = 'single.php';
		} elseif ( is_404() ) {
			$pick = '404.php';
		}
		return ( $pick && file_exists( WRA_DIR . '/' . $pick ) ) ? WRA_DIR . '/' . $pick : $template;
	},
	999
);

/* The design's CSS and JS, cache-busted by file time. */
add_action(
	'wp_enqueue_scripts',
	function () {
		$css = '/assets/css/wra.css';
		$wp  = '/assets/css/wp.css';
		$js  = '/assets/js/wra.js';
		wp_enqueue_style( 'wra', get_stylesheet_directory_uri() . $css, array(), filemtime( WRA_DIR . $css ) );
		wp_enqueue_style( 'wra-wp', get_stylesheet_directory_uri() . $wp, array( 'wra' ), filemtime( WRA_DIR . $wp ) );
		wp_enqueue_script(
			'wra',
			get_stylesheet_directory_uri() . $js,
			array(),
			filemtime( WRA_DIR . $js ),
			array( 'strategy' => 'defer', 'in_footer' => false )
		);
	},
	20
);

/*
 * The neutraliser: on our templates, nothing but the design's own CSS styles the
 * page. Elementor (and Elementor Pro), its kit/global styles, Hello's styles and
 * WordPress's block/global styles are dequeued. Posts keep the block library so
 * images, galleries and embeds added in the editor still lay out.
 */
function wra_neutralise_assets() {
	if ( ! wra_is_ours() || is_admin() ) {
		return;
	}
	$keep_blocks = is_singular( 'post' );
	$style_prefixes = array( 'elementor', 'e-', 'swiper', 'hello-elementor', 'google-fonts', 'font-awesome', 'classic-theme-styles' );
	if ( ! $keep_blocks ) {
		$style_prefixes[] = 'wp-block-';
		$style_prefixes[] = 'global-styles';
	}
	foreach ( wp_styles()->queue as $handle ) {
		foreach ( $style_prefixes as $prefix ) {
			if ( 0 === strpos( $handle, $prefix ) ) {
				wp_dequeue_style( $handle );
				break;
			}
		}
	}
	foreach ( wp_scripts()->queue as $handle ) {
		if ( 0 === strpos( $handle, 'elementor' ) || 0 === strpos( $handle, 'e-' ) || 0 === strpos( $handle, 'swiper' ) ) {
			wp_dequeue_script( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'wra_neutralise_assets', 9999 );
add_action( 'wp_footer', 'wra_neutralise_assets', 1 );

/* Keep <body> free of theme/builder classes the design never asked for (Elementor's kit class restyles everything). */
add_filter(
	'body_class',
	function ( $classes ) {
		if ( ! wra_is_ours() ) {
			return $classes;
		}
		$keep = array_values( array_intersect( $classes, array( 'admin-bar', 'logged-in', 'customize-support', 'no-customize-support' ) ) );
		$keep[] = 'wra';
		$keep[] = 'wra-' . sanitize_html_class( wra_meta( 'key', 'page' ) );
		return $keep;
	},
	999
);

/* Head clean-up: nothing here is used by the design. */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
add_filter( 'emoji_svg_url', '__return_false' );

/* 404s and searches are not for search engines. */
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_404() || is_search() || wra_meta( 'noindex' ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);
