<?php
/**
 * Radio Clubs & Sponsors: the directory on the page at /clubs/
 * (wp-admin → Clubs & Sponsors → Add New Listing).
 *
 * One record per club or sponsor (a "listing"):
 *   Name          the post title (required to show on the site)
 *   Logo          the featured image (optional; the theme shows the WRA placeholder without one)
 *   Website       meta _wra_club_url (optional; no link on the card without one)
 *   Shown/hidden  the post status: Published shows in the directory, Draft hides it
 *
 * The theme draws the cards from wra_site_clubs(); the records live here so they
 * survive a theme change.
 */
defined( 'ABSPATH' ) || exit;

function wra_site_register_club_type() {
	register_post_type(
		'wra_club',
		array(
			'labels'              => array(
				'name'                   => 'Clubs & Sponsors',
				'singular_name'          => 'Listing',
				'name_admin_bar'         => 'Club or sponsor',
				'menu_name'              => 'Clubs & Sponsors',
				'all_items'              => 'All Listings',
				'add_new'                => 'Add New Listing',
				'add_new_item'           => 'Add New Listing',
				'edit_item'              => 'Edit Listing',
				'new_item'               => 'New Listing',
				'search_items'           => 'Search Listings',
				'not_found'              => 'No listings yet. Click "Add New Listing" to add the first club or sponsor.',
				'not_found_in_trash'     => 'No listings in the Trash.',
				'featured_image'         => 'Logo',
				'set_featured_image'     => 'Set logo',
				'remove_featured_image'  => 'Remove logo',
				'use_featured_image'     => 'Use as logo',
				'item_published'         => 'Listing published.',
				'item_updated'           => 'Listing updated.',
				'item_reverted_to_draft' => 'Listing hidden from the directory.',
			),
			'description'         => 'Amateur radio clubs and sponsors listed on the Radio Clubs & Sponsors page.',
			// Records only: no page of their own on the site, so nothing to index or preview.
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false, // the classic edit screen: name, logo, website, Publish
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-groups',
			'supports'            => array( 'title', 'thumbnail' ),
			// Editors and administrators manage listings (the same people who manage pages).
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
		)
	);
	register_post_meta(
		'wra_club',
		'_wra_club_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'wra_site_club_clean_url',
			'auth_callback'     => function () {
				return current_user_can( 'edit_pages' );
			},
		)
	);
}
add_action( 'init', 'wra_site_register_club_type' );

/* The logo box needs featured images; the WRA theme supports them already, any other theme may not. */
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'post-thumbnails', array( 'wra_club' ) );
	},
	99
);

/**
 * A website address as typed into the form, made safe: "www.example.org" becomes
 * "https://www.example.org". Returns '' for anything that is not an http(s) address.
 */
function wra_site_club_clean_url( $raw ) {
	$url = trim( (string) $raw );
	if ( '' === $url || preg_match( '/\s/', $url ) ) {
		return '';
	}
	if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ) {
		$url = 'https://' . ltrim( $url, '/' );
	}
	$url  = esc_url_raw( $url, array( 'http', 'https' ) );
	$host = $url ? (string) wp_parse_url( $url, PHP_URL_HOST ) : '';
	return ( $host && false !== strpos( $host, '.' ) ) ? $url : '';
}

/**
 * Public API for the theme: the listings shown on the Radio Clubs & Sponsors page, A to Z by name.
 * Each item: id, name (plain text), url ('' when the club has no website),
 * logo (attachment ID, 0 when none).
 */
function wra_site_clubs() {
	$q = new WP_Query(
		array(
			'post_type'           => 'wra_club',
			'post_status'         => 'publish',
			'posts_per_page'      => -1,
			'orderby'             => 'title',
			'order'               => 'ASC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
	update_post_thumbnail_cache( $q );
	$clubs = array();
	foreach ( $q->posts as $p ) {
		$name = trim( wp_strip_all_tags( html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ) ) );
		if ( '' === $name ) {
			continue;
		}
		$clubs[] = array(
			'id'   => (int) $p->ID,
			'name' => $name,
			'url'  => (string) get_post_meta( $p->ID, '_wra_club_url', true ),
			'logo' => (int) get_post_thumbnail_id( $p ),
		);
	}
	// Sorted here rather than by the database, so the order is the same on any server:
	// case-insensitive, with numbers in number order ("2 Meter Club" before "10-70 Club").
	usort(
		$clubs,
		function ( $a, $b ) {
			return strnatcasecmp( $a['name'], $b['name'] );
		}
	);
	return $clubs;
}

/** The directory page's address (/clubs/). */
function wra_site_clubs_page_url() {
	$page = get_page_by_path( 'clubs' );
	return ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : home_url( '/clubs/' );
}

/**
 * The page the theme draws the directory on (address /clubs/, titled Radio Clubs &
 * Sponsors). Created once, when the plugin is activated or updated to 1.1.0, if
 * there is no page with that address.
 */
function wra_site_ensure_clubs_page() {
	$page = get_page_by_path( 'clubs', OBJECT, 'page' ); // a page in the Trash has a "__trashed" address, so is not found
	if ( $page ) {
		return (int) $page->ID;
	}
	return (int) wp_insert_post(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => 'Radio Clubs & Sponsors',
			'post_name'      => 'clubs',
			'post_content'   => '',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		)
	);
}

/** Show or hide one listing. Returns a notice key: shown, hidden, noname or denied. */
function wra_site_club_set_visibility( $post, $to ) {
	$post = get_post( $post );
	if ( ! $post || 'wra_club' !== $post->post_type || 'trash' === $post->post_status ) {
		return 'denied';
	}
	if ( 'show' === $to ) {
		if ( ! current_user_can( 'publish_post', $post->ID ) ) {
			return 'denied';
		}
		if ( '' === trim( wp_strip_all_tags( $post->post_title ) ) ) {
			return 'noname';
		}
		if ( 'publish' !== $post->post_status ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ) );
		}
		return 'shown';
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return 'denied';
	}
	if ( 'draft' !== $post->post_status ) {
		wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ) );
	}
	return 'hidden';
}

/* ---------------------------------------------------------------------------
 * The edit screen: Name, Logo, Website address, then Publish.
 * ------------------------------------------------------------------------- */

add_filter(
	'enter_title_here',
	function ( $text, $post ) {
		return 'wra_club' === $post->post_type ? 'Club or sponsor name' : $text;
	},
	10,
	2
);

add_action(
	'add_meta_boxes_wra_club',
	function () {
		// The logo goes under the name, in the order the team fills the form in.
		remove_meta_box( 'postimagediv', 'wra_club', 'side' );
		add_meta_box( 'postimagediv', 'Logo', 'post_thumbnail_meta_box', 'wra_club', 'normal', 'high' );
		add_meta_box( 'wra-club-website', 'Website address', 'wra_site_club_website_box', 'wra_club', 'normal', 'high' );
	}
);

function wra_site_club_website_box( $post ) {
	wp_nonce_field( 'wra_club_save', 'wra_club_nonce' );
	$url = (string) get_post_meta( $post->ID, '_wra_club_url', true );
	printf(
		'<p><label class="screen-reader-text" for="wra-club-url">Website address</label><input type="text" id="wra-club-url" class="large-text" name="wra_club_url" value="%s" placeholder="www.example.org" inputmode="url" autocomplete="off" spellcheck="false"></p>',
		esc_attr( $url )
	);
	echo '<p class="description">The club’s or sponsor’s own website, for example www.example.org. The card’s “Visit website” link opens it in a new tab. Leave empty if there is no website: the card then shows no link.</p>';
	if ( $url ) {
		printf( '<p><a href="%s" target="_blank" rel="noopener">Check this address<span class="screen-reader-text"> (opens in a new tab)</span> ↗</a></p>', esc_url( $url ) );
	}
}

add_filter(
	'admin_post_thumbnail_html',
	function ( $html, $post_id ) {
		if ( 'wra_club' !== get_post_type( $post_id ) ) {
			return $html;
		}
		return $html . '<p class="description">A PNG or JPG logo, at least 400 pixels wide, on a white or transparent background. Square and wide logos both fit. Leave empty and the card shows the standard WRA placeholder.</p>';
	},
	10,
	2
);

/* Inside the Publish box: what Publish and Draft mean for a listing. */
add_action(
	'post_submitbox_misc_actions',
	function ( $post ) {
		if ( 'wra_club' !== $post->post_type ) {
			return;
		}
		printf(
			'<div class="misc-pub-section wra-club-pubnote">Published listings show on the <a href="%s" target="_blank" rel="noopener">Radio Clubs &amp; Sponsors page<span class="screen-reader-text"> (opens in a new tab)</span></a>, A to Z. To hide a listing without deleting it, set its Status to Draft.</div>',
			esc_url( wra_site_clubs_page_url() )
		);
	}
);

/* Save the website address. */
add_action(
	'save_post_wra_club',
	function ( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['wra_club_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wra_club_nonce'] ) ), 'wra_club_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Not sanitize_text_field: it would strip %-encoded characters from the address. wra_site_club_clean_url() is the sanitiser.
		$raw = isset( $_POST['wra_club_url'] ) ? trim( wp_unslash( $_POST['wra_club_url'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$url = wra_site_club_clean_url( $raw );
		if ( '' !== $raw && '' === $url ) {
			$GLOBALS['wra_club_notice'] = 'badurl'; // keep the address it had, and say why
			return;
		}
		if ( '' === $url ) {
			delete_post_meta( $post_id, '_wra_club_url' );
		} else {
			update_post_meta( $post_id, '_wra_club_url', $url );
		}
	}
);

/* A listing cannot go on the site without a name: it is kept as a draft instead. */
add_filter(
	'wp_insert_post_data',
	function ( $data ) {
		if ( 'wra_club' === $data['post_type'] && in_array( $data['post_status'], array( 'publish', 'future', 'private', 'pending' ), true )
			&& '' === trim( wp_strip_all_tags( wp_unslash( $data['post_title'] ) ) ) ) {
			$data['post_status']        = 'draft';
			$GLOBALS['wra_club_notice'] = 'noname';
		}
		return $data;
	}
);

add_filter(
	'redirect_post_location',
	function ( $location, $post_id ) {
		if ( 'wra_club' !== get_post_type( $post_id ) || empty( $GLOBALS['wra_club_notice'] ) ) {
			return $location;
		}
		$location = add_query_arg( 'wra_club_notice', $GLOBALS['wra_club_notice'], $location );
		// "Listing published." would be wrong when the listing was kept as a draft.
		return 'noname' === $GLOBALS['wra_club_notice'] ? remove_query_arg( 'message', $location ) : $location;
	},
	10,
	2
);

add_filter(
	'post_updated_messages',
	function ( $messages ) {
		$view = sprintf( ' <a href="%s" target="_blank" rel="noopener">View the Radio Clubs &amp; Sponsors page<span class="screen-reader-text"> (opens in a new tab)</span></a>', esc_url( wra_site_clubs_page_url() ) );
		$messages['wra_club'] = array(
			0  => '',
			1  => 'Listing updated.' . $view,
			2  => '',
			3  => '',
			4  => 'Listing updated.',
			5  => '',
			6  => 'Listing published. It now shows in the directory.' . $view,
			7  => 'Listing saved.',
			8  => 'Listing submitted for review. It shows in the directory once an editor publishes it.',
			9  => 'Listing scheduled.',
			10 => 'Draft saved. This listing is hidden from the directory until it is published.',
		);
		return $messages;
	}
);

/* Name required in the browser too, so the form says so before saving. */
add_action(
	'admin_footer-post.php',
	'wra_site_club_title_required'
);
add_action(
	'admin_footer-post-new.php',
	'wra_site_club_title_required'
);
function wra_site_club_title_required() {
	if ( 'wra_club' !== get_current_screen()->post_type ) {
		return;
	}
	echo "<script>(function(){var t=document.getElementById('title');if(t){t.required=true;t.setAttribute('aria-required','true');}})();</script>\n";
}

/* ---------------------------------------------------------------------------
 * The list of listings: A to Z, logo and website at a glance, one-click Show/Hide.
 * ------------------------------------------------------------------------- */

add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() && $q->is_main_query() && 'wra_club' === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
			$q->set( 'orderby', 'title' );
			$q->set( 'order', 'ASC' );
		}
	}
);

add_filter(
	'manage_wra_club_posts_columns',
	function ( $cols ) {
		return array(
			'cb'        => isset( $cols['cb'] ) ? $cols['cb'] : '<input type="checkbox">',
			'wra_logo'  => 'Logo',
			'title'     => 'Name',
			'wra_url'   => 'Website',
			'wra_shown' => 'In the directory',
		);
	}
);

add_action(
	'manage_wra_club_posts_custom_column',
	function ( $col, $id ) {
		if ( 'wra_logo' === $col ) {
			echo has_post_thumbnail( $id )
				? get_the_post_thumbnail( $id, 'medium', array( 'alt' => '' ) )
				: '<span class="wra-club-nologo">WRA placeholder</span>';
		} elseif ( 'wra_url' === $col ) {
			$url = (string) get_post_meta( $id, '_wra_club_url', true );
			if ( $url ) {
				printf( '<a href="%s" target="_blank" rel="noopener">%s<span class="screen-reader-text"> (opens in a new tab)</span></a>', esc_url( $url ), esc_html( preg_replace( '#^https?://(www\.)?#i', '', untrailingslashit( $url ) ) ) );
			} else {
				echo '<span aria-hidden="true">—</span><span class="screen-reader-text">No website</span>';
			}
		} elseif ( 'wra_shown' === $col ) {
			echo 'publish' === get_post_status( $id )
				? '<span class="wra-club-state is-shown">Shown</span>'
				: '<span class="wra-club-state">Hidden</span>';
		}
	},
	10,
	2
);

/* No "All dates" filter: when a listing was added means nothing on the site. */
add_filter(
	'disable_months_dropdown',
	function ( $disable, $post_type ) {
		return 'wra_club' === $post_type ? true : $disable;
	},
	10,
	2
);

/* "Published"/"Draft" read as "Shown"/"Hidden" here, which is what they mean for a listing. */
add_filter(
	'views_edit-wra_club',
	function ( $views ) {
		if ( isset( $views['publish'] ) ) {
			$views['publish'] = preg_replace( '/>Published(?= <span)/', '>Shown', $views['publish'] );
		}
		if ( isset( $views['draft'] ) ) {
			$views['draft'] = preg_replace( '/>Drafts?(?= <span)/', '>Hidden', $views['draft'] );
		}
		return $views;
	}
);
add_filter(
	'display_post_states',
	function ( $states, $post ) {
		if ( 'wra_club' === $post->post_type && isset( $states['draft'] ) ) {
			$states['draft'] = 'Hidden';
		}
		return $states;
	},
	20,
	2
);

add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'wra_club' !== $post->post_type ) {
			return $actions;
		}
		// Quick Edit offers a slug, a date and a password, none of which mean anything for a listing.
		unset( $actions['inline hide-if-no-js'] );
		if ( 'trash' === $post->post_status ) {
			return $actions;
		}
		$to  = 'publish' === $post->post_status ? 'hide' : 'show';
		$can = 'show' === $to ? current_user_can( 'publish_post', $post->ID ) : current_user_can( 'edit_post', $post->ID );
		if ( ! $can ) {
			return $actions;
		}
		$url   = wp_nonce_url( admin_url( 'admin-post.php?action=wra_club_visibility&to=' . $to . '&post=' . $post->ID ), 'wra_club_visibility_' . $post->ID );
		$label = 'show' === $to ? 'Show in directory' : 'Hide from directory';
		$name  = wp_strip_all_tags( $post->post_title );
		$link  = array( 'wra_visibility' => sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( $url ), esc_attr( $label . ( $name ? ': ' . $name : '' ) ), esc_html( $label ) ) );
		return array_slice( $actions, 0, 1, true ) + $link + array_slice( $actions, 1, null, true );
	},
	10,
	2
);

add_action(
	'admin_post_wra_club_visibility',
	function () {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'wra_club_visibility_' . $id );
		$to     = ( isset( $_GET['to'] ) && 'show' === $_GET['to'] ) ? 'show' : 'hide';
		$result = wra_site_club_set_visibility( $id, $to );
		$back   = wp_get_referer();
		$back   = ( $back && false !== strpos( $back, 'edit.php' ) ) ? $back : admin_url( 'edit.php?post_type=wra_club' );
		$back   = remove_query_arg( array( 'wra_club_notice', 'wra_club_n', 'wra_club_skipped' ), $back );
		wp_safe_redirect( add_query_arg( array( 'wra_club_notice' => $result, 'wra_club_n' => 1 ), $back ) );
		exit;
	}
);

add_filter(
	'bulk_actions-edit-wra_club',
	function ( $actions ) {
		unset( $actions['edit'] ); // bulk Edit has the same meaningless fields as Quick Edit
		return array(
			'wra_show' => 'Show in directory',
			'wra_hide' => 'Hide from directory',
		) + $actions;
	}
);

add_filter(
	'handle_bulk_actions-edit-wra_club',
	function ( $redirect, $action, $ids ) {
		if ( ! in_array( $action, array( 'wra_show', 'wra_hide' ), true ) ) {
			return $redirect;
		}
		$to      = 'wra_show' === $action ? 'show' : 'hide';
		$done    = 0;
		$skipped = 0;
		foreach ( (array) $ids as $id ) {
			in_array( wra_site_club_set_visibility( (int) $id, $to ), array( 'shown', 'hidden' ), true ) ? $done++ : $skipped++;
		}
		return add_query_arg(
			array(
				'wra_club_notice'  => 'show' === $to ? 'shown' : 'hidden',
				'wra_club_n'       => $done,
				'wra_club_skipped' => $skipped,
			),
			remove_query_arg( array( 'wra_club_notice', 'wra_club_n', 'wra_club_skipped' ), $redirect )
		);
	},
	10,
	3
);

/* Notices on the Clubs & Sponsors screens. */
add_action(
	'admin_notices',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'wra_club' !== $screen->post_type ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only
		$notice  = isset( $_GET['wra_club_notice'] ) ? sanitize_key( $_GET['wra_club_notice'] ) : '';
		$n       = isset( $_GET['wra_club_n'] ) ? absint( $_GET['wra_club_n'] ) : 0;
		$skipped = isset( $_GET['wra_club_skipped'] ) ? absint( $_GET['wra_club_skipped'] ) : 0;
		// phpcs:enable
		$text = array(
			'noname' => array( 'error', 'Enter the name. The listing was saved as a draft, and stays hidden from the directory until it has a name.' ),
			'badurl' => array( 'error', 'That website address doesn’t look right, so it wasn’t saved. Enter it like www.example.org.' ),
			'denied' => array( 'error', 'You don’t have permission to change that listing.' ),
			'shown'  => array( 'success', 1 === $n ? 'The listing now shows in the directory.' : sprintf( '%d listings now show in the directory.', $n ) ),
			'hidden' => array( 'success', 1 === $n ? 'The listing is now hidden from the directory. Nothing was deleted.' : sprintf( '%d listings are now hidden from the directory. Nothing was deleted.', $n ) ),
		);
		if ( isset( $text[ $notice ] ) ) {
			$msg = $text[ $notice ][1];
			if ( 'shown' === $notice && $skipped ) {
				$msg .= sprintf( ' %d could not be shown because they have no name.', $skipped );
			}
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $text[ $notice ][0] ), esc_html( $msg ) );
		}
		if ( 'edit' === $screen->base ) {
			printf(
				'<div class="notice notice-info wra-club-help"><p>Listings marked <strong>Shown</strong> appear on the <a href="%s" target="_blank" rel="noopener">Radio Clubs &amp; Sponsors page<span class="screen-reader-text"> (opens in a new tab)</span></a>, A to Z. To take a club or sponsor off the page without deleting it, hover over its name and click <strong>Hide from directory</strong>.</p></div>',
				esc_url( wra_site_clubs_page_url() )
			);
		}
	}
);

/* Admin styles for the Clubs & Sponsors screens only. */
add_action(
	'admin_head',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || 'wra_club' !== $screen->post_type ) {
			return;
		}
		echo '<style>
.post-type-wra_club #visibility, .post-type-wra_club .misc-pub-curtime { display: none; }
.post-type-wra_club #postimagediv .inside img { width: auto; max-width: 320px; max-height: 200px; padding: 12px; background: #fff; border: 1px solid #dcdcde; box-sizing: content-box; }
.wra-club-pubnote { line-height: 1.5; }
.column-wra_logo { width: 96px; }
.column-wra_logo img { display: block; width: 80px; height: 52px; padding: 4px; object-fit: contain; background: #fff; border: 1px solid #dcdcde; box-sizing: border-box; }
.column-wra_shown { width: 150px; }
.column-wra_url { width: 28%; }
.wra-club-nologo { color: #646970; font-size: 12px; }
.wra-club-state { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
.wra-club-state::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #8c8f94; }
.wra-club-state.is-shown { color: #00701a; }
.wra-club-state.is-shown::before { background: #00a32a; }
</style>' . "\n";
	}
);
