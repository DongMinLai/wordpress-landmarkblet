<?php
/**
 * Plugin Name: LandmarkUS Core
 * Description: LandmarkUS 全局批发价格访问配置；与主题分离。
 * Version: 0.4.0
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 */
defined('ABSPATH') || exit;
final class LandmarkUS_Price_Access {
 const OPTION = 'landmarkus_price_visibility';
 public static function mode() { return get_option(self::OPTION, 'logged_in') === 'public' ? 'public' : 'logged_in'; }
 public static function restricted() {
  if (defined('WP_CLI') && WP_CLI && !defined('LM_PRICE_ACCESS_TEST')) return false;
  if (is_admin() && !wp_doing_ajax()) return false;
  return self::mode() === 'logged_in' && !is_user_logged_in();
 }
 public static function login_url($target = '') {
  $url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
  $target = wp_validate_redirect($target, home_url('/'));
  return add_query_arg('redirect_to', $target, $url);
 }
 public static function prompt($link = true) {
  if (!$link) return '<span class="lm-price-login">Log in to view prices</span>';
  $target = home_url('/');
  if (is_singular()) $target=get_permalink(get_queried_object_id());
  elseif (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
   $term_url=get_term_link(get_queried_object());if(!is_wp_error($term_url))$target=$term_url;
  } elseif (function_exists('is_shop') && is_shop()) $target=wc_get_page_permalink('shop');
  return '<a class="lm-price-login" href="'.esc_url(self::login_url($target)).'">Log in for wholesale prices</a>';
 }
 public static function fields() {
  return array(
   array('title'=>'LandmarkUS 批发价格','type'=>'title','id'=>'landmarkus_price_access'),
   array('title'=>'价格可见性','id'=>self::OPTION,'type'=>'select','default'=>'logged_in','options'=>array('logged_in'=>'仅登录用户可见','public'=>'所有访客可见'),'desc'=>'全站 WooCommerce 商品价格统一生效。仅登录模式下，访客可浏览商品，查看价格和使用购物车/结账前需登录。任何有效登录账户均可，不另设批发审核。'),
   array('type'=>'sectionend','id'=>'landmarkus_price_access')
  );
 }
 public static function save() {
  if (!current_user_can('manage_woocommerce')) wp_die('You are not allowed to change these settings.', '', array('response'=>403));
  check_admin_referer('woocommerce-settings');
  WC_Admin_Settings::save_fields(self::fields());
 }
 public static function prune_offers($data) {
  if (!is_array($data)) return $data;
  foreach ($data as $key=>$value) {
   if (in_array((string)$key,array('offers','price','lowPrice','highPrice','priceSpecification','priceCurrency'),true)) unset($data[$key]);
   elseif (is_array($value)) $data[$key]=self::prune_offers($value);
  }
  return $data;
 }
 public static function private_headers() {
  if (self::mode() !== 'logged_in') return;
  if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
  if (!headers_sent()) { nocache_headers(); header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0'); header('Vary: Cookie',false); }
 }
 public static function route_guest() {
  if (!self::restricted()) return;
  $ajax = isset($_REQUEST['wc-ajax']) ? sanitize_key(wp_unslash($_REQUEST['wc-ajax'])) : '';
  if (!$ajax && isset($_REQUEST['action'])) {
   $action=sanitize_key(wp_unslash($_REQUEST['action']));
   if (strpos($action,'woocommerce_')===0) $ajax=substr($action,12);
  }
  if ($ajax && $ajax!=='get_variation') {
   if ($ajax==='get_refreshed_fragments') wp_send_json(array('fragments'=>new stdClass(),'cart_hash'=>''));
   if ($ajax==='add_to_cart') wp_send_json(array('error'=>true,'product_url'=>self::login_url(home_url('/'))));
   wp_send_json_error(array('message'=>'Log in to access wholesale pricing.','login_url'=>self::login_url(home_url('/'))),401);
  }
 }
 public static function init() {
  add_filter('sanitize_option_'.self::OPTION,static function($value){return $value==='public'?'public':'logged_in';});
  add_filter('woocommerce_settings_tabs_array',static function($tabs){$tabs['landmarkus']='LandmarkUS';return $tabs;},50);
  add_action('woocommerce_settings_tabs_landmarkus',static function(){WC_Admin_Settings::output_fields(self::fields());echo '<p>切换价格可见性后，请清理页面、主机及 CDN 缓存。腰带批发整单最低12条，可混款；金额仍由WooCommerce计算。</p>';});
  add_action('woocommerce_update_options_landmarkus',array(__CLASS__,'save'));
  add_action('init',array(__CLASS__,'private_headers'),1);
  add_action('send_headers',array(__CLASS__,'private_headers'),99);
  add_action('wp_loaded',array(__CLASS__,'route_guest'),1);
  add_filter('woocommerce_get_price_html',static function($html){return self::restricted()?self::prompt():$html;},PHP_INT_MAX);
  // 覆盖原生金额格式化，避免迷你购物车小计等绕过价格HTML入口。
  add_filter('wc_price',static function($html){return self::restricted()?self::prompt(false):$html;},PHP_INT_MAX);
  add_filter('woocommerce_available_variation',static function($data){
   if (self::restricted()) {
    unset($data['display_price'],$data['display_regular_price']);
    $data['price_html']=self::prompt();$data['is_purchasable']=false;
   }
   return $data;
  },PHP_INT_MAX);
  foreach (array('woocommerce_is_purchasable','woocommerce_variation_is_purchasable') as $hook) add_filter($hook,static function($allowed){return self::restricted()?false:$allowed;},PHP_INT_MAX);
  add_filter('woocommerce_add_to_cart_validation',static function($allowed){
   if (!self::restricted()) return $allowed;
   wc_add_notice('Please log in to view wholesale prices and place an order.','error');return false;
  },PHP_INT_MAX);
  add_action('template_redirect',static function(){
   if (!self::restricted()) return;
   if (is_cart() || is_checkout()) {wp_safe_redirect(self::login_url(is_cart()?wc_get_cart_url():wc_get_checkout_url()));exit;}
   $orderby=isset($_GET['orderby'])?sanitize_key(wp_unslash($_GET['orderby'])):'';
   if (isset($_GET['min_price']) || isset($_GET['max_price']) || in_array($orderby,array('price','price-desc'),true)) {wp_safe_redirect(remove_query_arg(array('min_price','max_price','orderby')));exit;}
  },1);
  add_filter('woocommerce_catalog_orderby',static function($options){if(self::restricted())unset($options['price'],$options['price-desc']);return $options;});
  add_filter('widget_display_callback',static function($instance,$widget){return self::restricted() && $widget->id_base==='woocommerce_price_filter'?false:$instance;},10,2);
  add_action('woocommerce_single_product_summary',static function(){if(self::restricted())echo '<p><a class="button lm-price-login-button" href="'.esc_url(self::login_url(get_permalink())).'">Log in to view wholesale prices</a></p>';},31);
  foreach (array('woocommerce_structured_data_product','rank_math/json_ld') as $hook) add_filter($hook,static function($data){return self::restricted()?self::prune_offers($data):$data;},PHP_INT_MAX);
  add_filter('rest_pre_dispatch',static function($result,$server,$request){
   if (self::restricted() && preg_match('~^/wc/store/(?:v[0-9]+(?:/|$)|products(?:/|$)|cart(?:/|$)|checkout(?:/|$))~',$request->get_route())) return new WP_Error('landmarkus_login_required','Log in to access wholesale pricing.',array('status'=>401));
   return $result;
  },PHP_INT_MAX,3);
  add_filter('rest_post_dispatch',static function($response){
   if (self::mode()==='logged_in' && $response instanceof WP_HTTP_Response) {$response->header('Cache-Control','private, no-store, max-age=0');$response->header('Vary','Cookie');}
   return $response;
  },99);
  add_action('woocommerce_login_form_end',static function(){
   if(isset($_GET['redirect_to']))echo '<input type="hidden" name="landmarkus_return_to" value="'.esc_attr(wp_validate_redirect(esc_url_raw(wp_unslash($_GET['redirect_to'])),home_url('/'))).'">';
  });
  add_filter('woocommerce_login_redirect',static function($redirect){return isset($_POST['landmarkus_return_to'])?wp_validate_redirect(esc_url_raw(wp_unslash($_POST['landmarkus_return_to'])),$redirect):$redirect;});
 }
}
register_activation_hook(__FILE__,static function(){add_option(LandmarkUS_Price_Access::OPTION,'logged_in','','no');});
LandmarkUS_Price_Access::init();

require_once __DIR__.'/wholesale-order.php';

if(function_exists('wp_get_environment_type')&&wp_get_environment_type()==='local'&&file_exists(__DIR__.'/local-development.php'))require_once __DIR__.'/local-development.php';

require_once __DIR__.'/smart-search.php';

require_once __DIR__.'/contact-inquiry.php';
