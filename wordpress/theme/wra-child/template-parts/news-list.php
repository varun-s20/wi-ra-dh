<?php
/**
 * News page: the newest post as the feature, then the archive, newest first,
 * 20 to a page (static design: news.html).
 */
defined( 'ABSPATH' ) || exit;

$wra_paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$wra_feat  = null;
if ( 1 === $wra_paged ) {
	$wra_f    = wra_news_query( array( 'posts_per_page' => 1 ) );
	$wra_feat = $wra_f->have_posts() ? $wra_f->posts[0] : null;
}
$wra_q = wra_news_query(
	array(
		'posts_per_page' => 20,
		'offset'         => 1 + ( $wra_paged - 1 ) * 20,
		'no_found_rows'  => false,
	)
);
$wra_pages = (int) ceil( max( 0, $wra_q->found_posts - 1 ) / 20 );

if ( $wra_feat ) :
	?>
        <article class="feature" data-reveal>
          <div class="feature__media"><?php echo wra_post_image( $wra_feat, 'medium_large', array( 'sizes' => '(max-width: 760px) 46vw, 300px' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
          <div class="feature__body">
            <?php echo wra_post_time( $wra_feat ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

            <h3><a href="<?php echo esc_url( get_permalink( $wra_feat ) ); ?>"><?php echo wra_post_title( $wra_feat ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a></h3>
            <p><?php echo wra_nowrap( esc_html( wra_post_summary( $wra_feat ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
            <span class="alink" aria-hidden="true">Read the announcement</span>
          </div>
        </article>
	<?php
endif;

if ( $wra_q->have_posts() ) :
	?>

        <h2 class="eyebrow" style="margin-top:clamp(4rem,3rem + 3vw,6rem)">Archive</h2>
        <ul class="archive" role="list" style="margin-top:0">
<?php
	foreach ( $wra_q->posts as $wra_item ) {
		get_template_part( 'template-parts/news', 'row', array( 'post' => $wra_item ) );
	}
	?>
        </ul>
	<?php
	if ( $wra_pages > 1 ) {
		echo '<nav class="pager" aria-label="Older and newer news">';
		echo $wra_paged > 1 ? '<a href="' . esc_url( get_pagenum_link( $wra_paged - 1 ) ) . '"><small>Newer</small>Newer posts</a>' : '<span></span>';
		echo $wra_paged < $wra_pages ? '<a href="' . esc_url( get_pagenum_link( $wra_paged + 1 ) ) . '"><small>Older</small>Older posts</a>' : '';
		echo '</nav>';
	}
endif;
wp_reset_postdata();
