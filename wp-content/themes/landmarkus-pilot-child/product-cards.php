<?php
/** 本地预览商品卡与只读快速查看；价格权限仍由LandmarkUS Core管理。 */
defined('ABSPATH') || exit;
require_once __DIR__.'/product-options.php';

function lm_pilot_card_name($product) {
 $name=$product->get_name();$sku=$product->get_sku();
 return $sku ? preg_replace('/(?:\s*\|\s*|\s+)'.preg_quote($sku,'/').'\s*$/iu','',$name) : $name;
}
function lm_pilot_card_gallery($product) {
 $ids=array_unique(array_map('absint',array_merge(array($product->get_image_id()),$product->get_gallery_image_ids())));
 return array_values(array_filter($ids,static function($id){return $id && wp_attachment_is_image($id);}));
}
function lm_pilot_card_options($product) { return lm_pilot_option_values($product); }
add_action('blocksy:woocommerce:product-card:custom:layer',static function($layer){
 if($layer['id']!=='landmark_product_title')return;
 global $product;
 if(!$product instanceof WC_Product)return;
 $options=lm_pilot_card_options($product);
 echo '<div class="lm-card-meta"><p class="lm-sku">'.esc_html($product->get_sku()).'</p>';
 if(isset($options['Colors'])){
  $count=count($options['Colors']);echo '<p class="lm-card-options">'.esc_html($count.' '.($count===1?'color':'colors')).'</p>';
 }
 echo '</div><h2 class="woocommerce-loop-product__title"><a href="'.esc_url($product->get_permalink()).'">'.esc_html(lm_pilot_card_name($product)).'</a></h2>';
});
add_filter('woocommerce_loop_add_to_cart_link',static function($link,$product){
 return ''; // Human：卡片已可点击，不再增加重复详情动作。
},20,2);
add_action('blocksy:woocommerce:product-card:thumbnail:end',static function(){
 global $product;
 if(!$product instanceof WC_Product || $product->get_status()!=='publish' || !$product->is_visible())return;
 $GLOBALS['lm_pilot_preview_products'][$product->get_id()]=$product;
 echo '<button hidden type="button" class="lm-quick-view" data-lm-product="'.esc_attr($product->get_id()).'" aria-haspopup="dialog" aria-controls="lm-quick-view-dialog" aria-expanded="false" aria-label="'.esc_attr('Quick view: '.lm_pilot_card_name($product)).'">';
 echo '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><span>Quick view</span></button>';
});
add_action('wp_footer',static function(){
 $products=isset($GLOBALS['lm_pilot_preview_products'])?$GLOBALS['lm_pilot_preview_products']:array();
 if(!$products)return;
 echo '<dialog id="lm-quick-view-dialog" class="lm-qv" aria-labelledby="lm-qv-title"><button type="button" class="lm-qv-close" aria-label="Close quick view"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg></button><div class="lm-qv-content"></div></dialog>';
 foreach($products as $product){
  $id=$product->get_id();$images=lm_pilot_card_gallery($product);$count=count($images);$name=lm_pilot_card_name($product);
  echo '<template id="lm-qv-product-'.esc_attr($id).'"><div class="lm-qv-layout" data-product-id="'.esc_attr($id).'">';
  echo '<div class="lm-qv-gallery"><div class="lm-qv-image">';
  if($images){echo wp_get_attachment_image($images[0],'large',false,array('class'=>'lm-qv-main-image','loading'=>'eager','alt'=>$name.' — photo 1','sizes'=>'(max-width: 760px) 90vw, 500px'));}
  else{echo wc_placeholder_img('large',array('class'=>'lm-qv-main-image','alt'=>'Product image unavailable'));}
  echo '</div>';
  if($count>1){
   echo '<div class="lm-qv-thumbnails" role="group" aria-label="Product photos">';
   foreach($images as $index=>$image_id){
    $src=wp_get_attachment_image_src($image_id,'large');if(!$src)continue;
    $srcset=wp_get_attachment_image_srcset($image_id,'large');
    echo '<button type="button" class="lm-qv-thumbnail" tabindex="'.($index===0?'0':'-1').'" aria-pressed="'.($index===0?'true':'false').'" aria-label="'.esc_attr('View photo '.($index+1).' of '.$count).'" data-index="'.esc_attr($index).'" data-src="'.esc_url($src[0]).'" data-srcset="'.esc_attr($srcset?:'').'" data-alt="'.esc_attr($name.' — photo '.($index+1)).'">';
    echo wp_get_attachment_image($image_id,'thumbnail',false,array('loading'=>'lazy','alt'=>''));echo '</button>';
   }
   echo '</div>';
  }
  echo '<p class="lm-qv-photo-status" role="status" aria-live="polite">'.esc_html($count?'Photo 1 of '.$count:'Product photo unavailable').'</p></div>';
  echo '<div class="lm-qv-summary"><p class="lm-qv-sku">'.esc_html($product->get_sku()).'</p><h2 id="lm-qv-title">'.esc_html($name).'</h2>';
  // 不读取/序列化原始金额：继续使用全站权限过滤后的原生价格HTML。
  echo '<div class="lm-qv-price">'.wp_kses_post($product->get_price_html()).'</div>';
  $options=lm_pilot_card_options($product);
  if($options)echo '<div class="lm-qv-options">'.lm_pilot_options_markup($options).'</div>';
  if(class_exists('LandmarkUS_Wholesale_Order')&&LandmarkUS_Wholesale_Order::is_wholesale_belt($product))echo '<p class="lm-qv-order-note">Wholesale orders start at 12 belts in total. Mix styles to suit your store.</p>';
  echo '<a class="lm-qv-details" href="'.esc_url($product->get_permalink()).'">View full details</a></div></div></template>';
 }
},5);
