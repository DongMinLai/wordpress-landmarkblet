<?php
/** 共用Shell：读取原站菜单/页脚来源，不写菜单、小工具或业务数据。 */
defined('ABSPATH') || exit;

/** 搜索摘要仅用于已确认的五个落地页。 */
function lm_search_snippet() {
 if (is_404() || is_search() || is_preview() || is_paged() || is_embed()) return null;

 if (is_front_page()) {
  return array(
   'LandmarkUS | Wholesale Belt Supplier for Retailers',
   'Source belts for your store with LandmarkUS. Explore wholesale styles, U.S. inventory, and custom or private label options for your business.',
  );
 }

 if (!is_page()) return null;
 $page = get_queried_object();
 $root = home_url('/');
 $snippets = array(
  'wholesale-belts' => array(
   'Wholesale Belts for Retailers & Resellers | LandmarkUS',
   'Browse dress, casual, and other wholesale belt styles. Compare colors and sizes, plan a mixed-style order, and find options for your next restock.',
  ),
  'custom-belts' => array(
   'Custom & Private Label Belts | LandmarkUS',
   'Develop belts for your brand. Start with a sample, sketch, or existing style, then discuss materials, colors, buckles, and other design details.',
  ),
  'wholesale-buying-guide' => array(
   'Wholesale Belt Buying Guide for Retailers | LandmarkUS',
   'Learn how to order wholesale belts. Choose styles, colors, and sizes, review SKUs and quantities in your cart, and find answers about samples and returns.',
  ),
  'wholesale-golf-belts' => array(
   'Wholesale Golf Belts for Retailers | LandmarkUS',
   'Browse featured wholesale golf belts for your store. Compare styles and color options, then open individual products for sizes and ordering details.',
  ),
 );
 return isset($snippets[$page->post_name]) && untrailingslashit(get_permalink($page)) === untrailingslashit($root.$page->post_name)
  ? $snippets[$page->post_name] : null;
}

add_filter('document_title_parts', static function ($parts) {
 $snippet = lm_search_snippet();
 return $snippet ? array('title' => $snippet[0]) : $parts;
});

add_action('wp_head', static function () {
 $snippet = lm_search_snippet();
 if ($snippet) printf("<meta name=\"description\" content=\"%s\" />\n", esc_attr($snippet[1]));
});

function lm_shell_menu_items(){
 $locations=get_nav_menu_locations();
 $legacy=get_option('theme_mods_flatsome-child',array());
 $id=!empty($locations['primary'])?$locations['primary']:($legacy['nav_menu_locations']['primary']??0);
 $items=$id?wp_get_nav_menu_items($id):array();
 return apply_filters('landmarkus_shell_menu_items',is_array($items)?$items:array());
}
function lm_shell_nav($instance){
 $tree=array();foreach(lm_shell_menu_items() as $item)$tree[(int)$item->menu_item_parent][]=$item;
 $render=function($parent,$depth=0)use(&$render,$tree,$instance){
  foreach($tree[$parent]??array() as $item){
   $children=$tree[(int)$item->ID]??array();$id='lm-'.$instance.'-children-'.(int)$item->ID;
   $url = apply_filters('landmarkus_shell_nav_url', $item->url, $item);
   $is_home = $depth === 0 && untrailingslashit($item->url) === untrailingslashit(home_url('/'));
   $preview = $is_home ? get_page_by_path('landmark-home-v1') : null;
   $use_preview = $preview && $preview->post_status === 'publish' && get_post_meta($preview->ID, '_lm_pilot_owner', true) === 'PV01';
   if ($use_preview) $url = get_permalink($preview);
   $current = $is_home && ($use_preview ? is_page($preview->ID) : is_front_page());
   echo '<li class="'.($children?'lm-has-children ':'').($current?'lm-menu-current':'').'">';
   $rel=$item->xfn;if($item->target==='_blank')$rel=trim($rel.' noopener noreferrer');
   echo '<a href="'.esc_url($url).'"'.($current?' aria-current="page"':'').($item->target?' target="'.esc_attr($item->target).'"':'').($rel?' rel="'.esc_attr($rel).'"':'').'>'.esc_html($item->title).'</a>';
   if($children){
    echo '<button class="lm-submenu-toggle" type="button" aria-expanded="false" aria-controls="'.esc_attr($id).'" aria-label="'.esc_attr(sprintf('Show %s submenu',$item->title)).'"><span aria-hidden="true"></span></button>';
    $groups=false;foreach($children as $child)if(!empty($tree[(int)$child->ID]))$groups=true;
    echo '<ul id="'.esc_attr($id).'" class="lm-submenu" data-columns="'.($depth===0&&$groups?min(count($children),3):1).'">';$render((int)$item->ID,$depth+1);echo '</ul>';
   }
   echo '</li>';
  }
 };
 echo '<ul class="lm-site-menu">';$render(0);echo '</ul>';
}
function lm_shell_icon($name){
 $paths=array('menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>','search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>','cart'=>'<path d="M5 8h14l1 13H4L5 8Z"/><path d="M8 9V6a4 4 0 0 1 8 0v3"/>','account'=>'<circle cx="12" cy="7" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2Z"/>');
 echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'.($paths[$name]??'').'</svg>'; // 固定内部图形，无外部SVG输入。
}
function lm_shell_footer_contact(){
 $widgets=get_option('widget_block_widget',array());$slug=$widgets[2]['block']??'footer-about-us';
 $block=get_page_by_path(sanitize_title($slug),OBJECT,'blocks');if(!$block)return;
 if(preg_match('/\[ux_text[^\]]*\](.*?)\[\/ux_text\]/s',$block->post_content,$match)){
  $html=wp_kses($match[1],array('p'=>array(),'br'=>array(),'strong'=>array(),'em'=>array(),'a'=>array('href'=>array())));
  // 原文本转换成可操作联系方式；显示文字、地址及TEXT口径不改。
  $html=preg_replace_callback('/<p>([^<]+)<\/p>/',static function($m){$plain=trim(html_entity_decode($m[1],ENT_QUOTES,'UTF-8'));if(is_email($plain))return '<p><a href="'.esc_url('mailto:'.$plain).'">'.esc_html($plain).'</a></p>';if(preg_match('/^TEXT:(.*)$/u',$plain,$phone))return '<p><a href="'.esc_attr('sms:'.preg_replace('/[^+0-9]/','',$phone[1])).'">'.esc_html($plain).'</a></p>';return $m[0];},$html);
  echo $html; // 已按上方白名单过滤。
 }
 if(preg_match('/\[button\s+([^\]]*)\]/',$block->post_content,$button)){
  $attrs=shortcode_parse_atts($button[1]);if(!empty($attrs['link'])&&!empty($attrs['text']))echo '<a class="lm-footer-contact" href="'.esc_url($attrs['link']).'">'.esc_html($attrs['text']).'</a>';
 }
}
function lm_shell_footer_news(){
 $config=get_option('widget_flatsome_recent_posts',array());$instance=$config[18]??array();
 $news=new WP_Query(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>max(1,min(10,(int)($instance['number']??4))),'ignore_sticky_posts'=>true,'no_found_rows'=>true));
 echo '<section class="lm-footer-news" aria-labelledby="lm-footer-news-title"><h2 id="lm-footer-news-title">'.esc_html($instance['title']??'Latest News').'</h2><ul>';
 foreach($news->posts as $post){
  echo '<li><time datetime="'.esc_attr(get_the_date('c',$post)).'"><span>'.esc_html(get_the_date('d',$post)).'</span>'.esc_html(get_the_date('M',$post)).'</time><div><a class="lm-footer-news-link" href="'.esc_url(get_permalink($post)).'">'.esc_html(get_the_title($post)).'</a>';
  $count=(int)get_comments_number($post);$label=$count===0?'No Comments':sprintf(_n('%s Comment','%s Comments',$count,'landmarkus-pilot'),number_format_i18n($count));
  echo '<a class="lm-footer-comments" href="'.esc_url(get_comments_link($post)).'" aria-label="'.esc_attr($label.' on '.get_the_title($post)).'">'.esc_html($label).'</a></div></li>';
 }
 echo '</ul></section>';
}
function lm_shell_footer_categories(){
 if(!class_exists('WC_Widget_Product_Categories'))return;
 $config=get_option('widget_woocommerce_product_categories',array());$instance=$config[15]??array('title'=>'Product Categories','hide_empty'=>true,'hierarchical'=>true,'show_children_only'=>true);
 the_widget('WC_Widget_Product_Categories',$instance,array('before_widget'=>'<section class="lm-footer-categories" aria-labelledby="lm-footer-categories-title">','after_widget'=>'</section>','before_title'=>'<h2 id="lm-footer-categories-title">','after_title'=>'</h2>'));
}
function lm_shell_copyright(){
 $legacy=get_option('theme_mods_flatsome-child',array());$text=$legacy['footer_left_text']??'';
 echo wp_kses(str_replace('[ux_current_year]',wp_date('Y'),$text),array('strong'=>array(),'a'=>array('href'=>array())));
}
