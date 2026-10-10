<?php
/**
 * News posts: an optional Byline shown under the title ("Corey Becker, KD9HCW, President").
 * Empty means "Wisconsin Repeater Alliance".
 */
defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_meta(
			'post',
			'_wra_byline',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
);

/** Public API for the theme. */
function wra_site_byline( $post_id ) {
	return (string) get_post_meta( $post_id, '_wra_byline', true );
}

add_action(
	'add_meta_boxes_post',
	function () {
		add_meta_box( 'wra-byline', 'Byline', 'wra_site_byline_box', 'post', 'side', 'default' );
	}
);

function wra_site_byline_box( $post ) {
	wp_nonce_field( 'wra_byline_save', 'wra_byline_nonce' );
	printf(
		'<p><input type="text" class="widefat" name="wra_byline" value="%s" placeholder="Wisconsin Repeater Alliance"></p><p class="description">Who the post is from, shown under the title. Leave empty for "Wisconsin Repeater Alliance". Example: Corey Becker, KD9HCW, President</p>',
		esc_attr( wra_site_byline( $post->ID ) )
	);
}

add_action(
	'save_post_post',
	function ( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['wra_byline_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wra_byline_nonce'] ) ), 'wra_byline_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$byline = isset( $_POST['wra_byline'] ) ? sanitize_text_field( wp_unslash( $_POST['wra_byline'] ) ) : '';
		if ( '' === $byline ) {
			delete_post_meta( $post_id, '_wra_byline' );
		} else {
			update_post_meta( $post_id, '_wra_byline', $byline );
		}
	}
);
