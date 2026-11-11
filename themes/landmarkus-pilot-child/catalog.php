<?php
/** 原生Woo目录：实际分类/属性、SKU搜索与共享筛选；不写商品数据。 */
defined('ABSPATH') || exit;
function lm_catalog_page(){return !is_product()&&(is_shop()||is_product_taxonomy()||(is_search()&&get_query_var('post_type')==='product'));}
function lm_catalog_global_search(){return is_search()&&get_query_var('post_type')==='product'&&!is_product_taxonomy();}
function lm_catalog_search(){if(isset($_GET['catalog_search'])&&is_string($_GET['catalog_search']))$text=sanitize_text_field(wp_unslash($_GET['catalog_search']));elseif(is_search()&&get_query_var('post_type')==='product')$text=is_string($_GET['s']??null)?sanitize_text_field(wp_unslash($_GET['s'])):get_search_query(false);else $text='';return function_exists('mb_substr')?mb_substr($text,0,120):substr($text,0,120);}
function lm_catalog_params(){
 $out=array();$search=lm_catalog_search();if(lm_catalog_global_search()){$out['s']=$search;$out['post_type']='product';}elseif($search!=='')$out['catalog_search']=$search;
 foreach(array('color','size') as $attr){$key='filter_'.$attr;if(isset($_GET[$key])&&is_string($_GET[$key])){$values=array_slice(array_filter(array_map('sanitize_title',explode(',',wp_unslash($_GET[$key])))),0,20);if($values){$out[$key]=implode(',',array_unique($values));$out['query_type_'.$attr]='or';}}}
 if(isset($_GET['lm_skip'])&&is_string($_GET['lm_skip']))$out['lm_skip']=implode(',',array_intersect(explode(',',sanitize_text_field(wp_unslash($_GET['lm_skip']))),array('color','size','category')));
 if(isset($_GET['orderby'])&&is_string($_GET['orderby']))$out['orderby']=sanitize_key(wp_unslash($_GET['orderby']));return $out;
}
function lm_catalog_base(){if(lm_catalog_global_search())return home_url('/');if(is_product_taxonomy()){$url=get_term_link(get_queried_object());if(!is_wp_error($url))return $url;}return wc_get_page_permalink('shop');}
function lm_catalog_reset_url(){return lm_catalog_global_search()?wc_get_page_permalink('shop'):lm_catalog_base();}
function lm_catalog_url($base=null,$changes=array()){
 $params=array_merge(lm_catalog_params(),$changes);
 if(lm_catalog_global_search()&&(($base&&untrailingslashit($base)!==untrailingslashit(home_url('/')))||array_key_exists('s',$changes)&&$changes['s']===null)){
  if(!empty($params['s']))$params['catalog_search']=$params['s'];unset($params['s'],$params['post_type']);if(!$base)$base=wc_get_page_permalink('shop');
 }
 $params=array_filter($params,static function($value){return $value!==null&&$value!=='';});return add_query_arg($params,$base?:lm_catalog_base());
}
function lm_catalog_scope(){
 if(lm_catalog_global_search())return false;
 if(is_product_taxonomy()){$term=get_queried_object();if($term instanceof WP_Term){if($term->taxonomy==='product_cat'){$ancestors=get_ancestors($term->term_id,'product_cat','taxonomy');if($ancestors)$term=get_term(end($ancestors),'product_cat');}return $term;}}
 return get_term_by('slug','wholesale','product_cat');
}
function lm_catalog_term_name($name){return html_entity_decode($name,ENT_QUOTES,'UTF-8');}
function lm_catalog_scope_ids($root=false){
 static $cache=array();$scope=(!$root&&is_product_taxonomy())?get_queried_object():lm_catalog_scope();if(!$scope||is_wp_error($scope))return array();$key=$scope->taxonomy.':'.$scope->term_id;if(isset($cache[$key]))return $cache[$key];
 $query=new WP_Query(array('post_type'=>'product','post_status'=>'publish','fields'=>'ids','posts_per_page'=>-1,'no_found_rows'=>true,'tax_query'=>WC()->query->get_tax_query(array(array('taxonomy'=>$scope->taxonomy,'field'=>'term_id','terms'=>array($scope->term_id),'include_children'=>true)))));return $cache[$key]=$query->posts;
}
add_action('woocommerce_product_query',static function($query){
 if(is_admin()||!$query->is_main_query())return;$query->set('lm_catalog',true);
 if(!$query->is_tax()&&!$query->is_search()){$root=get_term_by('slug','wholesale','product_cat');if($root){$tax=(array)$query->get('tax_query');$tax[]=array('taxonomy'=>'product_cat','field'=>'term_id','terms'=>array($root->term_id),'include_children'=>true);$query->set('tax_query',$tax);}}
 $search=lm_catalog_search();if($search!=='')$query->set('s',$search);
 $query->set('posts_per_page',12);
},30);
add_filter('posts_search',static function($search,$query){
 if(!$query->is_main_query()||!$query->get('lm_catalog')||!$query->get('s'))return $search;
 global $wpdb;$like='%'.$wpdb->esc_like($query->get('s')).'%';
 $sku=$wpdb->prepare("EXISTS (SELECT 1 FROM {$wpdb->postmeta} lm_sku INNER JOIN {$wpdb->posts} lm_variant ON lm_variant.ID=lm_sku.post_id WHERE lm_sku.meta_key='_sku' AND lm_sku.meta_value LIKE %s AND (lm_sku.post_id={$wpdb->posts}.ID OR (lm_variant.post_parent={$wpdb->posts}.ID AND lm_variant.post_type='product_variation' AND lm_variant.post_status='publish')))",$like);
 $native=preg_replace('/^\s*AND\s+/i','',$search,1);if(!$native)$native='0=1';return " AND (($native) OR ($sku)) AND {$wpdb->posts}.post_password='' ";
},20,2);
add_filter('woocommerce_catalog_orderby',static function($options){if(lm_catalog_page()){unset($options['popularity'],$options['rating']);if(isset($options['date']))$options['date']='Newest first';}if(lm_catalog_page()&&class_exists('LandmarkUS_Price_Access')&&LandmarkUS_Price_Access::restricted()){unset($options['price'],$options['price-desc']);}return $options;});
add_action('wp_enqueue_scripts',static function(){if(!lm_catalog_page())return;wp_enqueue_style('landmark-catalog',get_stylesheet_directory_uri().'/catalog.css',array('landmark-commerce','landmark-product-cards'),(string)filemtime(__DIR__.'/catalog.css'));wp_enqueue_script('landmark-catalog',get_stylesheet_directory_uri().'/catalog.js',array(),(string)filemtime(__DIR__.'/catalog.js'),true);},110);
add_filter('template_include',static function($template){return lm_catalog_page()?__DIR__.'/woocommerce.php':$template;},1000);
function lm_catalog_heading(){
 $title=lm_catalog_global_search()?'Search results':(is_product_taxonomy()?woocommerce_page_title(false):'Wholesale');$search_key=lm_catalog_global_search()?'s':'catalog_search';
 echo '<header class="lm-catalog-heading"><div><p class="lm-catalog-eyebrow">LandmarkUS</p><h1>'.esc_html($title).'</h1>';
 $scope=lm_catalog_scope();if($scope&&$scope->taxonomy==='product_cat'&&$scope->slug==='wholesale')echo '<p class="lm-catalog-intro">Belt orders start at 12 belts. Mix styles across your order.</p>';
 echo '<p class="lm-catalog-guide-link"><a href="'.esc_url(home_url('/wholesale-buying-guide/')).'">Buying guide &amp; FAQs</a></p>';
 echo '</div><form class="lm-catalog-search" role="search" method="get" action="'.esc_url(lm_catalog_base()).'"><label for="lm-catalog-search">Find a product</label><div><input id="lm-catalog-search" type="search" name="'.esc_attr($search_key).'" maxlength="120" placeholder="SKU, color, style or size" value="'.esc_attr(lm_catalog_search()).'"><button type="submit">Search</button></div>';
 foreach(lm_catalog_params() as $key=>$value)if($key!==$search_key)echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';echo '</form></header>';
}
function lm_catalog_category_list($parent,$depth=0){
 if($depth>1)return;$terms=get_terms(array('taxonomy'=>'product_cat','parent'=>$parent,'hide_empty'=>true,'orderby'=>'menu_order'));if(is_wp_error($terms)||!$terms)return;
 usort($terms,static function($a,$b){return $a->slug==='mens-belts-wholesale'?-1:($b->slug==='mens-belts-wholesale'?1:0);});
 echo '<ul>';foreach($terms as $term){$current=is_product_category($term->term_id);$link=get_term_link($term);if(is_wp_error($link))continue;echo '<li><a href="'.esc_url(lm_catalog_url($link)).'"'.($current?' aria-current="page"':'').'><span>'.esc_html(lm_catalog_term_name($term->name)).'</span><span class="lm-category-count">'.esc_html($term->count).'</span></a>';lm_catalog_category_list($term->term_id,$depth+1);echo '</li>';}echo '</ul>';
}
function lm_catalog_filters(){
 $scope=lm_catalog_scope();$params=lm_catalog_params();$ids=lm_catalog_scope_ids();
 echo '<aside class="lm-catalog-filters" id="lm-catalog-filters" aria-label="Product categories and filters"><details class="lm-catalog-category-details" open><summary>Browse categories</summary><nav class="lm-catalog-categories" aria-label="Product categories">';
 if($scope&&$scope->taxonomy==='product_cat'){$root_url=$scope->slug==='wholesale'?wc_get_page_permalink('shop'):get_term_link($scope);if(!is_wp_error($root_url))echo '<a class="lm-category-all" href="'.esc_url(lm_catalog_url($root_url)).'"'.((is_shop()||is_product_category($scope->term_id))?' aria-current="page"':'').'><span>'.esc_html($scope->slug==='wholesale'?'All wholesale':lm_catalog_term_name($scope->name)).'</span><span class="lm-category-count">'.esc_html(count(lm_catalog_scope_ids(true))).'</span></a>';lm_catalog_category_list($scope->term_id);}
 else {echo '<a href="'.esc_url(lm_catalog_url(wc_get_page_permalink('shop'))).'">Browse wholesale</a>';$custom=get_term_by('slug','made-to-order','product_cat');if($custom)echo '<a href="'.esc_url(lm_catalog_url(get_term_link($custom))).'">Made to Order</a>';}
 echo '</nav></details><form class="lm-catalog-filter-form" action="'.esc_url(lm_catalog_base()).'" method="get"><h2>Refine products</h2>';
 foreach(array('catalog_search','s','post_type','orderby') as $key)if(!empty($params[$key]))echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($params[$key]).'">';
 if(class_exists('LandmarkUS_Smart_Search')&&LandmarkUS_Smart_Search::$state){$state=LandmarkUS_Smart_Search::$state;echo '<input type="hidden" name="lm_skip" value="'.esc_attr(implode(',',array_unique(array_merge($state['request']['skip'],array('color','size'))))).'">';foreach(array('color','size') as $a)if(empty($params['filter_'.$a])&&!empty($state['filters'][$a]))$params['filter_'.$a]=implode(',',$state['filters'][$a]);}
 $fields=0;foreach(array('color'=>'Color','size'=>'Size') as $attr=>$label){$terms=$ids?wp_get_object_terms($ids,'pa_'.$attr,array('orderby'=>'name')):array();if(is_wp_error($terms)||!$terms)continue;$fields++;$selected=$params['filter_'.$attr]??'';$slugs=wp_list_pluck($terms,'slug');
  echo '<label for="lm-filter-'.esc_attr($attr).'">'.esc_html($label).'</label><select id="lm-filter-'.esc_attr($attr).'" name="filter_'.esc_attr($attr).'"><option value="">All '.esc_html(strtolower($label)).' options</option>';
  if($selected!==''&&!in_array($selected,$slugs,true)){echo '<option value="'.esc_attr($selected).'" selected>Current selection: '.esc_html($selected).'</option>';}
  foreach($terms as $term)echo '<option value="'.esc_attr($term->slug).'"'.selected($selected,$term->slug,false).'>'.esc_html(lm_catalog_term_name($term->name)).'</option>';
  echo '</select><input type="hidden" name="query_type_'.esc_attr($attr).'" value="or">';
 }
 if($fields)echo '<button type="submit" class="lm-catalog-apply">Apply filters</button>';else echo '<p class="lm-catalog-filter-note">Use the categories or product search to find a style.</p>';
 echo '<a class="lm-catalog-reset" href="'.esc_url(lm_catalog_reset_url()).'">Clear all</a></form></aside>';
}
function lm_catalog_active_filters(){
 $params=lm_catalog_params();$chips=array();if(function_exists('lm_smart_search_summary'))lm_smart_search_summary();if(lm_catalog_search()!=='')$chips[]=array('Search: '.lm_catalog_search(),array('catalog_search'=>null,'s'=>null,'post_type'=>null,'lm_skip'=>null));
 foreach(array('color'=>'Color','size'=>'Size') as $attr=>$label)if(!empty($params['filter_'.$attr])){foreach(explode(',',$params['filter_'.$attr]) as $value){$term=get_term_by('slug',$value,'pa_'.$attr);$remaining=array_values(array_diff(explode(',',$params['filter_'.$attr]),array($value)));$chips[]=array($label.': '.($term?lm_catalog_term_name($term->name):$value),array('filter_'.$attr=>implode(',',$remaining)));}}
 if(!$chips)return;echo '<nav class="lm-catalog-active" aria-label="Applied filters">';foreach($chips as $chip)echo '<a href="'.esc_url(lm_catalog_url(null,$chip[1])).'" aria-label="'.esc_attr('Remove '.$chip[0]).'"><span>'.esc_html($chip[0]).'</span><span aria-hidden="true">×</span></a>';echo '<a class="lm-catalog-clear" href="'.esc_url(lm_catalog_reset_url()).'">Clear all</a></nav>';
}
add_action('woocommerce_no_products_found',static function(){if(lm_catalog_page())echo '<p class="lm-catalog-empty-help">Try a different SKU or keyword, or clear the current filters.</p><a class="lm-catalog-empty-reset" href="'.esc_url(lm_catalog_reset_url()).'">Clear search and filters</a>';},20);

function lm_catalog_filter_button(){echo '<button class="lm-catalog-open" type="button" hidden aria-controls="lm-catalog-filter-dialog" aria-expanded="false">Filters</button>';}
function lm_catalog_toolbar_open(){echo '<div class="lm-catalog-toolbar">';lm_catalog_filter_button();}
function lm_catalog_toolbar_close(){echo '<noscript><nav class="lm-catalog-noscript-sort" aria-label="Sort products"><a href="'.esc_url(lm_catalog_url(null,array('orderby'=>'menu_order'))).'">Default order</a><a href="'.esc_url(lm_catalog_url(null,array('orderby'=>'date'))).'">Newest first</a></nav></noscript></div>';}
