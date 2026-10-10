<?php
/**
 * Wisconsin Repeater Alliance child theme.
 *
 * inc/setup.php    theme supports, the parent/Elementor neutraliser, template routing, assets
 * inc/head.php     <title>, meta description, Open Graph, JSON-LD, preload, fonts, icons
 * inc/helpers.php  small helpers the templates call (links, breadcrumbs, nav state, forms, docs)
 * inc/news.php     News post helpers (cards, dates, bylines, reading time)
 * inc/page-meta.php  GENERATED page titles/descriptions/breadcrumbs (from the static build)
 */
defined( 'ABSPATH' ) || exit;

define( 'WRA_THEME_VERSION', '1.0.0' );
define( 'WRA_DIR', get_stylesheet_directory() );
// Base URL of the theme's assets folder. Templates echo it in front of design image paths ("img/brand/...").
define( 'WRA_A', esc_url( get_stylesheet_directory_uri() . '/assets/' ) );

require_once WRA_DIR . '/inc/helpers.php';
require_once WRA_DIR . '/inc/news.php';
require_once WRA_DIR . '/inc/setup.php';
require_once WRA_DIR . '/inc/head.php';
