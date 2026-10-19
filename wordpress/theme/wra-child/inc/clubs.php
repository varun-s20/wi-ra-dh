<?php
/**
 * Radio Clubs & Sponsors page helpers. The records live in the WRA Site plugin (wp-admin → Clubs & Sponsors);
 * these render them in the design's card markup (static design: clubs.html).
 */
defined( 'ABSPATH' ) || exit;

/** The published listings (clubs and sponsors), A to Z (none if the WRA Site plugin is off). */
function wra_clubs() {
	return function_exists( 'wra_site_clubs' ) ? wra_site_clubs() : array();
}

/** A listing's logo plate: its logo, or the WRA placeholder (the header logo in greyscale) when it has none or the file has gone. */
function wra_club_logo( array $club ) {
	$img = $club['logo'] ? wp_get_attachment_image(
		$club['logo'],
		'medium',
		false,
		array(
			'alt'     => '', // the club name sits right below it
			'loading' => 'lazy',
			'sizes'   => '(max-width: 560px) 96px, (max-width: 820px) 34vw, (max-width: 1100px) 22vw, 220px',
		)
	) : '';
	if ( $img ) {
		return '<div class="club__logo">' . $img . '</div>';
	}
	return sprintf(
		'<div class="club__logo club__logo--none"><img src="%1$simg/brand/wra-logo-nav-144.webp" srcset="%1$simg/brand/wra-logo-nav-144.webp 1x, %1$simg/brand/wra-logo-nav-216.webp 2x" width="151" height="144" alt="" loading="lazy"></div>',
		WRA_A
	);
}
