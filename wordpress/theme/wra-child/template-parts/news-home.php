<?php
/**
 * Home page: the three latest News posts (static design: index.html, "posts posts--home").
 */
defined( 'ABSPATH' ) || exit;

$wra_q = wra_news_query( array( 'posts_per_page' => 3 ) );
if ( ! $wra_q->have_posts() ) {
	return;
}
$wra_n     = count( $wra_q->posts );
$wra_delay = array( '', ' style="--d:.08s"', ' style="--d:.16s"' );
?>
        <div class="posts posts--home">
<?php foreach ( $wra_q->posts as $wra_i => $wra_item ) : ?>
          <article class="post" data-reveal<?php echo $wra_delay[ $wra_i ]; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed strings ?>>
            <p class="post__idx"><?php echo (int) ( $wra_i + 1 ); ?> of <?php echo (int) $wra_n; ?></p>
            <div class="post__body">
              <?php echo wra_post_time( $wra_item ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

              <h3><a href="<?php echo esc_url( get_permalink( $wra_item ) ); ?>"><?php echo wra_post_title( $wra_item ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></h3>
              <p><?php echo wra_nowrap( esc_html( wra_post_summary( $wra_item ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
            </div>
            <div class="post__media"><?php echo wra_post_image( $wra_item, 'medium_large', array( 'loading' => 'lazy', 'sizes' => '(max-width: 760px) 40vw, 200px' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
          </article>
<?php endforeach; ?>
        </div>
<?php
wp_reset_postdata();
