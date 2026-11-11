<?php
defined('ABSPATH') || exit;
function lm_contact_page(){return is_page('contact-us');}
add_filter('template_include',static function($template){return lm_contact_page()?__DIR__.'/contact-page.php':$template;},900);
add_action('wp_enqueue_scripts',static function(){if(!lm_contact_page())return;wp_enqueue_style('landmark-contact',get_stylesheet_directory_uri().'/contact.css',array('landmark-shared-shell'),(string)filemtime(__DIR__.'/contact.css'));wp_enqueue_script('landmark-contact',get_stylesheet_directory_uri().'/contact.js',array(),(string)filemtime(__DIR__.'/contact.js'),true);},130);

/** 只预填本地候选的自有询盘表单，商品参考从已发布的商品读取。 */
add_filter('wpcf7_form_tag', static function ($tag, $replace) {
 if (!$replace || !lm_contact_page() || !in_array($tag['name'], array('your-subject', 'product-reference'), true)) return $tag;
 if (!class_exists('LandmarkUS_Inquiry') || !LandmarkUS_Inquiry::owned(WPCF7_ContactForm::get_current())) return $tag;

 $id = $_GET['quote_product'] ?? '';
 if (!is_string($id) || !ctype_digit($id) || get_post_status((int) $id) !== 'publish' || post_password_required((int) $id)) return $tag;

 $product = wc_get_product((int) $id);
 if (!lm_is_custom_quote_product($product)) return $tag;

 if ($tag['name'] === 'your-subject') {
  $index = array_search('Custom project', $tag['values'], true);
  if ($index !== false) $tag['options'][] = 'default:' . ($index + 1);
 } else {
  $tag['values'] = array($product->get_sku() ?: $product->get_permalink());
 }
 return $tag;
}, 10, 2);
