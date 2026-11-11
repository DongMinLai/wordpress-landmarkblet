<?php
/** 渐进增强：原生GET搜索保留；采购条件可单独忽略或用真实筛选重新指定。 */
defined('ABSPATH') || exit;
add_action('after_setup_theme',static function(){add_theme_support('landmarkus-smart-search');});
add_action('wp_enqueue_scripts',static function(){
 if(!class_exists('LandmarkUS_Smart_Search'))return;
 wp_enqueue_style('landmark-smart-search',get_stylesheet_directory_uri().'/smart-search.css',array('landmark-shared-shell'),(string)filemtime(__DIR__.'/smart-search.css'));
 wp_enqueue_script('landmark-smart-search',get_stylesheet_directory_uri().'/smart-search.js',array(),(string)filemtime(__DIR__.'/smart-search.js'),true);
 $request=LandmarkUS_Smart_Search::current_request();wp_localize_script('landmark-smart-search','lmSmartSearch',array('endpoint'=>admin_url('admin-ajax.php'),'scope'=>$request['scope'],'term'=>$request['term']));
},120);
function lm_smart_search_summary(){
 $state=LandmarkUS_Smart_Search::$state;if(!$state)return;
 echo '<section class="lm-search-interpretation" aria-label="Search interpretation">';
 foreach($state['notices'] as $notice)echo '<p>'.esc_html($notice).'</p>';
 if($state['conditions']){echo '<p class="lm-search-caption">Recognized from your search</p><nav aria-label="Recognized purchasing conditions">';foreach($state['conditions'] as $c){$skip=implode(',',array_unique(array_merge($state['request']['skip'],array($c['dimension']))));echo '<a href="'.esc_url(lm_catalog_url(null,array('lm_skip'=>$skip))).'" aria-label="'.esc_attr('Remove recognized '.$c['dimension'].': '.$c['label']).'"><span>'.esc_html(ucfirst($c['dimension']).': '.$c['label']).'</span><span aria-hidden="true">×</span></a>';}echo '</nav><p class="lm-search-help">Remove a recognized condition here, or use the filters to choose another color or size.</p>';}
 echo '</section>';
}
