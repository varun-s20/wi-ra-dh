<?php
/**
 * One archive row (static design: news.html, "arow"). Expects $args['post'].
 */
defined( 'ABSPATH' ) || exit;

$wra_item = $args['post'];
?>
          <li class="arow" data-reveal><?php echo wra_post_time( $wra_item ); // phpcs:ignore WordPress.Security.EscapeOutput ?><div><h3><a href="<?php echo esc_url( get_permalink( $wra_item ) ); ?>"><?php echo wra_post_title( $wra_item ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></h3><p><?php echo wra_nowrap( esc_html( wra_post_summary( $wra_item ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p></div><span class="arow__go" aria-hidden="true">&rarr;</span></li>
