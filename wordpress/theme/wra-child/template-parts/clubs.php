<?php
/**
 * Radio Clubs & Sponsors page (/clubs/): the published listings, A to Z, all in
 * the same card (static design: clubs.html), with the A–Z / Z–A switch
 * (js/wra.js). Added, edited and hidden in wp-admin → Clubs & Sponsors (WRA Site plugin).
 */
defined( 'ABSPATH' ) || exit;

$wra_clubs = wra_clubs();
$wra_n     = count( $wra_clubs );
?>
        <div class="clubs-head">
          <h2 class="eyebrow" id="dir-h">Directory</h2>
<?php if ( $wra_n ) : ?>
          <div class="clubs-tools">
            <p class="clubs-count"><?php echo esc_html( 1 === $wra_n ? '1 listing' : number_format_i18n( $wra_n ) . ' listings' ); ?></p>
<?php if ( $wra_n > 1 ) : ?>
            <div class="clubs-sort" role="group" aria-label="Sort listings" data-clubs-sort data-current="asc">
              <button type="button" data-order="asc" aria-pressed="true"><span aria-hidden="true">A–Z</span><span class="visually-hidden">A to Z</span></button>
              <button type="button" data-order="desc" aria-pressed="false"><span aria-hidden="true">Z–A</span><span class="visually-hidden">Z to A</span></button>
            </div>
            <p class="visually-hidden" role="status" data-clubs-status></p>
<?php endif; ?>
          </div>
<?php endif; ?>
        </div>
<?php if ( ! $wra_n ) : ?>
        <div class="notice" role="note">
          <p class="notice__title">The directory is being compiled</p>
          <p class="notice__text">Listings are being added and will appear here shortly.</p>
        </div>
<?php else : ?>
        <ul class="clubs" role="list" data-reveal>
<?php foreach ( $wra_clubs as $wra_club ) : ?>
          <li class="club<?php echo $wra_club['url'] ? ' club--link' : ''; ?>">
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
<?php endif; ?>
