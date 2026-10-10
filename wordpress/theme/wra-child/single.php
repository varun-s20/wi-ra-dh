<?php
/**
 * A News post. Markup matches the static design's article pages (_build/pages/news-*.html).
 */
defined( 'ABSPATH' ) || exit;

the_post();
$wra_post = get_post();
wra_page_post( $wra_post );
get_header();

$wra_more = wra_news_query( array( 'post__not_in' => array( $wra_post->ID ) ) );
$wra_prev = get_previous_post(); // older
$wra_next = get_next_post();     // newer
?>
  <main id="main">
    <section class="phead phead--plain phead--article" aria-labelledby="page-title">
      <div class="phead__rings" aria-hidden="true"><i></i><i></i><i></i></div>
      <div class="wrap phead__inner">
        <?php wra_crumbs(); ?>
        <p class="meta" style="color:var(--blue-soft);margin-bottom:1rem">News &middot; <?php echo wra_post_time( $wra_post ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper ?></p>
        <h1 class="phead__title" id="page-title"><?php echo wra_post_title( $wra_post ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper ?></h1>
        <p class="byline"><b><?php echo esc_html( wra_byline( $wra_post ) ); ?></b><span class="sep" aria-hidden="true"></span><span><?php echo esc_html( wra_reading_time( $wra_post ) ); ?> min read</span></p>
      </div>
    </section>

    <section class="sec sec--first">
      <div class="wrap grid article">
        <article class="article__body prose" data-reveal>
          <?php the_content(); ?>
        </article>
        <aside class="article__aside" aria-label="About this article">
          <div class="aside-block aside-plate"><?php echo wra_post_image( $wra_post, 'medium_large', array( 'sizes' => '(max-width: 1000px) 100vw, 480px' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup ?></div>
          <div class="aside-block"><button class="copy-link" type="button" data-copy-link><span>Copy link</span></button></div>
          <?php if ( $wra_more->have_posts() ) : ?>
          <div class="aside-block">
            <p class="meta" style="margin-bottom:1rem">More news</p>
            <ul class="aside-list" role="list">
              <?php
              foreach ( $wra_more->posts as $wra_item ) {
                  printf(
                      '<li><a href="%s">%s%s</a></li>',
                      esc_url( get_permalink( $wra_item ) ),
                      wra_post_time( $wra_item ), // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper
                      wra_post_title( $wra_item ) // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in helper
                  );
              }
              ?>
            </ul>
            <a class="alink" href="<?php wra_u( '/news/' ); ?>" style="margin-top:1.25rem">All news</a>
          </div>
          <?php endif; ?>
        </aside>
      </div>
      <?php if ( $wra_prev || $wra_next ) : ?>
      <div class="wrap"><nav class="pager" aria-label="More articles"><?php
        if ( $wra_prev ) {
            printf( '<a href="%s"><small>Previous</small>%s</a>', esc_url( get_permalink( $wra_prev ) ), wra_post_title( $wra_prev ) ); // phpcs:ignore WordPress.Security.EscapeOutput
        } else {
            echo '<span></span>';
        }
        if ( $wra_next ) {
            printf( '<a href="%s"><small>Next</small>%s</a>', esc_url( get_permalink( $wra_next ) ), wra_post_title( $wra_next ) ); // phpcs:ignore WordPress.Security.EscapeOutput
        }
      ?></nav></div>
      <?php endif; ?>
    </section>
  </main>
<?php
wp_reset_postdata();
get_footer();
