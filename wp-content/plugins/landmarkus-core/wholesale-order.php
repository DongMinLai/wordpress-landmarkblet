<?php
/** 批发订货：Woo为变体、库存、购物车及金额权威；不随主题丢失。 */
defined('ABSPATH') || exit;
final class LandmarkUS_Wholesale_Order {
 const MINIMUM=12;
 public static function price_allowed(){return LandmarkUS_Price_Access::mode()==='public'||is_user_logged_in();}
 public static function is_wholesale_belt($product){
  if(!$product instanceof WC_Product)return false;$parent=$product->is_type('variation')?$product->get_parent_id():$product->get_id();
  $root=get_term_by('slug','mens-belts-wholesale','product_cat');if(!$root)return false;
  $ids=wp_get_post_terms($parent,'product_cat',array('fields'=>'ids'));if(is_wp_error($ids))return false;
  foreach($ids as $id)if((int)$id===(int)$root->term_id||in_array((int)$root->term_id,array_map('intval',get_ancestors($id,'product_cat','taxonomy')),true))return true;
  return false;
 }
 public static function count($cart=null){$cart=$cart?:WC()->cart;$n=0;if($cart)foreach($cart->get_cart() as $item)if(self::is_wholesale_belt($item['data']))$n+=(int)$item['quantity'];return $n;}
 public static function minimum_message($cart=null){$count=self::count($cart);return $count>0&&$count<self::MINIMUM?sprintf('Wholesale belt orders start at %1$d belts across styles. Add %2$d more belts to check out.',self::MINIMUM,self::MINIMUM-$count):'';}
 public static function variation_rows($product){
  $rows=array();if(!$product||!$product->is_type('variable'))return $rows;
  foreach($product->get_children() as $id){$v=wc_get_product($id);if(!$v||$v->get_status()!=='publish')continue;$attrs=$v->get_variation_attributes();$complete=!in_array('',$attrs,true);$rows[]=array('product'=>$v,'attributes'=>$attrs,'complete'=>$complete);}
  return $rows;
 }
 public static function validation_error($product,$variation,$quantity){
  if(!$product||!$variation||!$variation->is_type('variation')||$variation->get_parent_id()!==$product->get_id())return 'This variant does not belong to this product.';
  if($variation->get_status()!=='publish'||!$variation->variation_is_visible()||!$variation->variation_is_active())return 'This variant is not available.';
  if(in_array('',$variation->get_variation_attributes(),true))return 'Choose complete options using the single-item form.';
  if(!$variation->is_purchasable())return 'This variant cannot currently be purchased.';
  if(!$variation->is_in_stock())return 'This variant is out of stock.';
  if($variation->is_sold_individually()&&$quantity!==1)return 'This variant is sold individually.';
  if(!$variation->has_enough_stock($quantity))return 'This quantity is not available.';
  return '';
 }
 public static function reply($data,$status=200){
  if(wp_doing_ajax()){if($status>=400)wp_send_json_error($data,$status);wp_send_json_success($data);}
  if(!empty($data['message']))wc_add_notice($data['message'],$status>=400?'error':'success');
  $target=$status>=400&&isset($data['product_url'])?$data['product_url']:wc_get_cart_url();wp_safe_redirect($target,303);exit;
 }
 public static function bulk_add(){
  if(!function_exists('WC'))wp_die('WooCommerce is required.',503);
  if(!WC()->cart)wc_load_cart();
  $pid=isset($_POST['product_id'])?absint($_POST['product_id']):0;$product=wc_get_product($pid);$url=$product?$product->get_permalink():wc_get_page_permalink('shop');
  $base=array('product_url'=>$url);
  if(!self::price_allowed()){self::reply($base+array('message'=>'Please log in to place a wholesale order.','login_url'=>LandmarkUS_Price_Access::login_url($url)),401);}
  $nonce=isset($_POST['lm_bulk_nonce'])?sanitize_text_field(wp_unslash($_POST['lm_bulk_nonce'])):'';
  if(!wp_verify_nonce($nonce,'landmarkus_bulk_'.$pid))self::reply($base+array('message'=>'Your form expired. Reload this product and try again.'),403);
  if(!$product||!$product->is_type('variable')||$product->get_status()!=='publish'||!self::is_wholesale_belt($product))self::reply($base+array('message'=>'This product is not available for wholesale bulk ordering.'),400);
  $request=isset($_POST['request_id'])?sanitize_text_field(wp_unslash($_POST['request_id'])):'';
  if(!preg_match('/^[a-f0-9-]{36}$/i',$request))self::reply($base+array('message'=>'Reload this form before adding variants.'),400);
  $posted=isset($_POST['quantities'])&&is_array($_POST['quantities'])?wp_unslash($_POST['quantities']):array();
  if(count($posted)>500)self::reply($base+array('message'=>'Too many variants in one request.'),400);
  $draft=array();foreach($posted as $id=>$value)if(is_scalar($value)&&preg_match('/^[0-9]+$/D',(string)$id)&&preg_match('/^[0-9]{0,9}$/D',(string)$value))$draft[(int)$id]=(string)$value;WC()->session->set('landmarkus_bulk_draft_'.$pid,$draft);
  $rows=array();$errors=array();
  foreach($posted as $id=>$value){
   if(!is_scalar($value)||!preg_match('/^[0-9]+$/D',(string)$id)||((string)$value!==''&&!preg_match('/^[0-9]{1,9}$/D',(string)$value))){$errors[]='Enter whole, non-negative quantities.';continue;}
   if((string)$value===''||(int)$value===0)continue;$id=(int)$id;$qty=(int)$value;$v=wc_get_product($id);$error=self::validation_error($product,$v,$qty);if($error)$errors[]=$error;else $rows[$id]=array('quantity'=>$qty,'attributes'=>$v->get_variation_attributes());
  }
  if($errors||!$rows)self::reply($base+array('message'=>$errors?implode(' ',array_unique($errors)):'Enter a quantity for at least one available variant.'),400);
  ksort($rows);$identity=is_user_logged_in()?'user:'.get_current_user_id():'session:'.WC()->session->get_customer_id();
  $key='landmarkus_bulk_'.hash('sha256',$identity.'|'.$request);$hash=hash('sha256',wp_json_encode(array($pid,$rows)));$now=time();
  $receipt=get_option($key);
  if($receipt&&($receipt['expires']??0)<$now){delete_option($key);$receipt=false;}
  if($receipt){if(($receipt['hash']??'')!==$hash)self::reply($base+array('message'=>'This request was changed. Reload the form before trying again.'),409);if(($receipt['state']??'')==='complete')self::reply($receipt['result']+array('duplicate'=>true));self::reply($base+array('message'=>'This request is being processed. Please wait and review your cart.'),409);}
  if(!add_option($key,array('state'=>'processing','owner'=>hash('sha256',$identity),'hash'=>$hash,'expires'=>$now+900),'','no'))self::reply($base+array('message'=>'This request is already being processed.'),409);
  $lock='landmarkus_cartlock_'.hash('sha256',$identity);$old_lock=get_option($lock);if($old_lock&&$old_lock<$now)delete_option($lock);
  if(!add_option($lock,$now+120,'','no')){delete_option($key);self::reply($base+array('message'=>'Your cart is updating. Please try again in a moment.'),409);}
  $cart=WC()->cart;$before=$cart->get_cart();$removed=$cart->get_removed_cart_contents();$coupons=$cart->get_applied_coupons();$success=false;$message='';
  try{
   foreach($rows as $id=>$row){
    $passed=apply_filters('woocommerce_add_to_cart_validation',true,$pid,$row['quantity'],$id,$row['attributes']);
    if(!$passed||!$cart->add_to_cart($pid,$row['quantity'],$id,$row['attributes']))throw new RuntimeException('One or more variants could not be added. Review availability and try again.');
   }
   $cart->calculate_totals();$cart->set_session();$success=true;
  }catch(Throwable $e){$cart->set_cart_contents($before);$cart->set_removed_cart_contents($removed);$cart->set_applied_coupons($coupons);$cart->calculate_totals();$cart->set_session();$cart->persistent_cart_update();$message='The selected variants could not be added. Your cart and quantities were kept. Review availability and try again.';}
  finally{delete_option($lock);}
  if(!$success){delete_option($key);self::reply($base+array('message'=>$message),400);}
  WC()->session->__unset('landmarkus_bulk_draft_'.$pid);
  $count=array_sum(array_column($rows,'quantity'));$result=array('message'=>sprintf('%d belts added to your cart.',$count),'added_quantity'=>$count,'variants'=>count($rows),'cart_url'=>wc_get_cart_url(),'next_request_id'=>wp_generate_uuid4());
  update_option($key,array('state'=>'complete','owner'=>hash('sha256',$identity),'hash'=>$hash,'expires'=>$now+900,'result'=>$result),false);wp_schedule_single_event($now+900,'landmarkus_bulk_receipt_cleanup',array($key));self::reply($result);
 }
 public static function init(){
  add_action('wc_ajax_landmarkus_bulk_add',array(__CLASS__,'bulk_add'));
  add_action('admin_post_landmarkus_bulk_add',array(__CLASS__,'bulk_add'));add_action('admin_post_nopriv_landmarkus_bulk_add',array(__CLASS__,'bulk_add'));
  add_action('landmarkus_bulk_receipt_cleanup',static function($key){if(strpos($key,'landmarkus_bulk_')===0)delete_option($key);});
  add_action('woocommerce_check_cart_items',static function(){$message=self::minimum_message();if($message&&!wc_has_notice($message,'error'))wc_add_notice($message,'error');});
  add_action('template_redirect',static function(){if(is_checkout()&&!is_order_received_page()&&!is_wc_endpoint_url('order-pay')){$message=self::minimum_message();if($message){if(!wc_has_notice($message,'error'))wc_add_notice($message,'error');wp_safe_redirect(wc_get_cart_url());exit;}}},5);
  add_action('woocommerce_after_checkout_validation',static function($data,$errors){$message=self::minimum_message();if($message)$errors->add('landmarkus_minimum_belts',$message);},10,2);
  add_action('woocommerce_store_api_cart_errors',static function($errors,$cart){$message=self::minimum_message($cart);if($message)$errors->add('landmarkus_minimum_belts',$message);},10,2);
  add_action('woocommerce_before_cart',static function(){if(self::count())echo '<p class="lm-cart-rule">Wholesale minimum: 12 belts across styles. Prices are per belt.</p>';});
 }
}
LandmarkUS_Wholesale_Order::init();
