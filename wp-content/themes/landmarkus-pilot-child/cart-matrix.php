<?php
/** Color and size matrix for the classic wholesale cart. */
defined('ABSPATH') || exit;

add_action('woocommerce_before_template_part', static function ($name, $path, $located) {
 if ($name !== 'cart/cart.php' || !is_cart() ||
     wp_normalize_path($located) !== wp_normalize_path(get_template_directory().'/woocommerce/cart/cart.php')) return;

 // Keep Blocksy's cart form, coupons, totals, and hooks. Replace only the rows
 // whose full variation data can be represented by a color and a size.
 add_action('woocommerce_before_cart_contents', 'lm_cart_render_style_rows', 100);
}, 20, 3);

add_action('woocommerce_after_template_part', static function ($name) {
 if ($name === 'cart/cart.php') {
  remove_action('woocommerce_before_cart_contents', 'lm_cart_render_style_rows', 100);
 }
});

add_action('wp_enqueue_scripts', static function () {
 if (function_exists('is_cart') && is_cart()) {
  wp_enqueue_style('landmark-cart-matrix', get_stylesheet_directory_uri().'/cart-matrix.css',
   array('landmark-commerce'), (string) filemtime(__DIR__.'/cart-matrix.css'));
 }
}, 110);

function lm_cart_render_style_rows() {
 $cart = WC()->cart;
 $groups = array();
 $ambiguous = array();

 foreach ($cart->get_cart() as $key => $item) {
  $attributes = $item['variation'] ?? array();
  $color = $attributes['attribute_pa_color'] ?? '';
  $size = $attributes['attribute_pa_size'] ?? '';
  if (!$color || !$size || count($attributes) !== 2 || $item['quantity'] <= 0) continue;
  if (!apply_filters('woocommerce_cart_item_visible', true, $item, $key)) continue;

  $variation = apply_filters('woocommerce_cart_item_product', $item['data'], $item, $key);
  $id = (int) $item['product_id'];
  if (!$variation instanceof WC_Product_Variation || !$variation->exists() || $variation->get_parent_id() !== $id) continue;

  $parent = $groups[$id]['parent'] ?? wc_get_product($id);
  if (!$parent || !$parent->is_type('variable') || !class_exists('LandmarkUS_Wholesale_Order') ||
      !LandmarkUS_Wholesale_Order::is_wholesale_belt($parent)) continue;

  // Duplicate choices and custom item metadata need the original line items.
  if (isset($groups[$id]['colors'][$color][$size]) ||
      apply_filters('woocommerce_get_item_data', array(), $item)) {
   $ambiguous[$id] = true;
   continue;
  }

  $groups[$id]['parent'] = $parent;
  $groups[$id]['colors'][$color][$size] = array(
   'key' => $key,
   'item' => $item,
   'product' => $variation,
   'price' => apply_filters('woocommerce_cart_item_price', $cart->get_product_price($variation), $item, $key),
  );
 }

 $rendered = array();
 foreach (array_diff_key($groups, $ambiguous) as $group) {
  lm_cart_style_section($group, $cart);
  foreach ($group['colors'] as $sizes) {
   foreach ($sizes as $line) {
    $rendered[$line['key']] = true;
   }
  }
 }

 if (!$rendered) return;
 $skip_rendered = static function ($visible, $item, $key) use ($rendered) {
  return $visible && !isset($rendered[$key]);
 };
 add_filter('woocommerce_cart_item_visible', $skip_rendered, PHP_INT_MAX, 3);
 $restore = static function () use ($skip_rendered, &$restore) {
  remove_filter('woocommerce_cart_item_visible', $skip_rendered, PHP_INT_MAX);
  remove_action('woocommerce_cart_contents', $restore, PHP_INT_MIN);
 };
 add_action('woocommerce_cart_contents', $restore, PHP_INT_MIN);
}

function lm_cart_style_section($group, $cart) {
 $parent = $group['parent'];
 $colors = $group['colors'];
 $id = $parent->get_id();
 $sku = $parent->get_sku();
 $title = lm_pilot_card_name($parent);
 $images = lm_color_images($parent);
 $size_labels = array();
 $prices = array();
 $quantity = 0;
 $subtotal = 0;

 foreach ($colors as $sizes) {
  foreach ($sizes as $size => $line) {
   $item = $line['item'];
   $size_labels[$size] = lm_attribute_name('pa_size', $size);
   $quantity += $item['quantity'];
   // Use WooCommerce's unrounded line subtotal data and cart tax display mode.
   $subtotal += $item['line_subtotal'] + ($cart->display_prices_including_tax() ? $item['line_subtotal_tax'] : 0);
   $prices[] = $line['price'];
  }
 }
 uasort($size_labels, 'strnatcasecmp');
 $color_order = $parent->get_variation_attributes()['pa_color'] ?? array();
 $color_order = array_unique(array_merge($color_order, array_keys($colors)));
 $shared_price = count(array_unique($prices)) === 1 ? reset($prices) : '';
 $photo = $parent->get_image_id() ? wp_get_attachment_image($parent->get_image_id(), 'woocommerce_gallery_thumbnail', false, array('alt' => '')) : '';
 ?>
 <tr class="lm-cart-matrix-row">
  <td class="lm-cart-matrix-cell" colspan="5">
   <section class="lm-cart-style" aria-labelledby="lm-cart-style-<?php echo esc_attr($id); ?>">
    <header class="lm-cart-style-head">
     <?php if ($photo) { ?><div class="lm-cart-style-photo"><?php echo $photo; // WordPress attachment markup. ?></div><?php } ?>
     <div class="lm-cart-style-info">
      <?php if ($sku) { ?><span class="lm-cart-style-sku"><?php echo esc_html($sku); ?></span><?php } ?>
      <h2 id="lm-cart-style-<?php echo esc_attr($id); ?>">
       <?php if ($parent->is_visible()) { ?>
        <a href="<?php echo esc_url($parent->get_permalink()); ?>"><?php echo esc_html($title); ?></a>
       <?php } else { echo esc_html($title); } ?>
      </h2>
      <?php if ($shared_price) { ?><span class="lm-cart-unit-price"><?php echo wp_kses_post($shared_price); ?> / belt</span><?php } ?>
     </div>
     <div class="lm-cart-style-summary">
      <strong class="lm-cart-style-subtotal"><?php echo wp_kses_post(wc_price($subtotal)); ?></strong>
      <span class="lm-cart-style-quantity"><?php echo esc_html(sprintf(_n('%s unit', '%s units', $quantity, 'blocksy'), number_format_i18n($quantity))); ?></span>
     </div>
    </header>
    <div class="lm-cart-grid-frame">
    <table class="lm-cart-grid" role="table" aria-labelledby="lm-cart-style-<?php echo esc_attr($id); ?>" style="--lm-cart-columns:<?php echo esc_attr(count($size_labels)); ?>">
     <thead role="rowgroup">
      <tr role="row">
       <th scope="col" role="columnheader">Color</th>
       <?php foreach ($size_labels as $label) { ?>
        <th class="lm-cart-size" scope="col" role="columnheader"><?php echo esc_html($label); ?></th>
       <?php } ?>
      </tr>
     </thead>
     <tbody role="rowgroup">
      <?php foreach ($color_order as $color) {
       if (!isset($colors[$color])) continue;
       $label = lm_attribute_name('pa_color', $color);
       $photo = isset($images[$color]) ? wp_get_attachment_image($images[$color], 'woocommerce_gallery_thumbnail', false, array('alt' => '')) : '';
       $term = get_term_by('slug', $color, 'pa_color');
       $swatch = $term ? sanitize_hex_color(get_term_meta($term->term_id, 'wpcvs_color', true)) : '';
       $basic_colors = array('black' => '#171717', 'brown' => '#654130', 'tan' => '#b7865d', 'white' => '#fff');
       $swatch = $swatch ?: ($basic_colors[$color] ?? '');
       ?>
       <tr class="lm-cart-color cart_item" role="row">
        <th scope="row" role="rowheader">
         <span class="lm-cart-color-heading">
          <?php if ($photo) { echo $photo; /* WordPress attachment markup. */ }
          else { ?><span class="lm-cart-swatch" aria-hidden="true"<?php if ($swatch) { ?> style="--lm-cart-swatch:<?php echo esc_attr($swatch); ?>"<?php } ?>></span><?php } ?>
          <span class="lm-cart-color-name"><?php echo esc_html($label); ?></span>
         </span>
        </th>
        <?php foreach ($size_labels as $size => $size_label) {
         $line = $colors[$color][$size] ?? null;
         if (!$line) { ?>
          <td class="lm-cart-blank" role="cell"><span aria-hidden="true">&ndash;</span><span class="screen-reader-text">Not in cart</span></td>
         <?php } else { ?>
          <td class="lm-cart-variant" role="cell">
           <div class="lm-cart-size-label" aria-hidden="true"><?php echo esc_html($size_label); ?></div>
           <?php lm_cart_variant_controls($line, $title, $label, $size_label, !$shared_price); ?>
          </td>
         <?php }
        } ?>
       </tr>
      <?php } ?>
     </tbody>
    </table>
    </div>
   </section>
  </td>
 </tr>
 <?php
}

function lm_cart_variant_controls($line, $title, $color, $size, $show_price) {
 $key = $line['key'];
 $item = $line['item'];
 $product = $line['product'];
 $name = implode(', ', array($title, $color, $size));
 $args = wc_get_quantity_input_args(array(
  'input_id' => 'lm-cart-qty-'.$key,
  'input_name' => "cart[{$key}][qty]",
  'input_value' => $item['quantity'],
  'min_value' => $product->is_sold_individually() ? 1 : 0,
  'max_value' => $product->is_sold_individually() ? 1 : $product->get_max_purchase_quantity(),
  'product_name' => $name,
 ), $product);
 // One input per cart key. Blocksy's duplicate .quantity wrappers synchronize
 // the inputs on a <tr>, which would overwrite the other sizes in this row.
 ob_start();
 ?>
 <input class="<?php echo esc_attr(implode(' ', $args['classes'])); ?>" id="<?php echo esc_attr($args['input_id']); ?>"
  name="<?php echo esc_attr($args['input_name']); ?>" value="<?php echo esc_attr($args['input_value']); ?>"
  type="<?php echo esc_attr($args['type']); ?>" aria-label="<?php echo esc_attr(sprintf('Quantity for %s', $name)); ?>"
  <?php if ($show_price && $args['type'] !== 'hidden') { ?>aria-describedby="lm-cart-price-<?php echo esc_attr($key); ?>"<?php } ?>
  min="<?php echo esc_attr($args['min_value']); ?>" step="<?php echo esc_attr($args['step']); ?>"
  <?php if ($args['max_value'] > 0) { ?>max="<?php echo esc_attr($args['max_value']); ?>"<?php } ?>
  <?php if ($args['readonly']) { ?>readonly<?php } ?>
  inputmode="<?php echo esc_attr($args['inputmode']); ?>" autocomplete="off">
 <?php
 $input = ob_get_clean();
 ?>
 <div class="lm-cart-variant-actions">
  <?php
  if ($args['type'] === 'hidden') echo esc_html($item['quantity']);
  echo apply_filters('woocommerce_cart_item_quantity', $input, $key, $item); // Trusted WooCommerce filter markup.
  ?>
  <span class="lm-cart-remove product-remove">
   <?php
   $remove = sprintf(
    '<a href="%s" class="remove" role="button" aria-label="%s" data-product_id="%s" data-product_sku="%s"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16m-14 0 1 13h10l1-13M9 7V4h6v3m-5 4v5m4-5v5"/></svg></a>',
    esc_url(wc_get_cart_remove_url($key)),
    esc_attr(sprintf('Remove %s from cart', $name)),
    esc_attr($item['product_id']),
    esc_attr($product->get_sku())
   );
   echo apply_filters('woocommerce_cart_item_remove_link', $remove, $key); // Trusted WooCommerce filter markup.
   ?>
  </span>
 </div>
 <?php
 if ($show_price) {
  echo '<span class="lm-cart-unit-price" id="lm-cart-price-'.esc_attr($key).'">'.wp_kses_post($line['price']).' / belt</span>';
 }
 do_action('woocommerce_after_cart_item_name', $item, $key);
 if ($product->backorders_require_notification() && $product->is_on_backorder($item['quantity'])) {
  echo wp_kses_post(apply_filters('woocommerce_cart_item_backorder_notification',
   '<p class="backorder_notification">'.esc_html__('Available on backorder', 'woocommerce').'</p>',
   $item['product_id'], $item, $key));
 }
}
