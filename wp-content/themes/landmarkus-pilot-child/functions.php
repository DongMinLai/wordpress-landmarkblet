<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/shared-shell.php';
require_once __DIR__.'/commerce.php';
require_once __DIR__.'/catalog.php';
require_once __DIR__.'/smart-search.php';
require_once __DIR__.'/buyer-guide.php';
require_once __DIR__.'/contact.php';
require_once __DIR__.'/about.php';
require_once __DIR__.'/custom.php';
require_once __DIR__.'/factory.php';
require_once __DIR__.'/trust-pages.php';
require_once __DIR__.'/wholesale-hub.php';
add_action('wp_enqueue_scripts',static function(){
 wp_dequeue_style('blocksy-fonts-font-source-google');
 wp_enqueue_style('landmark-pilot-fonts',get_stylesheet_directory_uri().'/font-faces.css',array(),(string)filemtime(get_stylesheet_directory().'/font-faces.css'));
 wp_enqueue_style('landmark-pilot',get_stylesheet_uri(),array('landmark-pilot-fonts'),(string)filemtime(get_stylesheet_directory().'/style.css'));
 wp_enqueue_style('landmark-home-v2',get_stylesheet_directory_uri().'/homepage-v2.css',array('landmark-pilot'),(string)filemtime(get_stylesheet_directory().'/homepage-v2.css'));
 wp_enqueue_style('landmark-home-v3',get_stylesheet_directory_uri().'/homepage-v3.css',array('landmark-home-v2'),(string)filemtime(get_stylesheet_directory().'/homepage-v3.css'));
 wp_enqueue_style('landmark-ui-polish',get_stylesheet_directory_uri().'/ui-polish.css',array('landmark-home-v3'),(string)filemtime(get_stylesheet_directory().'/ui-polish.css'));
 wp_enqueue_style('landmark-shared-shell',get_stylesheet_directory_uri().'/shared-shell.css',array('landmark-ui-polish'),(string)filemtime(get_stylesheet_directory().'/shared-shell.css'));
 wp_enqueue_script('landmark-pilot',get_stylesheet_directory_uri().'/site.js',array(),(string)filemtime(get_stylesheet_directory().'/site.js'),true);
 wp_enqueue_style('landmark-product-cards',get_stylesheet_directory_uri().'/product-cards.css',array('landmark-ui-polish'),(string)filemtime(get_stylesheet_directory().'/product-cards.css'));
 wp_enqueue_style('landmark-product-options',get_stylesheet_directory_uri().'/product-options.css',array('landmark-product-cards'),(string)filemtime(get_stylesheet_directory().'/product-options.css'));
 wp_enqueue_script('landmark-quick-view',get_stylesheet_directory_uri().'/quick-view.js',array(),(string)filemtime(get_stylesheet_directory().'/quick-view.js'),true);
},99);
add_action('wp_enqueue_scripts', static function() {
 if (!is_page('landmark-home-v1')) return;
 wp_enqueue_style(
  'landmark-home-hero-editorial',
  get_stylesheet_directory_uri().'/homepage-hero.css',
  array('landmark-ui-polish'),
  (string) filemtime(__DIR__.'/homepage-hero.css')
 );
 wp_enqueue_style(
  'landmark-home-rhythm',
  get_stylesheet_directory_uri().'/homepage-rhythm.css',
  array('landmark-home-hero-editorial'),
  (string) filemtime(__DIR__.'/homepage-rhythm.css')
 );
}, 130);
add_filter('body_class',static function($classes){$classes[]='lm-pilot';$classes[]='lm-ui-polished';if(function_exists('lm_commerce_page')&&lm_commerce_page())$classes[]='lm-commerce';elseif(function_exists('lm_wholesale_hub_page')&&lm_wholesale_hub_page())$classes[]='lm-hub';elseif(function_exists('lm_trust_page')&&lm_trust_page()){$classes[]='lm-trust';$classes[]='lm-trust-'.lm_trust_page();}elseif(function_exists('lm_factory_page')&&lm_factory_page())$classes[]='lm-factory';elseif(function_exists('lm_custom_page')&&lm_custom_page())$classes[]='lm-custom';elseif(function_exists('lm_about_page')&&lm_about_page())$classes[]='lm-about';elseif(function_exists('lm_contact_page')&&lm_contact_page())$classes[]='lm-contact';elseif(function_exists('lm_buyer_guide_page')&&lm_buyer_guide_page())$classes[]='lm-guide';else{$classes[]='lm-home-v2';$classes[]='lm-home-v3';}return $classes;});
add_action('init',static function(){
 $file=get_stylesheet_directory().'/page-content.html';
 if(function_exists('register_block_pattern') && file_exists($file))register_block_pattern('landmarkus/wholesale-home-pilot',array('title'=>'Landmark wholesale homepage pilot','description'=>'Editable wholesale homepage preview.','categories'=>array('featured'),'content'=>file_get_contents($file)));
});
function lm_pilot_shop_url(){ $term=get_term_by('slug','mens-belts-wholesale','product_cat');return $term && !is_wp_error($term) ? get_term_link($term) : home_url('/shop/'); }
function lm_pilot_nav(){echo '<a href="'.esc_url(lm_pilot_shop_url()).'">Wholesale belts</a><a href="#shop-by-style">Shop by style</a><a href="#how-to-order">How to order</a><a href="#buying-support">Buying support</a><a class="lm-mobile-account" href="'.esc_url(wc_get_page_permalink('myaccount')).'">Log in / My account</a>';}
remove_action('woocommerce_before_shop_loop_item_title','woocommerce_show_product_loop_sale_flash',10);

function lm_pilot_card_layout($layout){return array(array('id'=>'product_image','enabled'=>true,'product_image_hover'=>'swap'),array('id'=>'landmark_product_title','enabled'=>true),array('id'=>'product_price','enabled'=>true));}
add_filter('blocksy_woo_card_options_layers:defaults','lm_pilot_card_layout',100);
add_filter('theme_mod_woo_card_layout','lm_pilot_card_layout',100);

// Human确认按条计价；只补充展示单位，不改变WooCommerce金额或规则。
add_filter('woocommerce_get_price_html',static function($html,$product){
 if($html==='')return $html;
 if(function_exists('lm_commerce_page')&&lm_commerce_page()&&class_exists('LandmarkUS_Wholesale_Order')&&!LandmarkUS_Wholesale_Order::is_wholesale_belt($product))return $html;
 return $html.' <span class="lm-price-unit">/ belt</span>';
},20,2);

require_once __DIR__.'/product-cards.php';
