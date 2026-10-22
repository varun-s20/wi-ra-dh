<?php
/**
 * Radio Clubs & Sponsors page helpers. The records live in the WRA Site plugin (wp-admin → Clubs & Sponsors);
 * these sort and page them (12 to a page, A–Z or Z–A across the whole directory) and render the design's
 * card, switch and pager markup (static design: clubs.html, built by _build/clubs_static.py).
 *
 * Addresses: /clubs/, /clubs/page/2/, and ?order=za for Z–A (also /clubs/page/2/?order=za).
 */
defined( 'ABSPATH' ) || exit;

/** Listings to a page. */
function wra_clubs_per_page() {
	return max( 1, (int) apply_filters( 'wra_clubs_per_page', 12 ) );
}

/** All published listings, A to Z (none if the WRA Site plugin is off). */
function wra_clubs() {
	return function_exists( 'wra_site_clubs' ) ? wra_site_clubs() : array();
}

/** The sort order asked for: 'za' (Z to A) or 'az' (the default). */
function wra_clubs_order() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only, nothing is changed
	return ( isset( $_GET['order'] ) && 'za' === sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'za' : 'az';
}

/** Address of one page of the directory, in A–Z ('az') or Z–A ('za') order. */
function wra_clubs_url( $page = 1, $order = 'az', $anchor = true ) {
	$url = home_url( '/clubs/' . ( $page > 1 ? 'page/' . (int) $page . '/' : '' ) );
	if ( 'za' === $order ) {
		$url = add_query_arg( 'order', 'za', $url );
	}
	return $url . ( $anchor ? '#directory' : '' );
}

/**
 * The directory as it is to be shown: the whole directory sorted, then one page of it, so Z–A is
 * the exact mirror of A–Z and the page links never skip or repeat a listing.
 * Returns order, page (clamped to the real pages), requested (as asked for), pages, per, total,
 * from and to (1-based positions of this page's first and last listing) and items.
 */
function wra_clubs_view() {
	static $view = null;
	if ( null === $view ) {
		$all   = wra_clubs();
		$order = wra_clubs_order();
		if ( 'za' === $order ) {
			$all = array_reverse( $all );
		}
		$per       = wra_clubs_per_page();
		$total     = count( $all );
		$pages     = max( 1, (int) ceil( $total / $per ) );
		$requested = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$page      = min( $requested, $pages );
		$items     = array_slice( $all, ( $page - 1 ) * $per, $per );
		$view      = array(
			'order'     => $order,
			'page'      => $page,
			'requested' => $requested,
			'pages'     => $pages,
			'per'       => $per,
			'total'     => $total,
			'from'      => $items ? ( $page - 1 ) * $per + 1 : 0,
			'to'        => $items ? ( $page - 1 ) * $per + count( $items ) : 0,
			'items'     => $items,
		);
	}
	return $view;
}

/**
 * Page numbers to show: all of them up to 7, otherwise the first, the last and the neighbours of the
 * current page; null marks a gap (a gap of a single number shows the number instead).
 */
function wra_clubs_window( $cur, $last ) {
	if ( $last <= 7 ) {
		return range( 1, $last );
	}
	$keep = array();
	foreach ( array( 1, $last, $cur - 1, $cur, $cur + 1 ) as $n ) {
		if ( $n >= 1 && $n <= $last ) {
			$keep[ $n ] = $n;
		}
	}
	sort( $keep );
	$out  = array();
	$prev = 0;
	foreach ( $keep as $n ) {
		if ( $prev && 2 === $n - $prev ) {
			$out[] = $prev + 1;
		} elseif ( $prev && $n - $prev > 2 ) {
			$out[] = null;
		}
		$out[] = $n;
		$prev  = $n;
	}
	return $out;
}

/** The page links under the cards (nothing when everything fits on one page). */
function wra_clubs_pager( array $view ) {
	if ( $view['pages'] < 2 ) {
		return;
	}
	$page  = $view['page'];
	$pages = $view['pages'];
	$order = $view['order'];
	printf( '          <nav class="clubs-pager%s" aria-label="Directory pages">' . "\n", $pages <= 4 ? ' clubs-pager--few' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed strings
	if ( $page > 1 ) {
		printf(
			'            <a class="clubs-pager__step clubs-pager__step--prev" href="%s" rel="prev"><span aria-hidden="true">&larr;</span><span class="clubs-pager__word">Previous</span><span class="visually-hidden"> page</span></a>' . "\n",
			esc_url( wra_clubs_url( $page - 1, $order ) )
		);
	}
	echo '            <ol class="clubs-pager__pages" role="list">' . "\n";
	foreach ( wra_clubs_window( $page, $pages ) as $n ) {
		if ( null === $n ) {
			echo '              <li class="clubs-pager__gap" aria-hidden="true">&hellip;</li>' . "\n";
		} elseif ( $n === $page ) {
			printf( '              <li><span class="clubs-pager__num" aria-current="page"><span class="visually-hidden">Page </span>%d</span></li>' . "\n", (int) $n );
		} else {
			printf(
				'              <li><a class="clubs-pager__num" href="%s" aria-label="%s">%d</a></li>' . "\n",
				esc_url( wra_clubs_url( $n, $order ) ),
				esc_attr( 'Page ' . $n ),
				(int) $n
			);
		}
	}
	echo '            </ol>' . "\n";
	printf( '            <p class="clubs-pager__of">Page %d of %d</p>' . "\n", (int) $page, (int) $pages );
	if ( $page < $pages ) {
		printf(
			'            <a class="clubs-pager__step clubs-pager__step--next" href="%s" rel="next"><span class="clubs-pager__word">Next</span><span class="visually-hidden"> page</span><span aria-hidden="true">&rarr;</span></a>' . "\n",
			esc_url( wra_clubs_url( $page + 1, $order ) )
		);
	}
	echo '          </nav>' . "\n";
}

/** A listing's logo plate: its logo, or the WRA placeholder (the header logo in greyscale) when it has none or the file has gone. */
function wra_club_logo( array $club ) {
	$img = $club['logo'] ? wp_get_attachment_image(
		$club['logo'],
		'medium',
		false,
		array(
			'alt'     => '', // the club name sits right below it
			'loading' => 'lazy',
			'sizes'   => '(max-width: 560px) 96px, (max-width: 820px) 34vw, (max-width: 1100px) 22vw, 220px',
		)
	) : '';
	if ( $img ) {
		return '<div class="club__logo">' . $img . '</div>';
	}
	return sprintf(
		'<div class="club__logo club__logo--none"><img src="%1$simg/brand/wra-logo-nav-144.webp" srcset="%1$simg/brand/wra-logo-nav-144.webp 1x, %1$simg/brand/wra-logo-nav-216.webp 2x" width="151" height="144" alt="" loading="lazy"></div>',
		WRA_A
	);
}

/*
 * A page number beyond the last one (a bookmark from when there were more listings, or a typed
 * address) goes to the last page instead of showing an empty directory.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! is_page( 'clubs' ) ) {
			return;
		}
		$view = wra_clubs_view();
		if ( $view['requested'] > $view['pages'] ) {
			wp_safe_redirect( wra_clubs_url( $view['pages'], $view['order'], false ), 302 );
			exit;
		}
	},
	5
);

/* Page 2 onwards: its own title and address. Z–A views stay out of search results (they repeat the same listings). */
add_action(
	'wp_head',
	function () {
		if ( 'clubs' !== wra_meta( 'key' ) ) {
			return;
		}
		$view = wra_clubs_view();
		if ( $view['page'] > 1 ) {
			$GLOBALS['wra']['path'] = '/clubs/page/' . $view['page'] . '/';
			foreach ( array( 'title', 'og_title' ) as $field ) {
				if ( ! empty( $GLOBALS['wra'][ $field ] ) ) {
					$GLOBALS['wra'][ $field ] = preg_replace( '/ – /u', ' – Page ' . $view['page'] . ' – ', $GLOBALS['wra'][ $field ], 1 );
				}
			}
		}
		if ( 'za' === $view['order'] ) {
			$GLOBALS['wra']['noindex'] = true;
		}
	},
	0
);

/* WordPress's own canonical tag knows nothing of /page/N/ on a page: give it the directory page's address. */
add_filter(
	'get_canonical_url',
	function ( $url ) {
		if ( 'clubs' !== wra_meta( 'key' ) ) {
			return $url;
		}
		$view = wra_clubs_view();
		return 'za' === $view['order'] ? false : wra_clubs_url( $view['page'], 'az', false );
	}
);
