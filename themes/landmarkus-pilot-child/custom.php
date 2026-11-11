<?php
defined('ABSPATH') || exit;
function lm_custom_page(){return is_page('custom-belts');}
add_filter('template_include',static function($template){return lm_custom_page()?__DIR__.'/custom-page.php':$template;},900);
add_action('wp_enqueue_scripts',static function(){if(!lm_custom_page())return;wp_enqueue_style('landmark-custom',get_stylesheet_directory_uri().'/custom.css',array('landmark-shared-shell'),(string)filemtime(__DIR__.'/custom.css'));},130);
// Candidate presentation only: keep original menu database, labels and child categories.
add_filter('landmarkus_shell_nav_url',static function($url,$item){
 if((int)$item->menu_item_parent!==0||untrailingslashit((string)wp_parse_url($url,PHP_URL_PATH))!=='/product-category/made-to-order/mens-belts')return $url;
 $page=get_page_by_path('custom-belts');return $page&&$page->post_status==='publish'&&get_post_meta($page->ID,'_lm_custom_owner',true)==='PV01'?get_permalink($page):$url;
},10,2);

/** 通用定制询盘只为本页入口预选类型，保留原有商品参考处理。 */
add_filter('wpcf7_form_tag', static function ($tag, $replace) {
 if (!$replace || !is_page('contact-us') || $tag['name'] !== 'your-subject' ||
     ($_GET['inquiry'] ?? '') !== 'custom' || isset($_GET['quote_product']) ||
     !class_exists('LandmarkUS_Inquiry') || !LandmarkUS_Inquiry::owned(WPCF7_ContactForm::get_current())) {
  return $tag;
 }

 $index = array_search('Custom project', $tag['values'], true);
 if ($index !== false) {
  $tag['options'][] = 'default:' . ($index + 1);
 }

 return $tag;
}, 10, 2);

/** 按 SKU 生成参考款询盘，复用现有商品询价参数及资格判断。 */
add_shortcode('landmark_custom_reference_inquiry', static function ($attributes) {
 $sku = shortcode_atts(array('sku' => ''), $attributes)['sku'];
 if (!lm_custom_page() || $sku === '' || !function_exists('wc_get_product_id_by_sku')) {
  return '';
 }

 $product = wc_get_product(wc_get_product_id_by_sku($sku));
 $contact = get_page_by_path('contact-us');
 if (!$product || $product->get_status() !== 'publish' || post_password_required($product->get_id()) ||
     !lm_is_custom_quote_product($product) || !$contact || $contact->post_status !== 'publish') {
  return '';
 }

 $url = add_query_arg('quote_product', $product->get_id(), get_permalink($contact)) . '#lm-name';
 return '<p class="lm-custom-reference-inquiry"><a href="' . esc_url($url) . '">Discuss ' . esc_html($product->get_sku()) . '</a></p>';
});
