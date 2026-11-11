<?php
/** 采购指南展示；原生区块内容可编辑，价格访问说明跟随Core实时配置。 */
defined('ABSPATH') || exit;
function lm_buyer_guide_page(){return is_page('wholesale-buying-guide');}
add_filter('template_include',static function($template){return lm_buyer_guide_page()?__DIR__.'/buyer-guide-page.php':$template;},900);
add_action('wp_enqueue_scripts',static function(){if(lm_buyer_guide_page())wp_enqueue_style('landmark-buyer-guide',get_stylesheet_directory_uri().'/buyer-guide.css',array('landmark-shared-shell'),(string)filemtime(__DIR__.'/buyer-guide.css'));},130);
add_shortcode('landmark_guide_prices',static function(){
 $mode=class_exists('LandmarkUS_Price_Access')?LandmarkUS_Price_Access::mode():'logged_in';
 if($mode==='public')return '<p>Product prices are currently visible in the catalog and on product pages. Wholesale belt prices are per belt. You can also <a href="'.esc_url(wc_get_page_permalink('myaccount')).'">sign in to your account</a>.</p>';
 if(is_user_logged_in())return '<p>You are signed in. View wholesale prices in the catalog or on each product page. Belt prices are per belt.</p>';
 return '<p><a href="'.esc_url(wc_get_page_permalink('myaccount')).'">Log in to view wholesale prices</a>. You can browse styles and product options before signing in. Belt prices are per belt.</p>';
});
