<?php
defined('ABSPATH') || exit;
function lm_about_page(){return is_page('about-us');}
add_filter('template_include',static function($template){return lm_about_page()?__DIR__.'/about-page.php':$template;},900);
add_action('wp_enqueue_scripts',static function(){if(!lm_about_page())return;wp_enqueue_style('landmark-about',get_stylesheet_directory_uri().'/about.css',array('landmark-shared-shell'),(string)filemtime(__DIR__.'/about.css'));},130);

/** 采购价格提示沿用 Core 的可见性规则及登录链接。 */
add_shortcode('landmark_about_prices', static function () {
 if (!class_exists('LandmarkUS_Price_Access') || !LandmarkUS_Price_Access::restricted()) {
  return '';
 }

 return '<p>' . LandmarkUS_Price_Access::prompt() . '</p>';
});
