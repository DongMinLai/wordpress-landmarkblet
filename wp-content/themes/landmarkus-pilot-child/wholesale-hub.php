<?php
defined('ABSPATH') || exit;
function lm_wholesale_hub_page(){return is_page('wholesale-belts');}
add_filter('template_include',static function($template){return lm_wholesale_hub_page()?__DIR__.'/wholesale-hub-page.php':$template;},900);
add_action('wp_enqueue_scripts', static function() {
 if (!lm_wholesale_hub_page()) return;
 $uri = get_stylesheet_directory_uri();
 wp_enqueue_style('landmark-wholesale-hub', $uri.'/wholesale-hub.css', array('landmark-shared-shell','landmark-product-cards'), (string) filemtime(__DIR__.'/wholesale-hub.css'));
 wp_enqueue_script('landmark-wholesale-hub-carousel', $uri.'/wholesale-hub-carousel.js', array(), (string) filemtime(__DIR__.'/wholesale-hub-carousel.js'), true);
}, 130);

/** 批发页已有单条计价说明，价格提示只说明当前访问方式。 */
add_shortcode('landmark_hub_prices', static function() {
 if (!class_exists('LandmarkUS_Price_Access')) return '';
 if (LandmarkUS_Price_Access::mode() === 'public' || is_user_logged_in()) {
  return '<p class="lm-hub-price-note">See prices in the catalog and on product pages.</p>';
 }
 $url = LandmarkUS_Price_Access::login_url(get_permalink(get_queried_object_id()));
 return '<p class="lm-hub-price-note">Browse without an account. <a href="'.esc_url($url).'">Log in for wholesale prices</a>.</p>';
});
// Candidate menu presentation only. Catalog URLs, query, canonical and menu database remain native.
add_filter('landmarkus_shell_nav_url',static function($url,$item){
 if((int)$item->menu_item_parent!==0||!in_array(untrailingslashit((string)wp_parse_url($url,PHP_URL_PATH)),array('/product-category/mens-belts-wholesale','/product-category/wholesale/mens-belts-wholesale','/product-category/wholesale'),true))return $url;
 $p=get_page_by_path('wholesale-belts');return $p&&$p->post_status==='publish'&&get_post_meta($p->ID,'_lm_wholesale_hub_owner',true)==='PV01'?get_permalink($p):$url;
},10,2);

/** Hub 独用的颜色图片。先批量预热变体和附件，再复用商品页的真实颜色映射。 */
function lm_hub_carousel_prime_photos($products) {
 $variation_ids = array();
 $attachment_ids = array();
 foreach ($products as $product) {
  $variation_ids = array_merge($variation_ids, $product->get_children());
  $attachment_ids = array_merge($attachment_ids, array($product->get_image_id()), $product->get_gallery_image_ids());
 }
 _prime_post_caches($variation_ids, false, true);
 foreach ($variation_ids as $id) {
  $attachment_ids[] = (int) get_post_meta($id, '_thumbnail_id', true);
 }
 _prime_post_caches(array_values(array_unique(array_filter($attachment_ids))), false, true);
}

function lm_hub_carousel_color_photos($product) {
 return lm_color_images($product, static function($id, $inherit) use ($product) {
  $post = get_post($id);
  if (!$post || $post->post_type !== 'product_variation' || (int) $post->post_parent !== $product->get_id()) {
   return array('', 0);
  }
  $color = wc_get_product_variation_attributes($id)['attribute_pa_color'] ?? '';
  $image = (int) get_post_meta($id, '_thumbnail_id', true);
  return array($color, $inherit && !$image ? (int) $product->get_image_id() : $image);
 });
}

function lm_hub_carousel_swatches($product, $names) {
 $photos = lm_hub_carousel_color_photos($product);
 $colors = array_values($product->get_variation_attributes()['pa_color'] ?? array());
 $primary = array_search((int) $product->get_image_id(), $photos, true);
 if (is_string($primary) && in_array($primary, $colors, true)) {
  $colors = array_merge(array($primary), array_diff($colors, array($primary)));
 }

 $choices = array();
 foreach ($colors as $color) {
  if (!isset($photos[$color])) continue;
  $thumb = wp_get_attachment_image_src($photos[$color], 'woocommerce_gallery_thumbnail');
  if (!$thumb || !$thumb[3] || max($thumb[1], $thumb[2]) > 100) continue;
  $preview = wp_get_attachment_image_src($photos[$color], 'medium_large');
  if (!$preview) continue;
  $choices[] = array(
   'name' => $names[$color] ?? $color,
   'thumb' => $thumb[0],
   'preview' => $preview[0],
   'primary' => $color === $primary,
  );
  if (count($choices) === 4) break;
 }
 if (!$choices) return;

 echo '<div class="lm-hub-swatches" role="group" aria-label="Color previews" hidden>';
 foreach ($choices as $choice) {
  echo '<button type="button" class="lm-hub-swatch" aria-pressed="'.($choice['primary'] ? 'true' : 'false').'"';
  echo ' aria-label="'.esc_attr($choice['name']).'" title="'.esc_attr($choice['name']).'"';
  echo ' data-thumb="'.esc_url($choice['thumb']).'" data-preview="'.esc_url($choice['preview']).'">';
  echo '</button>';
 }
 $remaining = count($colors) - count($choices);
 if ($remaining > 0) {
  echo '<a class="lm-hub-more-colors" href="'.esc_url($product->get_permalink()).'" aria-label="'.esc_attr(sprintf('See all %d colors', count($colors))).'">+'.esc_html($remaining).'</a>';
 }
 echo '</div>';
}

add_shortcode('landmark_hub_carousel', static function($attributes) {
 if (!lm_wholesale_hub_page() || !shortcode_exists('products')) return '';
 $attributes = shortcode_atts(array('ids' => '', 'group' => 'featured', 'dress' => '', 'golf' => ''), $attributes);
 $groups = array(
  'featured' => array('label' => 'Featured belts', 'term' => 0),
  'dress' => array('label' => 'Dress belts', 'term' => 264),
  'golf' => array('label' => 'Golf belts', 'term' => 283),
 );
 $group = isset($groups[$attributes['group']]) ? $attributes['group'] : 'featured';
 $section = $groups[$group];
 $dress = wp_parse_id_list($attributes['dress']);
 $golf = wp_parse_id_list($attributes['golf']);
 $ids = array_slice(array_values(array_unique(array_filter(array_merge(wp_parse_id_list($attributes['ids']), $dress, $golf)))), 0, 8);
 $products = array();
 $color_slugs = array();
 foreach ($ids as $id) {
  $product = wc_get_product($id);
  if (!$product || $product->get_status() !== 'publish' || !$product->is_visible()) continue;
  if ($section['term'] && !in_array($section['term'], $product->get_category_ids(), true)) continue;
  $products[$id] = $product;
  $color_slugs = array_merge($color_slugs, array_values($product->get_variation_attributes()['pa_color'] ?? array()));
 }
 if (!$products) return '';

 lm_hub_carousel_prime_photos($products);
 $names = array();
 if ($color_slugs) {
  $terms = get_terms(array('taxonomy' => 'pa_color', 'slug' => array_unique($color_slugs), 'hide_empty' => false));
  if (!is_wp_error($terms)) {
   foreach ($terms as $term) $names[$term->slug] = $term->name;
  }
 }

 $extras = static function($layer) use ($dress, $golf, $names, $section) {
  global $product;
  if ($layer['id'] !== 'landmark_product_title' || !$product instanceof WC_Product) return;
  $id = $product->get_id();
  $categories = $product->get_category_ids();
  $label = '';
  if ($section['term']) $label = $section['label'];
  elseif (in_array($id, $dress, true) && in_array(264, $categories, true)) $label = 'Dress belts';
  elseif (in_array($id, $golf, true) && in_array(283, $categories, true)) $label = 'Golf belts';
  else {
   foreach (array(263 => 'Casual belts', 264 => 'Dress belts', 267 => 'Ratchet belts', 268 => 'Reversible belts', 269 => 'Stretch belts') as $term => $name) {
    if (!in_array($term, $categories, true)) continue;
    $label = $name;
    break;
   }
  }
  if ($label && !$section['term']) echo '<span class="lm-hub-card-type">'.esc_html($label).'</span>';
  lm_hub_carousel_swatches($product, $names);
 };
 add_action('blocksy:woocommerce:product-card:custom:layer', $extras, 20);
 try {
  $cards = do_shortcode('[products ids="'.implode(',', array_keys($products)).'" columns="4" limit="8" orderby="post__in" order="ASC"]');
 } finally {
  remove_action('blocksy:woocommerce:product-card:custom:layer', $extras, 20);
 }

 $chevron = static function($direction) {
  $path = $direction === 'previous' ? 'M14 5l-7 7 7 7' : 'M10 5l7 7-7 7';
  return '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="'.$path.'"/></svg>';
 };
 $track_id = wp_unique_id('hub-'.$group.'-track-');
 return '<div class="lm-hub-carousel" data-lm-hub-group="'.esc_attr($group).'">'
  .'<div class="lm-hub-carousel-controls" role="group" aria-label="'.esc_attr($section['label'].' carousel controls').'" hidden>'
  .'<span class="lm-hub-carousel-position" role="status"></span>'
  .'<button type="button" data-lm-hub-move="-1" aria-label="Previous belt" aria-controls="'.esc_attr($track_id).'">'.$chevron('previous').'</button>'
  .'<button type="button" data-lm-hub-move="1" aria-label="Next belt" aria-controls="'.esc_attr($track_id).'">'.$chevron('next').'</button>'
  .'</div><div class="lm-hub-viewport" id="'.esc_attr($track_id).'" tabindex="0" role="region" aria-label="'.esc_attr($section['label']).'">'
  .$cards.'</div></div>';
});
