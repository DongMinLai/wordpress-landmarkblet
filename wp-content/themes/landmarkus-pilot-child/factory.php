<?php
defined('ABSPATH') || exit;
function lm_factory_page(){return is_page('wz-factory');}
add_filter('template_include',static function($template){return lm_factory_page()?__DIR__.'/factory-page.php':$template;},900);
add_action('wp_enqueue_scripts',static function(){if(!lm_factory_page())return;wp_enqueue_style('landmark-factory',get_stylesheet_directory_uri().'/factory.css',array('landmark-shared-shell'),(string)filemtime(__DIR__.'/factory.css'));},130);
