<?php defined('ABSPATH') || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?><a class="lm-skip" href="#main">Skip to content</a>
<header class="lm-header lm-original-header"><div class="lm-shell lm-header__inner">
<details class="lm-mobile-menu"><summary aria-label="Menu"><?php lm_shell_icon('menu'); ?><span class="screen-reader-text">Menu</span></summary><nav aria-label="Mobile navigation"><?php lm_shell_nav('mobile'); ?><a class="lm-mobile-account" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Log in / My account</a></nav></details>
<a class="lm-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="LandmarkUS home"><img src="<?php echo esc_url(get_stylesheet_directory_uri().'/assets/landmark.png'); ?>" alt="Landmark" width="266" height="150" fetchpriority="high"></a>
<nav class="lm-nav" aria-label="Main navigation"><?php lm_shell_nav('desktop'); ?></nav>
<div class="lm-header-actions">
<form class="lm-desktop-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"><label class="screen-reader-text" for="lm-desktop-search">Search products</label><input type="search" id="lm-desktop-search" name="s" placeholder="Search…" required><input type="hidden" name="post_type" value="product"><button type="submit" aria-label="Search products"><?php lm_shell_icon('search'); ?></button></form>
<details class="lm-site-search"><summary aria-label="Search"><?php lm_shell_icon('search'); ?></summary><form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>"><label for="lm-header-search">Search products</label><div><input type="search" id="lm-header-search" name="s" placeholder="Search…" required><input type="hidden" name="post_type" value="product"><button type="submit" aria-label="Search products"><?php lm_shell_icon('search'); ?></button></div></form></details>
<a class="lm-cart" href="<?php echo esc_url(wc_get_cart_url()); ?>" aria-label="Shopping bag"><?php lm_shell_icon('cart'); ?></a><a class="lm-account" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" aria-label="My account"><?php lm_shell_icon('account'); ?></a>
</div></div></header>
