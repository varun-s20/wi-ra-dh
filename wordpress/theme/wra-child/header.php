<?php
/**
 * Document head + the site header and mobile drawer (template-parts/chrome-header.php,
 * generated from _build/partials/header.html).
 */
defined( 'ABSPATH' ) || exit;
if ( ! wra_is_ours() ) {
	wra_page_generic();
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php get_template_part( 'template-parts/chrome-header' ); ?>
