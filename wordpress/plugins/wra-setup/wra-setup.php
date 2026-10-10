<?php
/**
 * Plugin Name:       WRA One-time Setup
 * Description:       Switches wi-ra.org to the new design in one step: page templates, the new Resources and Terms pages, the approved News text and images, the site icon, and the theme. Shows the plan first, logs every change, and can undo them. Delete this plugin once the site is signed off.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Digital Heroes
 * License:           Proprietary, for wi-ra.org
 */
defined( 'ABSPATH' ) || exit;

define( 'WRA_SETUP_DIR', plugin_dir_path( __FILE__ ) );

/** Pages the design has a template for: slug => title used when the page has to be created. */
function wra_setup_pages() {
	return array(
		'about'                  => 'About WRA',
		'frequency-coordination' => 'What Is Frequency Coordination?',
		'repeater-coordination'  => 'Repeater Coordination',
		'arcs'                   => 'Amateur Radio Coordination System (ARCS)',
		'membership'             => 'Membership',
		'resources'              => 'Resources',
		'news'                   => 'News & Announcements',
		'contact'                => 'Contact Us',
		'privacy-policy'         => 'Privacy Policy',
		'terms-of-use'           => 'Terms of Use',
	);
}

/** Pages whose content moved elsewhere; their URLs redirect (WRA Site plugin). */
function wra_setup_retired_pages() {
	return array( 'faq', 'volunteer', 'membership-pay-dues' );
}

/** Earlier slugs of posts that are renamed. */
function wra_setup_old_slugs() {
	return array( 'wisconsin-association-of-repeaters-transition' => array( 'lorem-ipsum-dolor-sit-amet-consectetur-2' ) );
}

function wra_setup_posts() {
	return json_decode( file_get_contents( WRA_SETUP_DIR . 'content/posts.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- local file
}

function wra_setup_find_page( $slug ) {
	$p = get_page_by_path( $slug, OBJECT, 'page' );
	return ( $p && 'trash' !== $p->post_status ) ? $p : null;
}

function wra_setup_find_post( array $spec ) {
	$slugs = array_merge( array( $spec['slug'] ), isset( wra_setup_old_slugs()[ $spec['slug'] ] ) ? wra_setup_old_slugs()[ $spec['slug'] ] : array() );
	foreach ( $slugs as $slug ) {
		$found = get_posts( array( 'name' => $slug, 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ) );
		if ( $found ) {
			return $found[0];
		}
	}
	$found = get_posts( array( 'title' => $spec['title'], 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ) );
	return $found ? $found[0] : null;
}

function wra_setup_home_page() {
	$id = (int) get_option( 'page_on_front' );
	if ( $id && get_post( $id ) && 'page' === get_post_type( $id ) ) {
		return get_post( $id );
	}
	return wra_setup_find_page( 'home' );
}

/** WordPress's own sample content on a fresh install: [slug, type, text that proves it is untouched]. */
function wra_setup_sample_content() {
	return array(
		array( 'hello-world', 'post', 'Welcome to WordPress' ),
		array( 'sample-page', 'page', 'This is an example page' ),
	);
}

/** Site identity the design expects. */
function wra_setup_identity() {
	return array(
		'blogname'        => 'Wisconsin Repeater Alliance',
		'blogdescription' => 'Coordinating Wisconsin Amateur Radio',
		'timezone_string' => 'America/Chicago',
	);
}

/** Untouched sample posts/pages (never anything a person has edited). */
function wra_setup_find_samples() {
	$found = array();
	foreach ( wra_setup_sample_content() as $s ) {
		$p = get_page_by_path( $s[0], OBJECT, $s[1] );
		if ( $p && 'trash' !== $p->post_status && false !== strpos( $p->post_content, $s[2] ) ) {
			$found[] = $p;
		}
	}
	return $found;
}

/** What the setup will do, as human-readable lines (also the dry run). */
function wra_setup_plan() {
	$plan = array();
	$home = wra_setup_home_page();
	$plan[] = $home ? sprintf( 'Home page: keep "%s" as the front page, on the Default template.', $home->post_title ) : 'Home page: create "Home" and make it the front page.';
	foreach ( wra_setup_pages() as $slug => $title ) {
		$p = wra_setup_find_page( $slug );
		if ( ! $p ) {
			$plan[] = sprintf( '/%s/: create the page "%s".', $slug, $title );
		} else {
			$tpl    = get_post_meta( $p->ID, '_wp_page_template', true );
			$plan[] = sprintf( '/%s/: use the design template (currently %s, template "%s").', $slug, $p->post_status, $tpl ? $tpl : 'default' );
		}
	}
	foreach ( wra_setup_retired_pages() as $slug ) {
		$p = wra_setup_find_page( $slug );
		if ( $p && 'publish' === $p->post_status ) {
			$plan[] = sprintf( '/%s/: set "%s" to Draft (the address redirects to its new place).', $slug, $p->post_title );
		}
	}
	foreach ( wra_setup_posts() as $spec ) {
		$p = wra_setup_find_post( $spec );
		$plan[] = $p
			? sprintf( 'News post "%s": replace its title and text with the approved version, set its summary, byline and image%s.', $spec['title'], $p->post_name !== $spec['slug'] ? ', and rename its address to /' . $spec['slug'] . '/ (the old address keeps working)' : '' )
			: sprintf( 'News post "%s": create it.', $spec['title'] );
	}
	if ( ! term_exists( 'news', 'category' ) ) {
		$plan[] = 'Category: create "News" and file the News posts in it.';
	}
	foreach ( wra_setup_find_samples() as $p ) {
		$plan[] = sprintf( 'WordPress sample %s "%s": move to the Bin (it is the untouched default).', $p->post_type, $p->post_title );
	}
	foreach ( wra_setup_identity() as $opt => $val ) {
		if ( get_option( $opt ) !== $val ) {
			$plan[] = sprintf( 'Settings: %s → "%s" (currently "%s").', array( 'blogname' => 'Site title', 'blogdescription' => 'Tagline', 'timezone_string' => 'Time zone' )[ $opt ], $val, get_option( $opt ) );
		}
	}
	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		$plan[] = 'Permalinks: set to "Post name".';
	}
	$plan[] = 'Site icon: the new WRA icon (Appearance → Customize → Site Identity).';
	$plan[] = sprintf( 'Theme: activate "Wisconsin Repeater Alliance" (currently "%s").', wp_get_theme()->get( 'Name' ) );
	return $plan;
}

/** Copy a theme asset into the Media Library once; returns the attachment ID. */
function wra_setup_media( $rel, $alt ) {
	$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'meta_key' => '_wra_asset', 'meta_value' => $rel, 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $existing ) {
		return (int) $existing[0];
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$src = get_theme_root( 'wra-child' ) . '/wra-child/assets/' . $rel;
	if ( ! file_exists( $src ) ) {
		return 0;
	}
	$tmp = wp_tempnam( basename( $src ) );
	copy( $src, $tmp );
	$id = media_handle_sideload( array( 'name' => basename( $src ), 'tmp_name' => $tmp ), 0 );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	update_post_meta( $id, '_wra_asset', $rel );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return (int) $id;
}

/** Run the setup. Every change is recorded in the wra_setup_log option so it can be undone. */
function wra_setup_run() {
	if ( get_option( 'wra_setup_log' ) ) {
		return new WP_Error( 'already', 'Setup has already run. Undo it first if you need to run it again.' );
	}
	if ( ! wp_get_theme( 'wra-child' )->exists() || ! wp_get_theme( 'hello-elementor' )->exists() ) {
		return new WP_Error( 'theme', 'Install the "Wisconsin Repeater Alliance" theme (wra-child) first; it needs Hello Elementor installed too.' );
	}
	kses_remove_filters(); // the approved post text keeps its markup
	$log = array( 'pages' => array(), 'created' => array(), 'posts' => array(), 'options' => array(), 'trashed' => array(), 'identity' => array(), 'term' => 0, 'time' => time() );

	// Home + design pages
	$home = wra_setup_home_page();
	if ( ! $home ) {
		$hid               = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home', 'post_name' => 'home' ) );
		$log['created'][]  = $hid;
		$home              = get_post( $hid );
	}
	$pages = array( $home );
	foreach ( wra_setup_pages() as $slug => $title ) {
		$p = wra_setup_find_page( $slug );
		if ( ! $p ) {
			$id               = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ) );
			$log['created'][] = $id;
			continue;
		}
		$pages[] = $p;
	}
	foreach ( $pages as $p ) {
		$log['pages'][ $p->ID ] = array( 'status' => $p->post_status, 'template' => get_post_meta( $p->ID, '_wp_page_template', true ) );
		update_post_meta( $p->ID, '_wp_page_template', 'default' );
		if ( 'publish' !== $p->post_status ) {
			wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'publish' ) );
		}
	}
	foreach ( wra_setup_retired_pages() as $slug ) {
		$p = wra_setup_find_page( $slug );
		if ( $p && 'publish' === $p->post_status ) {
			$log['pages'][ $p->ID ] = array( 'status' => 'publish', 'template' => get_post_meta( $p->ID, '_wp_page_template', true ) );
			wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'draft' ) );
		}
	}

	// Reading + permalinks
	foreach ( array( 'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure', 'site_icon' ) as $opt ) {
		$log['options'][ $opt ] = get_option( $opt );
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home->ID );
	update_option( 'page_for_posts', 0 );
	if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	// News category
	$term = term_exists( 'news', 'category' );
	if ( ! $term ) {
		$term        = wp_insert_term( 'News', 'category', array( 'slug' => 'news' ) );
		$log['term'] = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	}
	$news_cat = is_array( $term ) ? (int) $term['term_id'] : (int) $term;

	// News posts
	foreach ( wra_setup_posts() as $spec ) {
		$p     = wra_setup_find_post( $spec );
		$thumb = wra_setup_media( $spec['image'], $spec['image_alt'] );
		$data  = array(
			'post_type'    => 'post',
			'post_title'   => $spec['title'],
			'post_content' => $spec['content'],
			'post_excerpt' => $spec['excerpt'],
			'post_name'    => $spec['slug'],
		);
		if ( $p ) {
			$log['posts'][ $p->ID ] = array(
				'title'     => $p->post_title,
				'content'   => $p->post_content,
				'excerpt'   => $p->post_excerpt,
				'slug'      => $p->post_name,
				'thumbnail' => (int) get_post_thumbnail_id( $p ),
				'byline'    => get_post_meta( $p->ID, '_wra_byline', true ),
				'elementor' => get_post_meta( $p->ID, '_elementor_edit_mode', true ),
				'cats'      => wp_get_post_categories( $p->ID ),
			);
			$data['ID'] = $p->ID;
			wp_update_post( wp_slash( $data ) );
			$id = $p->ID;
			// Elementor-built posts: park the builder data so the editor content is what shows.
			if ( get_post_meta( $id, '_elementor_edit_mode', true ) ) {
				update_post_meta( $id, '_wra_parked_elementor_edit_mode', get_post_meta( $id, '_elementor_edit_mode', true ) );
				delete_post_meta( $id, '_elementor_edit_mode' );
			}
		} else {
			$data['post_status'] = 'publish';
			$data['post_title']  = $spec['title'];
			$data['post_date']   = isset( $spec['datetime'] ) ? $spec['datetime'] : $spec['date'] . ' 12:00:00';
			$id                  = wp_insert_post( wp_slash( $data ) );
			$log['created'][]    = $id;
		}
		if ( $thumb ) {
			set_post_thumbnail( $id, $thumb );
		}
		if ( $news_cat ) {
			wp_set_post_categories( $id, array( $news_cat ) );
		}
		if ( $spec['byline'] ) {
			update_post_meta( $id, '_wra_byline', $spec['byline'] );
		} else {
			delete_post_meta( $id, '_wra_byline' );
		}
	}

	// Fresh installs: WordPress's untouched sample post and page go to the Bin
	foreach ( wra_setup_find_samples() as $p ) {
		wp_trash_post( $p->ID );
		$log['trashed'][] = $p->ID;
	}

	// Site title, tagline, time zone
	foreach ( wra_setup_identity() as $opt => $val ) {
		if ( get_option( $opt ) !== $val ) {
			$log['identity'][ $opt ] = get_option( $opt );
			update_option( $opt, $val );
		}
	}

	// Site icon
	$icon = wra_setup_media( 'icons/icon-512.png', 'Wisconsin Repeater Alliance' );
	if ( $icon ) {
		update_option( 'site_icon', $icon );
	}

	// Theme last
	$log['options']['stylesheet'] = get_option( 'stylesheet' );
	$log['options']['template']   = get_option( 'template' );
	switch_theme( 'wra-child' );

	update_option( 'wra_setup_log', $log, false );
	flush_rewrite_rules();
	kses_init_filters();
	return true;
}

/** Put everything back the way it was before wra_setup_run(). */
function wra_setup_undo() {
	$log = get_option( 'wra_setup_log' );
	if ( ! $log ) {
		return new WP_Error( 'none', 'Nothing to undo.' );
	}
	kses_remove_filters();
	switch_theme( $log['options']['stylesheet'] );
	foreach ( $log['pages'] as $id => $was ) {
		update_post_meta( $id, '_wp_page_template', $was['template'] ? $was['template'] : 'default' );
		wp_update_post( array( 'ID' => $id, 'post_status' => $was['status'] ) );
	}
	foreach ( $log['posts'] as $id => $was ) {
		wp_update_post( wp_slash( array( 'ID' => $id, 'post_title' => $was['title'], 'post_content' => $was['content'], 'post_excerpt' => $was['excerpt'], 'post_name' => $was['slug'] ) ) );
		$was['thumbnail'] ? set_post_thumbnail( $id, $was['thumbnail'] ) : delete_post_thumbnail( $id );
		$was['byline'] ? update_post_meta( $id, '_wra_byline', $was['byline'] ) : delete_post_meta( $id, '_wra_byline' );
		if ( isset( $was['cats'] ) ) {
			wp_set_post_categories( $id, $was['cats'] );
		}
		$parked = get_post_meta( $id, '_wra_parked_elementor_edit_mode', true );
		if ( $parked ) {
			update_post_meta( $id, '_elementor_edit_mode', $parked );
			delete_post_meta( $id, '_wra_parked_elementor_edit_mode' );
		}
	}
	foreach ( $log['created'] as $id ) {
		wp_trash_post( $id );
	}
	foreach ( (array) ( isset( $log['trashed'] ) ? $log['trashed'] : array() ) as $id ) {
		wp_untrash_post( $id );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	}
	foreach ( (array) ( isset( $log['identity'] ) ? $log['identity'] : array() ) as $opt => $val ) {
		update_option( $opt, $val );
	}
	if ( ! empty( $log['term'] ) ) {
		wp_delete_term( $log['term'], 'category' );
	}
	foreach ( array( 'show_on_front', 'page_on_front', 'page_for_posts', 'permalink_structure', 'site_icon' ) as $opt ) {
		update_option( $opt, $log['options'][ $opt ] );
	}
	delete_option( 'wra_setup_log' );
	flush_rewrite_rules();
	kses_init_filters();
	return true;
}

/* Tools → WRA one-time setup */
add_action(
	'admin_menu',
	function () {
		add_management_page( 'WRA one-time setup', 'WRA one-time setup', 'manage_options', 'wra-setup', 'wra_setup_screen' );
	}
);

function wra_setup_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$msg = '';
	if ( isset( $_POST['wra_setup_action'] ) && check_admin_referer( 'wra_setup' ) ) {
		$action = sanitize_key( $_POST['wra_setup_action'] );
		$result = 'run' === $action ? wra_setup_run() : ( 'undo' === $action ? wra_setup_undo() : null );
		$msg    = is_wp_error( $result ) ? '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>'
			: '<div class="notice notice-success"><p>' . ( 'run' === $action ? 'Done. The new design is live. Check every page, then delete this plugin.' : 'Undone. The site is back to how it was.' ) . '</p></div>';
	}
	$log = get_option( 'wra_setup_log' );
	echo '<div class="wrap"><h1>WRA one-time setup</h1>' . $msg; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
	if ( $log ) {
		echo '<p>Setup ran on ' . esc_html( wp_date( 'F j, Y g:i a', $log['time'] ) ) . '. If something is wrong you can put everything back as it was. Once the site is signed off, deactivate and delete this plugin.</p>';
		echo '<form method="post">';
		wp_nonce_field( 'wra_setup' );
		echo '<input type="hidden" name="wra_setup_action" value="undo">';
		submit_button( 'Undo setup', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Put the site back to how it was before setup?');" ) );
		echo '</form></div>';
		return;
	}
	echo '<p>Take a backup of the site first. This is what will happen:</p><ol>';
	foreach ( wra_setup_plan() as $line ) {
		echo '<li>' . esc_html( $line ) . '</li>';
	}
	echo '</ol><p>Nothing is deleted. Every change is logged, and you can undo it from this screen.</p><form method="post">';
	wp_nonce_field( 'wra_setup' );
	echo '<input type="hidden" name="wra_setup_action" value="run">';
	submit_button( 'Run setup', 'primary', 'submit', false );
	echo '</form></div>';
}

/* WP-CLI: wp wra-setup plan | run | undo */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'wra-setup',
		function ( $args ) {
			$cmd = isset( $args[0] ) ? $args[0] : 'plan';
			if ( 'plan' === $cmd ) {
				foreach ( wra_setup_plan() as $line ) {
					WP_CLI::line( '- ' . $line );
				}
				return;
			}
			$r = 'run' === $cmd ? wra_setup_run() : ( 'undo' === $cmd ? wra_setup_undo() : new WP_Error( 'cmd', 'Use plan, run or undo.' ) );
			is_wp_error( $r ) ? WP_CLI::error( $r->get_error_message() ) : WP_CLI::success( $cmd . ' complete' );
		}
	);
}
