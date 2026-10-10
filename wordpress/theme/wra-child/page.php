<?php
/**
 * Any page that has no design template of its own (for example a page the team
 * adds later): title in the page header, the editor content in the article column.
 */
defined( 'ABSPATH' ) || exit;

wra_page_generic();
get_header();
the_post();
?>
  <main id="main">
    <section class="phead phead--plain" aria-labelledby="page-title">
      <div class="phead__rings" aria-hidden="true"><i></i><i></i><i></i></div>
      <div class="wrap phead__inner">
        <?php wra_crumbs(); ?>
        <h1 class="phead__title" id="page-title"><?php echo wra_nowrap( esc_html( get_the_title() ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped first ?></h1>
      </div>
    </section>

    <section class="sec sec--first">
      <div class="wrap grid article">
        <div class="article__body prose">
          <?php the_content(); ?>
        </div>
      </div>
    </section>
  </main>
<?php
get_footer();
