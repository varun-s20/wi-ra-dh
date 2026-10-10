<?php
/**
 * WRA Settings: the ARCS status notice and where website form emails go.
 */
defined( 'ABSPATH' ) || exit;

function wra_site_defaults() {
	return array(
		'arcs_show'    => 1,
		'arcs_title'   => 'Platform status',
		'arcs_message' => 'ARCS is up and running at arcsonline.org. New user registration is disabled while we complete setup and testing.',
		'arcs_updated' => '2026-10-08',
		'form_to'      => 'info@wi-ra.org',
	);
}

function wra_site_option( $key ) {
	$opts = wp_parse_args( (array) get_option( 'wra_site_options', array() ), wra_site_defaults() );
	return isset( $opts[ $key ] ) ? $opts[ $key ] : '';
}

/** Public API for the theme: the ARCS status notice. */
function wra_site_arcs_status() {
	return array(
		'show'    => (bool) wra_site_option( 'arcs_show' ),
		'title'   => wra_site_option( 'arcs_title' ),
		'message' => wra_site_option( 'arcs_message' ),
		'updated' => wra_site_option( 'arcs_updated' ),
	);
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'wra_site',
			'wra_site_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'wra_site_sanitize',
				'default'           => wra_site_defaults(),
			)
		);
	}
);

/* Editors may change the ARCS notice; only administrators see the form recipient. */
add_filter(
	'option_page_capability_wra_site',
	function () {
		return 'edit_pages';
	}
);

function wra_site_sanitize( $in ) {
	$old = wp_parse_args( (array) get_option( 'wra_site_options', array() ), wra_site_defaults() );
	$in  = (array) $in;
	$out = $old;

	$out['arcs_show']    = empty( $in['arcs_show'] ) ? 0 : 1;
	$out['arcs_title']   = sanitize_text_field( isset( $in['arcs_title'] ) ? $in['arcs_title'] : '' );
	$out['arcs_message'] = wp_kses( isset( $in['arcs_message'] ) ? $in['arcs_message'] : '', array( 'a' => array( 'href' => array() ), 'strong' => array(), 'em' => array() ) );
	$date                = isset( $in['arcs_updated'] ) ? sanitize_text_field( $in['arcs_updated'] ) : '';
	$out['arcs_updated'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';

	if ( current_user_can( 'manage_options' ) && isset( $in['form_to'] ) ) {
		$emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $in['form_to'] ) ) ), 'is_email' );
		if ( $emails ) {
			$out['form_to'] = implode( ', ', $emails );
		} else {
			add_settings_error( 'wra_site_options', 'form_to', 'Enter at least one valid email address for website forms.' );
		}
	}
	if ( '' === $out['arcs_title'] ) {
		$out['arcs_title'] = 'Platform status';
	}
	return $out;
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'WRA Settings', 'WRA Settings', 'edit_pages', 'wra-settings', 'wra_site_settings_page', 'dashicons-admin-site-alt3', 30 );
	}
);

function wra_site_settings_page() {
	$o = wp_parse_args( (array) get_option( 'wra_site_options', array() ), wra_site_defaults() );
	?>
	<div class="wrap">
		<h1>WRA Settings</h1>
		<?php settings_errors( 'wra_site_options' ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'wra_site' ); ?>

			<h2>ARCS status notice</h2>
			<p>Shown near the top of the <a href="<?php echo esc_url( home_url( '/arcs/' ) ); ?>" target="_blank" rel="noopener">ARCS page</a>. Change the message and the date whenever the platform's status changes.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Show the notice</th>
					<td><label><input type="checkbox" name="wra_site_options[arcs_show]" value="1" <?php checked( $o['arcs_show'] ); ?>> Show the status notice on the ARCS page</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="wra-arcs-title">Title</label></th>
					<td><input id="wra-arcs-title" class="regular-text" type="text" name="wra_site_options[arcs_title]" value="<?php echo esc_attr( $o['arcs_title'] ); ?>">
					<p class="description">Usually "Platform status".</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wra-arcs-message">Message</label></th>
					<td><textarea id="wra-arcs-message" class="large-text" rows="3" name="wra_site_options[arcs_message]"><?php echo esc_textarea( $o['arcs_message'] ); ?></textarea>
					<p class="description">One or two sentences. Plain text; links are allowed.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="wra-arcs-updated">"Updated" date</label></th>
					<td><input id="wra-arcs-updated" type="date" name="wra_site_options[arcs_updated]" value="<?php echo esc_attr( $o['arcs_updated'] ); ?>">
					<p class="description">Shown as "Updated October 8, 2026". Leave empty to hide the date.</p></td>
				</tr>
			</table>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<h2>Website forms</h2>
			<p>Messages from the Contact form and the Volunteer form are emailed here and also saved under <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wra_message' ) ); ?>">Messages</a>.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wra-form-to">Send form emails to</label></th>
					<td><input id="wra-form-to" class="regular-text" type="text" name="wra_site_options[form_to]" value="<?php echo esc_attr( $o['form_to'] ); ?>">
					<p class="description">One address, or several separated by commas.</p></td>
				</tr>
			</table>
			<?php endif; ?>

			<?php submit_button( 'Save settings' ); ?>
		</form>
	</div>
	<?php
}
