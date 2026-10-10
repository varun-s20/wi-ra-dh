<?php
/**
 * Everything in <head> that the static build wrote by hand: title, description,
 * Open Graph, fonts, image preload, icons and JSON-LD. No SEO plugin is used, so
 * this is the only source of these tags (no duplicate schema graphs).
 */
defined( 'ABSPATH' ) || exit;

/* <title> */
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$t = wra_meta( 'title' );
		return $t ? $t : $title;
	},
	20
);
add_filter(
	'document_title_separator',
	function () {
		return '–';
	}
);

/* The canonical URL of the current view (absolute; follows Settings → General). */
function wra_canonical() {
	$path = wra_meta( 'path' );
	if ( '' !== $path && null !== $path ) {
		return home_url( $path );
	}
	return is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
}

add_action(
	'wp_head',
	function () {
		// Lets the CSS hide reveal-on-scroll content only when JS is running (same as the static build).
		echo '<script>document.documentElement.classList.add("js");</script>' . "\n";

		$desc = wra_meta( 'description' );
		if ( ! $desc && is_singular() ) {
			$desc = wra_post_summary( get_queried_object() );
		}
		$title = wra_meta( 'og_title', wp_get_document_title() );
		$image = wra_meta( 'image' ) ? wra_meta( 'image' ) : WRA_A . 'img/og-wra.jpg';

		if ( $desc ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		echo '<meta name="theme-color" content="#022259">' . "\n";
		printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( wra_meta( 'og_type', 'website' ) ) );
		echo '<meta property="og:site_name" content="Wisconsin Repeater Alliance">' . "\n";
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
		if ( $desc ) {
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		if ( ! is_404() ) {
			printf( '<meta property="og:url" content="%s">' . "\n", esc_url( wra_canonical() ) );
		}
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

		// Site icon: WordPress prints it when one is set (Appearance → Customize → Site Identity); otherwise the theme's.
		if ( ! has_site_icon() ) {
			printf( '<link rel="icon" type="image/png" sizes="32x32" href="%sicons/favicon-32.png">' . "\n", WRA_A );
			printf( '<link rel="apple-touch-icon" href="%sicons/apple-touch-icon.png">' . "\n", WRA_A );
		}

		echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
		echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400..800;1,400&#038;display=swap">' . "\n";

		$pre = wra_meta( 'preload' );
		if ( $pre ) {
			$srcset = '';
			if ( ! empty( $pre['srcset'] ) ) {
				$srcset = preg_replace( '/(^|(?<=[\s,]))(?=[a-z])/', WRA_A, $pre['srcset'] );
				$srcset = sprintf( ' imagesrcset="%s" imagesizes="%s"', esc_attr( $srcset ), esc_attr( $pre['sizes'] ) );
			}
			printf( '<link rel="preload" as="image" href="%s"%s fetchpriority="high">' . "\n", esc_url( WRA_A . $pre['href'] ), $srcset ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
		}
	},
	1
);

/* JSON-LD: the organisation on the home page, breadcrumbs everywhere else, NewsArticle on posts. */
function wra_org_node() {
	return array(
		'@type'           => 'NGO',
		'@id'             => home_url( '/#org' ),
		'name'            => 'Wisconsin Repeater Alliance, Inc.',
		'alternateName'   => array( 'WRA', 'WI-RA' ),
		'url'             => home_url( '/' ),
		'logo'            => WRA_A . 'img/brand/wra-logo-640.webp',
		'email'           => 'info@wi-ra.org',
		'address'         => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => '1148 N. Sunnyslope Dr',
			'addressLocality' => 'Mount Pleasant',
			'addressRegion'   => 'WI',
			'postalCode'      => '53406',
			'addressCountry'  => 'US',
		),
		'nonprofitStatus' => 'Nonprofit501c3',
		'taxID'           => '42-4481796',
		'areaServed'      => array( '@type' => 'State', 'name' => 'Wisconsin' ),
		'description'     => 'Fair, transparent and engineering-based frequency coordination for amateur radio repeater owners, radio clubs and operators across Wisconsin.',
	);
}

add_action(
	'wp_head',
	function () {
		if ( ! wra_is_ours() || is_404() || is_search() ) {
			return;
		}
		$graph = array();
		if ( is_front_page() ) {
			$graph[] = wra_org_node();
		} else {
			$trail = array( array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url( '/' ) ) );
			$pos   = 2;
			foreach ( (array) wra_meta( 'crumbs', array() ) as $crumb ) {
				$trail[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => html_entity_decode( wp_strip_all_tags( $crumb[0] ), ENT_QUOTES ),
					'item'     => $crumb[1] ? home_url( $crumb[1] ) : wra_canonical(),
				);
			}
			$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $trail );
		}
		$article = wra_meta( 'article' );
		if ( $article ) {
			$graph[] = array_filter(
				array(
					'@type'            => 'NewsArticle',
					'headline'         => wp_strip_all_tags( get_the_title() ),
					'datePublished'    => $article['date'],
					'dateModified'     => get_the_modified_date( 'Y-m-d' ),
					'author'           => $article['author'] ? array( '@type' => 'Person', 'name' => $article['author'] ) : array( '@id' => home_url( '/#org' ) ),
					'publisher'        => array( '@id' => home_url( '/#org' ) ),
					'mainEntityOfPage' => wra_canonical(),
					'image'            => wra_meta( 'image' ),
				)
			);
			$graph[] = wra_org_node();
		}
		$data = array( '@context' => 'https://schema.org', '@graph' => $graph );
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	},
	99
);
