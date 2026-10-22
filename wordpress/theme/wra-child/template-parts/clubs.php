<?php
/**
 * Radio Clubs & Sponsors page (/clubs/): the published listings, 12 to a page, A to Z or Z to A
 * across the whole directory, all in the same card (static design: clubs.html, built by
 * _build/clubs_static.py). The switch and the page links are real addresses; js/wra.js swaps the
 * cards in place when it can. Listings are added, edited and hidden in wp-admin → Clubs & Sponsors.
 */
defined( 'ABSPATH' ) || exit;

$wra_view = wra_clubs_view();
$wra_n    = $wra_view['total'];

if ( ! $wra_n ) :
	?>
        <div class="clubs-head">
          <h2 class="eyebrow" id="dir-h">Directory</h2>
        </div>
        <div class="notice" role="note">
          <p class="notice__title">The directory is being compiled</p>
          <p class="notice__text">Listings are being added and will appear here shortly.</p>
        </div>
        <div class="clubs clubs--ghost" aria-hidden="true">
<?php for ( $wra_i = 0; $wra_i < 4; $wra_i++ ) : ?>
          <div class="club club--skeleton"><div class="club__logo"></div><div class="club__body"><div class="club__name--sk"><span class="sk sk--line"></span><span class="sk sk--line sk--short"></span></div><span class="sk sk--link"></span></div></div>
<?php endfor; ?>
        </div>
	<?php
	return;
endif;

if ( $wra_view['pages'] > 1 ) {
	$wra_count = sprintf( 'Showing %d–%d of %d', $wra_view['from'], $wra_view['to'], $wra_n );
} else {
	$wra_count = 1 === $wra_n ? '1 listing' : $wra_n . ' listings';
}
$wra_az = 'az' === $wra_view['order'];
?>
        <div class="clubs-dir" id="directory" data-clubs-dir data-order="<?php echo esc_attr( $wra_view['order'] ); ?>" data-page="<?php echo (int) $wra_view['page']; ?>" data-pages="<?php echo (int) $wra_view['pages']; ?>" data-total="<?php echo (int) $wra_n; ?>" data-per="<?php echo (int) $wra_view['per']; ?>">
          <div class="clubs-head">
            <h2 class="eyebrow" id="dir-h" tabindex="-1">Directory</h2>
            <div class="clubs-tools">
              <p class="clubs-count"><?php echo esc_html( $wra_count ); ?></p>
              <div class="clubs-sort" role="group" aria-label="Sort listings" data-clubs-sort data-current="<?php echo $wra_az ? 'asc' : 'desc'; ?>">
                <a href="<?php echo esc_url( wra_clubs_url( 1, 'az' ) ); ?>"<?php echo $wra_az ? ' aria-current="true"' : ''; ?>><span aria-hidden="true">A–Z</span><span class="visually-hidden">A to Z</span></a>
                <a href="<?php echo esc_url( wra_clubs_url( 1, 'za' ) ); ?>"<?php echo $wra_az ? '' : ' aria-current="true"'; ?>><span aria-hidden="true">Z–A</span><span class="visually-hidden">Z to A</span></a>
              </div>
              <p class="visually-hidden" role="status" data-clubs-status></p>
            </div>
          </div>
          <div class="clubs-frame">
          <ul class="clubs" role="list" data-reveal>
<?php foreach ( $wra_view['items'] as $wra_club ) : ?>
          <li class="club<?php echo $wra_club['url'] ? ' club--link' : ''; ?>" data-club="<?php echo (int) $wra_club['id']; ?>">
            <?php echo wra_club_logo( $wra_club ); // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup / escaped in helper ?>

            <div class="club__body">
              <h3 class="club__name"><?php echo esc_html( $wra_club['name'] ); ?></h3>
<?php if ( $wra_club['url'] ) : ?>
              <a class="alink alink--ext club__link" href="<?php echo esc_url( $wra_club['url'] ); ?>" target="_blank" rel="noopener">Visit website<span class="visually-hidden">: <?php echo esc_html( $wra_club['name'] ); ?> (opens in a new tab)</span></a>
<?php endif; ?>
            </div>
          </li>
<?php endforeach; ?>
          </ul>
          </div>
<?php wra_clubs_pager( $wra_view ); ?>
        </div>
