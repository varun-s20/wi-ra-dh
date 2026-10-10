<?php
/**
 * ARCS page: the platform status notice. Text and date come from
 * WRA Settings (wra-site plugin); if the plugin is off, the last approved
 * wording shows instead.
 */
defined( 'ABSPATH' ) || exit;

$wra_status = function_exists( 'wra_site_arcs_status' ) ? wra_site_arcs_status() : array(
	'show'    => true,
	'title'   => 'Platform status',
	'message' => 'ARCS is up and running at arcsonline.org. New user registration is disabled while we complete setup and testing.',
	'updated' => '2026-10-08',
);
if ( empty( $wra_status['show'] ) ) {
	return;
}
?>
          <div class="notice" role="note">
            <p class="notice__title"><?php echo esc_html( $wra_status['title'] ); ?></p>
            <p class="notice__text"><?php echo wp_kses( $wra_status['message'], array( 'a' => array( 'href' => array(), 'class' => array() ), 'strong' => array(), 'em' => array() ) ); ?></p>
<?php if ( ! empty( $wra_status['updated'] ) ) : ?>
            <p class="notice__meta">Updated <?php echo esc_html( mysql2date( 'F j, Y', $wra_status['updated'] ) ); ?></p>
<?php endif; ?>
          </div>
