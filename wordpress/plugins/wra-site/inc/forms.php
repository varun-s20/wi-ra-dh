<?php
/**
 * Website forms (Contact, Volunteer): a REST endpoint the site script posts to,
 * a saved copy of every message (wp-admin → Messages), and an email to the
 * address in WRA Settings. Mail goes through wp_mail(), so whatever delivers the
 * site's email (on Azure: App Service Email) delivers these too.
 */
defined( 'ABSPATH' ) || exit;

/** The forms the site has, their fields (name => label) and which are required. */
function wra_site_forms() {
	return array(
		'contact'   => array(
			'label'    => 'Contact form',
			'fields'   => array( 'name' => 'Name', 'callsign' => 'Callsign', 'email' => 'Email', 'subject' => 'Subject', 'message' => 'Message', 'consent' => 'Consent to be contacted' ),
			'required' => array( 'name', 'email', 'subject', 'message', 'consent' ),
		),
		'volunteer' => array(
			'label'    => 'Volunteer interest',
			'fields'   => array( 'name' => 'Name', 'callsign' => 'Callsign', 'email' => 'Email', 'areas' => 'Areas of interest', 'experience' => 'Relevant experience', 'time' => 'Time to contribute', 'comments' => 'Comments', 'consent' => 'Consent to be contacted' ),
			'required' => array( 'name', 'email', 'consent' ),
		),
	);
}

function wra_site_register_message_type() {
	register_post_type(
		'wra_message',
		array(
			'labels'          => array(
				'name'               => 'Messages',
				'singular_name'      => 'Message',
				'menu_name'          => 'Messages',
				'all_items'          => 'All messages',
				'edit_item'          => 'Message',
				'view_item'          => 'View message',
				'search_items'       => 'Search messages',
				'not_found'          => 'No messages yet.',
				'not_found_in_trash' => 'No messages in the Bin.',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => false,
			'menu_position'   => 26,
			'menu_icon'       => 'dashicons-email-alt',
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'wra_site_register_message_type' );

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'wra/v1',
			'/message',
			array(
				'methods'             => 'POST',
				'callback'            => 'wra_site_receive_message',
				'permission_callback' => '__return_true', // public form; protected by honeypot, time trap and rate limit
			)
		);
	}
);

function wra_site_receive_message( WP_REST_Request $request ) {
	$p     = $request->get_body_params();
	$forms = wra_site_forms();
	$form  = isset( $p['_form'] ) ? sanitize_key( $p['_form'] ) : '';
	if ( ! isset( $forms[ $form ] ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'unknown_form' ), 400 );
	}

	// Spam: a filled honeypot, or a submission within 3 seconds of the page loading,
	// is answered as if it worked so bots learn nothing, and is not saved or sent.
	$ts = isset( $p['_ts'] ) ? (int) $p['_ts'] : 0;
	if ( ! empty( $p['_hp'] ) || ! $ts || ( time() - $ts ) < 3 ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	// At most 5 messages per address per 10 minutes.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'wra_msg_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'too_many' ), 429 );
	}

	$def    = $forms[ $form ];
	$values = array();
	foreach ( $def['fields'] as $name => $label ) {
		$raw = isset( $p[ $name ] ) ? $p[ $name ] : '';
		if ( is_array( $raw ) ) {
			$val = implode( ', ', array_map( 'sanitize_text_field', array_map( 'wp_unslash', $raw ) ) );
		} elseif ( in_array( $name, array( 'message', 'comments' ), true ) ) {
			$val = sanitize_textarea_field( wp_unslash( $raw ) );
		} elseif ( 'email' === $name ) {
			$val = sanitize_email( wp_unslash( $raw ) );
		} else {
			$val = sanitize_text_field( wp_unslash( $raw ) );
		}
		$values[ $name ] = mb_substr( $val, 0, in_array( $name, array( 'message', 'comments' ), true ) ? 5000 : 300 );
	}
	foreach ( $def['required'] as $name ) {
		if ( '' === $values[ $name ] ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'missing_' . $name ), 400 );
		}
	}
	if ( ! is_email( $values['email'] ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_email' ), 400 );
	}
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

	$topic = ( 'contact' === $form && $values['subject'] ) ? $values['subject'] : $def['label'];
	$title = $topic . ' from ' . $values['name'] . ( $values['callsign'] ? ' (' . strtoupper( $values['callsign'] ) . ')' : '' );

	$id = wp_insert_post(
		array(
			'post_type'   => 'wra_message',
			'post_status' => 'publish',
			'post_title'  => $title,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'save_failed' ), 500 );
	}
	update_post_meta( $id, '_wra_form', $form );
	update_post_meta( $id, '_wra_fields', $values );
	update_post_meta( $id, '_wra_page', esc_url_raw( (string) $request->get_header( 'referer' ) ) );

	// Email: From stays the site's own sender; the visitor goes in Reply-To.
	$lines = array();
	foreach ( $def['fields'] as $name => $label ) {
		if ( '' !== $values[ $name ] ) {
			$lines[] = $label . ': ' . ( 'consent' === $name ? 'Yes' : $values[ $name ] );
		}
	}
	$lines[] = '';
	$lines[] = 'Sent from the ' . strtolower( $def['label'] ) . ' on ' . wp_parse_url( home_url(), PHP_URL_HOST ) . '. Reply to this email to answer ' . $values['name'] . ' directly.';
	$lines[] = 'Saved in WordPress: ' . admin_url( 'post.php?post=' . $id . '&action=edit' );

	$to      = wra_site_option( 'form_to' );
	$subject = '[WRA website] ' . $title;
	$reply   = str_replace( array( "\r", "\n", '<', '>', '"' ), '', $values['name'] ) . ' <' . $values['email'] . '>';
	$sent    = wp_mail( $to, $subject, implode( "\n", $lines ), array( 'Reply-To: ' . $reply ) );
	update_post_meta( $id, '_wra_mailed', $sent ? 'yes' : 'no' );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/* Messages: a read-only view of what was sent, and useful list columns. */
add_action(
	'add_meta_boxes_wra_message',
	function () {
		remove_meta_box( 'submitdiv', 'wra_message', 'side' );
		add_meta_box( 'wra-message', 'Message', 'wra_site_message_box', 'wra_message', 'normal', 'high' );
	}
);

function wra_site_message_box( $post ) {
	$form   = get_post_meta( $post->ID, '_wra_form', true );
	$forms  = wra_site_forms();
	$fields = (array) get_post_meta( $post->ID, '_wra_fields', true );
	$labels = isset( $forms[ $form ] ) ? $forms[ $form ]['fields'] : array();
	echo '<table class="widefat striped"><tbody>';
	printf( '<tr><th style="width:12rem">Form</th><td>%s</td></tr>', esc_html( isset( $forms[ $form ] ) ? $forms[ $form ]['label'] : $form ) );
	printf( '<tr><th>Received</th><td>%s</td></tr>', esc_html( get_the_date( 'F j, Y g:i a', $post ) ) );
	foreach ( $fields as $name => $value ) {
		if ( '' === $value ) {
			continue;
		}
		$label = isset( $labels[ $name ] ) ? $labels[ $name ] : $name;
		if ( 'email' === $name ) {
			$value_html = '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
		} elseif ( 'consent' === $name ) {
			$value_html = 'Yes';
		} else {
			$value_html = nl2br( esc_html( $value ) );
		}
		printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $label ), $value_html ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
	}
	$mailed = get_post_meta( $post->ID, '_wra_mailed', true );
	printf( '<tr><th>Email sent</th><td>%s</td></tr>', 'yes' === $mailed ? 'Yes' : '<strong>No</strong>: the email did not go out, so reply from here.' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</tbody></table>';
	if ( ! empty( $fields['email'] ) ) {
		printf( '<p><a class="button button-primary" href="mailto:%s?subject=%s">Reply by email</a></p>', esc_attr( $fields['email'] ), rawurlencode( 'Re: ' . $post->post_title ) );
	}
}

add_filter(
	'manage_wra_message_posts_columns',
	function () {
		return array( 'cb' => '<input type="checkbox">', 'title' => 'Message', 'wra_email' => 'Email', 'wra_form' => 'Form', 'date' => 'Received' );
	}
);
add_action(
	'manage_wra_message_posts_custom_column',
	function ( $col, $id ) {
		$fields = (array) get_post_meta( $id, '_wra_fields', true );
		if ( 'wra_email' === $col && ! empty( $fields['email'] ) ) {
			printf( '<a href="mailto:%1$s">%1$s</a>', esc_html( $fields['email'] ) );
		}
		if ( 'wra_form' === $col ) {
			$forms = wra_site_forms();
			$form  = get_post_meta( $id, '_wra_form', true );
			echo esc_html( isset( $forms[ $form ] ) ? $forms[ $form ]['label'] : $form );
		}
	},
	10,
	2
);
/* No "Published"/"Last modified" status text for messages. */
add_filter(
	'display_post_states',
	function ( $states, $post ) {
		return 'wra_message' === $post->post_type ? array() : $states;
	},
	10,
	2
);

/* Personal data: Tools → Export/Erase Personal Data include website messages. */
add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['wra-messages'] = array(
			'eraser_friendly_name' => 'Website messages',
			'callback'             => function ( $email ) {
				$removed = 0;
				foreach ( wra_site_messages_by_email( $email ) as $id ) {
					wp_delete_post( $id, true );
					$removed++;
				}
				return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
			},
		);
		return $erasers;
	}
);
add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['wra-messages'] = array(
			'exporter_friendly_name' => 'Website messages',
			'callback'               => function ( $email ) {
				$data = array();
				foreach ( wra_site_messages_by_email( $email ) as $id ) {
					$items = array();
					foreach ( (array) get_post_meta( $id, '_wra_fields', true ) as $k => $v ) {
						$items[] = array( 'name' => $k, 'value' => $v );
					}
					$data[] = array( 'group_id' => 'wra-messages', 'group_label' => 'Website messages', 'item_id' => 'wra-message-' . $id, 'data' => $items );
				}
				return array( 'data' => $data, 'done' => true );
			},
		);
		return $exporters;
	}
);

function wra_site_messages_by_email( $email ) {
	$ids = get_posts( array( 'post_type' => 'wra_message', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) );
	return array_filter(
		$ids,
		function ( $id ) use ( $email ) {
			$f = (array) get_post_meta( $id, '_wra_fields', true );
			return isset( $f['email'] ) && strtolower( $f['email'] ) === strtolower( $email );
		}
	);
}
