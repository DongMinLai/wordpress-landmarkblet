<?php
/** 原生WooCommerce页面的Landmark呈现与图片选项。 */
defined('ABSPATH') || exit;
function lm_commerce_page(){return function_exists('is_woocommerce')&&(is_woocommerce()||is_cart()||is_checkout()||is_account_page()||(is_search()&&get_query_var('post_type')==='product'));}
add_action('after_setup_theme',static function(){
 remove_action('woocommerce_checkout_terms_and_conditions','wc_terms_and_conditions_page_content',30);
 add_filter('woocommerce_show_page_title','__return_false');
 add_theme_support('woocommerce');add_theme_support('wc-product-gallery-zoom');add_theme_support('wc-product-gallery-lightbox');add_theme_support('wc-product-gallery-slider');
 if(class_exists('WPCleverWpcvs')){remove_filter('woocommerce_dropdown_variation_attribute_options_html',array(WPCleverWpcvs::instance(),'variation_attribute_options_html'),199);remove_filter('woocommerce_product_get_default_attributes',array(WPCleverWpcvs::instance(),'get_default_attributes'),199);}
});
add_action('wp_enqueue_scripts',static function(){
 if(!lm_commerce_page())return;
 wp_dequeue_style('wpcvs-frontend');wp_dequeue_script('wpcvs-frontend');
 wp_enqueue_style('landmark-commerce',get_stylesheet_directory_uri().'/commerce.css',array('landmark-shared-shell'),(string)filemtime(__DIR__.'/commerce.css'));
 $deps=array('jquery');if(is_product()){$deps[]='wc-add-to-cart-variation';wp_enqueue_script('wc-add-to-cart-variation');}
 wp_enqueue_script('landmark-commerce',get_stylesheet_directory_uri().'/commerce.js',$deps,(string)filemtime(__DIR__.'/commerce.js'),true);
},100);
add_filter('template_include',static function($template){if(is_cart()||is_checkout()||is_account_page())return __DIR__.'/commerce-page.php';return $template;},999);

/** 定制目录中未配置价格的商品走询价入口。 */
function lm_is_custom_quote_product($product) {
 if (!$product instanceof WC_Product || !$product->is_type(array('simple', 'variable')) || $product->get_price('edit') !== '') return false;
 if ($product->is_type('variable') && $product->get_variation_price('min', false) !== '') return false;
 if (class_exists('LandmarkUS_Wholesale_Order') && LandmarkUS_Wholesale_Order::is_wholesale_belt($product)) return false;

 $root = get_term_by('slug', 'made-to-order', 'product_cat');
 $terms = get_the_terms($product->get_id(), 'product_cat');
 if (!$root || !is_array($terms)) return false;

 foreach ($terms as $term) {
  if ($term->term_id === $root->term_id || term_is_ancestor_of($root, $term, 'product_cat')) return true;
 }
 return false;
}

add_filter('blocksy:woocommerce:product-single:layout', static function ($layout) {
 if (!is_product() || !lm_is_custom_quote_product(wc_get_product(get_queried_object_id()))) return $layout;

 foreach ($layout as &$layer) {
  if ($layer['id'] === 'product_price') {
   $layer['id'] = 'lm_custom_quote';
   $layer['enabled'] = true;
  } elseif ($layer['id'] === 'product_add_to_cart') {
   $layer['enabled'] = false;
  }
 }
 unset($layer);
 return $layout;
});

add_action('blocksy:woocommerce:product:custom:layer', static function ($layer) {
 if ($layer['id'] !== 'lm_custom_quote') return;

 $contact = get_page_by_path('contact-us');
 if (!$contact || $contact->post_status !== 'publish') return;

 $url = add_query_arg('quote_product', get_queried_object_id(), get_permalink($contact)) . '#lm-name';
 echo '<a class="button lm-product-quote" href="' . esc_url($url) . '">Request a Quote</a>';
});

function lm_color_images($product, $read_variation = null) {
 static $cache = array();
 $id = $product->get_id();
 $use_cache = $read_variation === null;
 if ($use_cache && isset($cache[$id])) return $cache[$id];

 // 普通商品页保持 WooCommerce 变体对象读取。目录可传入已批量预热的图片数据。
 $read_variation = $read_variation ?? static function($id, $inherit) {
  $variation = wc_get_product($id);
  if (!$variation) return array('', 0);
  $color = $variation->get_variation_attributes()['attribute_pa_color'] ?? '';
  return array($color, (int) $variation->get_image_id($inherit ? 'view' : 'edit'));
 };
 $images = array();
 $children = $product->get_children();
 foreach ($children as $child) {
  list($color, $image) = $read_variation($child, false);
  if ($color && $image && !isset($images[$color]) && wp_attachment_is_image($image)) {
   $images[$color] = $image;
  }
 }

 $all = $product->get_variation_attributes()['pa_color'] ?? array();
 $missing = array_values(array_diff($all, array_keys($images)));
 $parent_image = (int) $product->get_image_id();
 if (count($missing) === 1 && ($images || count($all) === 1) && $parent_image && !in_array($parent_image, $images, true)) {
  foreach ($children as $child) {
   list($color, $image) = $read_variation($child, true);
   if ($color === $missing[0] && $image === $parent_image) {
    $images[$color] = $parent_image;
    break;
   }
  }
 }
 // GB-01双面颜色与文件名缩写已逐张目视核对，精确映射仍限定本商品图库。
 $map_file=__DIR__.'/color-image-map.json';$map=is_file($map_file)?json_decode(file_get_contents($map_file),true):array();$refs=$map[$product->get_sku()]??array();
 foreach($refs as $color=>$filename){if(isset($images[$color])||!in_array($color,$all,true))continue;foreach(array_unique(array_merge(array($parent_image),$product->get_gallery_image_ids())) as $image){if(wp_basename((string)get_post_meta($image,'_wp_attached_file',true))===$filename&&wp_attachment_is_image($image)){$images[$color]=(int)$image;break;}}}
 // 仅接受同SKU图库中“SKU_颜色_1_...”的精确文件命名证据，不做颜色相似推断。
 foreach($all as $color){if(isset($images[$color])||!preg_match('/^[a-z]+(?:-[a-z]+)*$/D',$color))continue;$matches=array();$prefix=strtolower($product->get_sku().'_'.str_replace('-','_',$color).'_1_');
  foreach(array_unique(array_merge(array($parent_image),$product->get_gallery_image_ids())) as $image){$file=wp_basename((string)get_post_meta($image,'_wp_attached_file',true));if(strpos(strtolower($file),$prefix)===0&&wp_attachment_is_image($image))$matches[]=(int)$image;}
  if(count($matches)===1)$images[$color]=$matches[0];
 }
 if ($use_cache) $cache[$id] = $images;
 return $images;
}
add_filter('woocommerce_available_variation',static function($data,$product,$variation){
 $color=$variation->get_variation_attributes()['attribute_pa_color']??'';$images=lm_color_images($product);
 if($color&&isset($images[$color])){$data['image_id']=$images[$color];$data['image']=wc_get_product_attachment_props($images[$color]);}
 return $data; // 只更新图片；金额/可购权限继续由Core末位过滤器治理。
},200,3);
function lm_attribute_name($attribute,$value){if(taxonomy_exists($attribute)){$term=get_term_by('slug',$value,$attribute);if($term)return $term->name;}return $value;}
add_filter('woocommerce_dropdown_variation_attribute_options_html',static function($html,$args){
 if(!is_product()||!in_array($args['attribute'],array('pa_color','pa_size'),true))return $html;
 $product=$args['product'];if(!$product)return $html;$attribute=$args['attribute'];$options=$args['options']?:($product->get_variation_attributes()[$attribute]??array());$images=$attribute==='pa_color'?lm_color_images($product):array();
 $out='<div class="lm-choice-field" data-attribute="'.esc_attr($attribute).'">'.$html.'<div class="lm-choice-buttons '.($attribute==='pa_color'?'lm-color-choices':'lm-size-choices').'" role="group" aria-label="'.esc_attr(wc_attribute_label($attribute)).'" hidden>';
 foreach($options as $value){$name=lm_attribute_name($attribute,$value);$image=$images[$value]??0;$large=$image?wp_get_attachment_image_src($image,'woocommerce_single'):false;
  $out.='<button type="button" class="lm-choice '.($image?'lm-choice-image':'lm-choice-text').'" data-value="'.esc_attr($value).'" aria-pressed="false"'.($attribute==='pa_color'?' aria-label="'.esc_attr($name).'" data-color-name="'.esc_attr($name).'"':'').($large?' data-image-src="'.esc_url($large[0]).'" data-image-srcset="'.esc_attr(wp_get_attachment_image_srcset($image,'woocommerce_single')?:'').'"':'').'>';
  if($image)$out.=wp_get_attachment_image($image,'woocommerce_thumbnail',false,array('alt'=>'','loading'=>'lazy'));
  $out.='<span'.($attribute==='pa_color'&&$image?' class="lm-color-name"':'').'>'.esc_html($name).'</span></button>';
 }
 $out.='</div>';if($attribute==='pa_color')$out.='<span class="lm-color-tooltip" role="tooltip" id="lm-color-tooltip-'.esc_attr($product->get_id()).'" hidden></span><p class="lm-selected-color" aria-live="polite" hidden></p>';
 return $out.'</div>';
},1000,2);
add_action('woocommerce_after_variations_table', static function () {
 if (!is_product()) return;
 // 在规格表读完时初始化选项，避免等待页尾的 WooCommerce 脚本。
 $file = __DIR__ . '/product-choices.js';
 if (is_readable($file)) wp_print_inline_script_tag(file_get_contents($file), array('id' => 'landmark-product-choices'));
});

add_action('woocommerce_single_product_summary',static function(){global $product;if($product&&class_exists('LandmarkUS_Wholesale_Order')&&LandmarkUS_Wholesale_Order::is_wholesale_belt($product))echo '<p class="lm-pdp-rule">12 belts per order. Mixed styles welcome. Prices are per belt.</p>';},25);
add_action('woocommerce_after_add_to_cart_form', static function() {
 global $product;
 if (!$product || !class_exists('LandmarkUS_Wholesale_Order') ||
     !LandmarkUS_Wholesale_Order::is_wholesale_belt($product) || !$product->is_type('variable')) return;
 $rows = LandmarkUS_Wholesale_Order::variation_rows($product);
 if (!$rows) return;

 $groups = array();
 foreach ($rows as $row) {
  $color = $row['attributes']['attribute_pa_color'] ?? '';
  $options = array();
  foreach ($row['attributes'] as $key => $value) {
   if ($key !== 'attribute_pa_color') $options[] = lm_attribute_name(substr($key, 10), $value);
  }
  $row['option_label'] = implode(' / ', $options);
  $groups[$color][] = $row;
 }

 echo '<details class="lm-bulk-order"><summary><span class="lm-bulk-heading">Bulk order</span><span class="lm-bulk-count">'.esc_html(count($rows).' variants').'</span></summary><div class="lm-bulk-panel">';
 if (!LandmarkUS_Wholesale_Order::price_allowed()) {
  echo '<p class="lm-bulk-intro"><a class="lm-price-login" href="'.esc_url(LandmarkUS_Price_Access::login_url($product->get_permalink())).'">Log in for wholesale prices and ordering</a></p>';
  echo '<dl class="lm-bulk-preview" aria-label="Wholesale variants by color">';
  foreach ($groups as $color => $group) {
   $label = $color ? lm_attribute_name('pa_color', $color) : 'Options';
   echo '<div><dt>'.esc_html($label).'</dt><dd><ul role="list">';
   foreach ($group as $row) {
    echo '<li data-variant="'.esc_attr($row['product']->get_id()).'">'.esc_html($row['option_label']).'</li>';
   }
   echo '</ul></dd></div>';
  }
  echo '</dl></div></details>';
  return;
 }

 $draft = WC()->session ? WC()->session->get('landmarkus_bulk_draft_'.$product->get_id(), array()) : array();
 echo '<p class="lm-bulk-intro">Enter quantities by variant or use batch fill. All entered quantities go to your cart, where you can review shipping, tax, and totals.</p>';
 echo '<form class="lm-bulk-form" action="'.esc_url(admin_url('admin-post.php')).'" method="post" data-ajax-url="'.esc_url(WC_AJAX::get_endpoint('landmarkus_bulk_add')).'">';
 echo '<input type="hidden" name="action" value="landmarkus_bulk_add"><input type="hidden" name="product_id" value="'.esc_attr($product->get_id()).'"><input type="hidden" name="request_id" value="'.esc_attr(wp_generate_uuid4()).'">';
 wp_nonce_field('landmarkus_bulk_'.$product->get_id(), 'lm_bulk_nonce', false);

 echo '<div class="lm-bulk-tools" hidden><div class="lm-bulk-tools-heading"><p class="lm-bulk-tools-title">Batch fill</p><p class="lm-bulk-selection">0 variants selected for batch fill</p></div>';
 echo '<label class="lm-select-all"><input type="checkbox" class="lm-bulk-select-all"> Select all available variants</label>';
 echo '<div class="lm-bulk-fill"><label>Quantity per selected variant<input type="number" class="lm-bulk-fill-value" min="0" step="1" inputmode="numeric" value=""></label><button type="button" class="lm-bulk-apply">Apply</button><button type="button" class="lm-bulk-undo" disabled>Undo</button><button type="button" class="lm-bulk-clear">Clear</button></div>';
 echo '<div class="lm-bulk-confirm" hidden><p>Replace the quantities already entered in the selected variants?</p><button type="button" data-confirm="yes">Replace quantities</button><button type="button" data-confirm="no">Cancel</button></div></div>';

 echo '<div class="lm-bulk-table-scroll"><table class="lm-bulk-table"><caption class="screen-reader-text">Wholesale variant quantities</caption><thead><tr>';
 echo '<th scope="col"><span class="screen-reader-text">Select</span><svg class="lm-bulk-select-icon" aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.3"><rect x="2" y="2" width="12" height="12" rx="1"/><path d="m5 8 2 2 4-4"/></svg></th>';
 echo '<th scope="col">Size / options</th><th scope="col">Price / belt</th><th scope="col">Quantity</th></tr></thead>';
 foreach ($groups as $color => $group) {
  $label = $color ? lm_attribute_name('pa_color', $color) : 'Options';
  echo '<tbody data-color="'.esc_attr($color).'"><tr class="lm-bulk-color"><th colspan="4" scope="rowgroup"><label><input type="checkbox" class="lm-bulk-select-group" hidden> '.esc_html($label).'</label></th></tr>';
  foreach ($group as $row) {
   $v = $row['product'];
   $id = $v->get_id();
   $can = $row['complete'] && $v->variation_is_active() && $v->is_in_stock() && $v->is_purchasable();
   $options = $row['option_label'];
   $description = $label.' / '.$options;
   $disabled = $can ? '' : ' disabled';
   echo '<tr data-variant="'.esc_attr($id).'"><td><input type="checkbox" class="lm-bulk-select" aria-label="'.esc_attr('Select '.$description).'" hidden'.$disabled.'></td>';
   echo '<th scope="row">'.esc_html($options).($can ? '' : '<small>Unavailable</small>').'</th>';
   echo '<td>'.wp_kses_post($v->get_price_html()).'</td>';
   echo '<td><label class="screen-reader-text" for="lm-qty-'.esc_attr($id).'">'.esc_html('Quantity for '.$description).'</label><input id="lm-qty-'.esc_attr($id).'" class="lm-bulk-qty" name="quantities['.esc_attr($id).']" type="number" min="0" step="1" inputmode="numeric" value="'.esc_attr($draft[$id] ?? '').'"'.$disabled.'></td></tr>';
  }
  echo '</tbody>';
 }
 echo '</table></div>';
 echo '<div class="lm-bulk-actions"><p class="lm-bulk-total" role="status" aria-live="polite">0 belts across 0 variants</p><button class="button lm-bulk-submit" type="submit">Add quantities to cart</button></div><div class="lm-bulk-feedback" tabindex="-1" hidden><p class="lm-bulk-status" role="status" aria-live="polite" aria-atomic="true"></p><a class="lm-bulk-cart-link lm-bulk-success-link" href="'.esc_url(wc_get_cart_url()).'" hidden>View cart</a></div></form>';
 echo '</div></details>';
}, 20);
