<?php
/** 颜色/尺码展示组件与Local PDP原生选项外观；不承载交易规则。 */
defined('ABSPATH') || exit;
function lm_pilot_option_values($product) {
 $options=array();$attributes=$product->get_attributes();
 foreach(array('pa_color'=>'Colors','pa_size'=>'Sizes') as $key=>$label){
  if(!isset($attributes[$key]) || !$attributes[$key]->get_visible())continue;
  $values=wc_get_product_terms($product->get_id(),$key,array('fields'=>'names'));
  if(is_wp_error($values) || !$values)continue;
  $options[$label]=array_map(static function($v){return html_entity_decode($v,ENT_QUOTES,'UTF-8');},$values);
 }
 return $options;
}
function lm_pilot_option_list($label,$values) {
 $type=$label==='Colors'?'colors':'sizes';$long=$type==='sizes' && max(array_map('strlen',$values))>6;
 $html='<ul class="lm-option-values lm-option-values--'.$type.($long?' lm-option-values--long':'').'" aria-label="'.esc_attr($label).'">';
 foreach($values as $value)$html.='<li>'.esc_html($value).'</li>';
 return $html.'</ul>';
}
function lm_pilot_options_markup($options) {
 $html='';
 foreach($options as $label=>$values){
  $count=count($values);
  $html.='<div class="lm-option-group" data-option-label="'.esc_attr($label).'"><div class="lm-option-heading"><h3>'.esc_html($label).'</h3><span>'.esc_html($count.' '.($count===1?'option':'options')).'</span></div>'.lm_pilot_option_list($label,$values).'</div>';
 }
 return $html;
}
