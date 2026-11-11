<?php
defined('ABSPATH') || exit;
function lm_trust_page(){return is_page('our-supply-chain')?'supply':(is_page('quality-control')?'qc':'');}
add_filter('template_include',static function($template){return lm_trust_page()?__DIR__.'/trust-page.php':$template;},900);
add_action('wp_enqueue_scripts',static function(){if(!lm_trust_page())return;wp_enqueue_style('landmark-trust-pages',get_stylesheet_directory_uri().'/trust-pages.css',array('landmark-shared-shell'),(string)filemtime(__DIR__.'/trust-pages.css'));},130);
// Candidate navigation only: add one owned published QC child; preserve menu database and all original items.
add_filter('landmarkus_shell_menu_items',static function($items){
 $page=get_page_by_path('quality-control');if(!$page||$page->post_status!=='publish'||get_post_meta($page->ID,'_lm_trust_owner',true)!=='PV01')return $items;
 $parent=null;foreach($items as $item){$path=untrailingslashit((string)wp_parse_url($item->url,PHP_URL_PATH));if($path==='/quality-control')return $items;if((int)$item->menu_item_parent===0&&$path==='/about-us')$parent=$item;}
 if(!$parent)return $items;
 $items[]=(object)array('ID'=>-$page->ID,'menu_item_parent'=>(string)$parent->ID,'title'=>'Quality Control','url'=>get_permalink($page),'xfn'=>'','target'=>'');return $items;
});
