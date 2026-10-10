<?php
/**
 * Fallback for archives and search (category, date, author, search results):
 * a list of posts in the News archive style.
 */
defined( 'ABSPATH' ) || exit;

wra_page_generic();
get_header();
?>
  <main id="main">
    <section class="phead phead--plain" aria-labelledby="page-title">
      <div class="phead__rings" aria-hidden="true"><i></i><i></i><i></i></div>
      <div class="wrap phead__inner">
        <?php wra_crumbs(); ?>
        <h1 class="phead__title" id="page-title"><?php echo esc_html( is_search() ? sprintf( 'Search results for “%s”', get_search_query() ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
      </div>
    </section>

    <section class="sec sec--first">
      <div class="wrap">
        <?php if ( have_posts() ) : ?>
        <h2 class="eyebrow"><?php echo is_search() ? 'Results' : 'Posts'; ?></h2>
        <ul class="archive" role="list" style="margin-top:0">
          <?php
          while ( have_posts() ) :
              the_post();
              get_template_part( 'template-parts/news', 'row', array( 'post' => get_post() ) );
          endwhile;
          ?>
        </ul>
        <?php the_posts_pagination( array( 'class' => 'wra-pagination', 'prev_text' => 'Newer', 'next_text' => 'Older' ) ); ?>
        <?php else : ?>
        <p class="body-copy">Nothing found. <a class="inline-link" href="<?php wra_u( '/news/' ); ?>">See all news</a>.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>
<?php
get_footer();
