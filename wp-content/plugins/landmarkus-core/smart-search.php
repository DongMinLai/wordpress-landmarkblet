<?php
/** 本地采购搜索：公开元数据索引、确定性条件和保守容错；金额实时读取且不入缓存。 */
defined('ABSPATH') || exit;
final class LandmarkUS_Smart_Search {
 const CACHE = 'landmarkus_search_v1';
 public static $state = null;
 public static function clean($value) { return is_string($value) ? mb_substr(sanitize_text_field(wp_unslash($value)),0,120) : ''; }
 public static function norm($value) { return strtolower(trim(preg_replace('/[^a-z0-9]+/i',' ',remove_accents(html_entity_decode($value,ENT_QUOTES,'UTF-8'))))); }
 public static function sku($value) { return str_replace(' ','',self::norm($value)); }
 public static function invalidate() { delete_transient(self::CACHE); }
 public static function index() {
  $cached=get_transient(self::CACHE);if(is_array($cached))return $cached;
  $rows=array();$ids=get_posts(array('post_type'=>'product','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'ASC','suppress_filters'=>false));
  _prime_post_caches($ids,true,true);
  foreach($ids as $id){$p=wc_get_product($id);if(!$p||get_post_field('post_password',$id)||$p->get_catalog_visibility()==='hidden')continue;
   $cats=wp_get_object_terms($id,'product_cat');$attrs=array();foreach(array('color','size') as $a){$terms=wc_get_product_terms($id,'pa_'.$a);$attrs[$a]=array();foreach($terms as $t)$attrs[$a][$t->slug]=html_entity_decode($t->name,ENT_QUOTES,'UTF-8');}
   $c=array();foreach($cats as $t){$c[$t->term_id]=array('name'=>html_entity_decode($t->name,ENT_QUOTES,'UTF-8'),'slug'=>$t->slug);foreach(get_ancestors($t->term_id,'product_cat','taxonomy') as $aid){$t2=get_term($aid,'product_cat');if($t2&&!is_wp_error($t2))$c[$aid]=array('name'=>html_entity_decode($t2->name,ENT_QUOTES,'UTF-8'),'slug'=>$t2->slug);}}
   $children=$p->is_type('variable')?$p->get_children():array();_prime_post_caches($children,false,true);$variants=array();$skus=array_filter(array($p->get_sku()));
   foreach($children as $vid){if(get_post_status($vid)!=='publish')continue;$v=wc_get_product($vid);if(!$v)continue;$vs=$v->get_sku();if($vs!=='')$skus[]=$vs;$variants[]=array('image'=>$v->get_image_id(),'color'=>$v->get_attribute('pa_color')?get_post_meta($vid,'attribute_pa_color',true):'','size'=>$v->get_attribute('pa_size')?get_post_meta($vid,'attribute_pa_size',true):'');}
   $rows[]=array('id'=>(int)$id,'sku'=>$p->get_sku(),'skus'=>array_values(array_unique($skus)),'title'=>$p->get_name(),'text'=>self::norm($p->get_name().' '.wp_strip_all_tags($p->get_short_description().' '.$p->get_description())),'visibility'=>$p->get_catalog_visibility(),'variable'=>$p->is_type('variable'),'cats'=>$c,'attrs'=>$attrs,'variants'=>$variants);
  }
  set_transient(self::CACHE,$rows,10*MINUTE_IN_SECONDS);return $rows;
 }
 public static function request($source=null){
  $v=is_array($source)?$source:$_GET;$out=array('q'=>self::clean($v['q']??($v['catalog_search']??($v['s']??''))),'scope'=>sanitize_key(is_string($v['scope']??null)?$v['scope']:'global'),'term'=>absint(is_scalar($v['term']??0)?($v['term']??0):0),'skip'=>array(),'color'=>array(),'size'=>array());
  if(!in_array($out['scope'],array('global','wholesale','category'),true))$out['scope']='global';
  foreach(array('color','size') as $a){$value=$v['filter_'.$a]??'';if(is_string($value))$out[$a]=array_slice(array_filter(array_map('sanitize_title',explode(',',wp_unslash($value)))),0,20);}
  $skip=$v['lm_skip']??'';if(is_string($skip))$out['skip']=array_values(array_intersect(explode(',',$skip),array('color','size','category')));return $out;
 }
 public static function current_request(){
  $v=$_GET;if(is_product_taxonomy()){$v['scope']='category';$v['term']=get_queried_object_id();}elseif(is_shop())$v['scope']='wholesale';else $v['scope']='global';return self::request($v);
 }
 private static function scoped($request){
  $root=get_term_by('slug','wholesale','product_cat');$term=$request['scope']==='wholesale'?($root?$root->term_id:-1):$request['term'];
  $object=$request['scope']==='category'?get_term($term):null;
  if($request['scope']==='category'&&(!$object||is_wp_error($object)||!in_array($object->taxonomy,array('product_cat','product_tag','pa_color','pa_size'),true)))return array();
  return array_values(array_filter(self::index(),static function($row)use($request,$term,$object){if($request['scope']==='global')return $row['visibility']!=='catalog';if($row['visibility']==='search')return false;if($object&&$object->taxonomy!=='product_cat')return has_term($term,$object->taxonomy,$row['id']);return isset($row['cats'][$term]);}));
 }
 private static function phrase($q,$phrase){$p=self::norm($phrase);return $p!==''&&preg_match('/(?:^| )'.preg_quote($p,'/').'(?: |$)/',$q);}
 private static function take(&$q,$phrase){$p=self::norm($phrase);$q=trim(preg_replace('/(?:^| )'.preg_quote($p,'/').'(?= |$)/',' ',$q));$q=preg_replace('/\s+/',' ',$q);}
 public static function search($request){
  $rows=self::scoped($request);$q=self::norm($request['q']);$result=array('ids'=>array(),'conditions'=>array(),'notices'=>array(),'kind'=>'exact','request'=>$request,'filters'=>array('color'=>$request['color'],'size'=>$request['size']),'categories'=>array());
  if($q===''){$result['ids']=array_column(array_filter($rows,static function($r)use($result){return self::fits($r,$result);}), 'id');return $result;}
  // 完整SKU永远先于语言解析；正规化只忽略空格/连字符等分隔符。
  $needle=self::sku($q);$exact=array();foreach($rows as $r)foreach($r['skus'] as $sku)if(self::sku($sku)===$needle){$exact[]=$r;break;}
  if($exact){$result['ids']=array_column(array_filter($exact,static function($r)use($result){return self::fits($r,$result);}), 'id');$result['kind']='sku';return $result;}
  $dict=array('color'=>array(),'size'=>array(),'category'=>array());foreach($rows as $r){foreach(array('color','size') as $a)foreach($r['attrs'][$a] as $slug=>$name){$key=self::norm($name);if($key!==''){$dict[$a][$key]['label']=$name;$dict[$a][$key]['values'][$slug]=$slug;}}foreach($r['cats'] as $id=>$cat){$name=preg_replace('/\s+(belts?|wholesale)$/i','',$cat['name']);$key=self::norm($name);if(!in_array($key,array('wholesale','made to order','men s belts','accessories','uncategorized','best sellers'),true)&&$key!==''){$dict['category'][$key]['label']=$name;$dict['category'][$key]['values'][$id]=$id;}}}
  // 统一美式/英式灰色拼写；不把Black/Brown组合误写成两个单色。
  foreach(array('gray'=>'grey','grey'=>'gray') as $a=>$b)if(isset($dict['color'][$a])&&isset($dict['color'][$b])){$all=$dict['color'][$a]['values']+$dict['color'][$b]['values'];$dict['color'][$a]['values']=$all;$dict['color'][$b]['values']=$all;}
  // 仅明确标签支持size，数字本身不等于合身区间；12pcs不成为size。
  $size_token='';if(preg_match('/\b(?:size|waist)\s+(\d+(?:\s*\/\s*\d+)?|xs|xl|s|m|l)\b/i',$request['q'],$sm)){$size_token=$sm[0];$value=self::norm($sm[1]);if(isset($dict['size'][$value])){$dict['size'][$value]['match']=$size_token;}else{$result['notices'][]='This size does not match an exact catalog label. Choose a size filter to confirm the fit.';self::take($q,$size_token);}}
  foreach(array('color','size','category') as $dimension){uksort($dict[$dimension],static function($a,$b){return strlen($b)-strlen($a);});foreach($dict[$dimension] as $phrase=>$entry){$match=$entry['match']??$phrase;if($dimension==='size'&&!isset($entry['match'])&&!preg_match('/\d.*\d|inch|length|medium|large|small/',$phrase))continue;
    if(!self::phrase($q,$match))continue;self::take($q,$match);if(in_array($dimension,$request['skip'],true))continue;$values=array_map('strval',array_values($entry['values']));$result['conditions'][]=array('dimension'=>$dimension,'label'=>$entry['label'],'values'=>$values);if($dimension==='category')$result['categories'][]=$values;else $result['filters'][$dimension]=array_values(array_unique(array_merge($result['filters'][$dimension],$values)));
   }}
  // 明确数量仅提示：此搜索不会加购，不从数字臆测价格或交期。
  if(preg_match('/\b\d+\s*(?:pcs|pieces|belts|units)\b/',$q,$qty)){$result['notices'][]='Order quantity is set on the product page or in your cart.';self::take($q,$qty[0]);}
  $q=trim(preg_replace('/\b(?:show|me|find|looking|for|please|a|an|the|in|with|and|color|colour|size|waist|wholesale|men|mens|s)\b/',' ',$q));$q=preg_replace('/\s+/',' ',$q);$q=preg_replace('/\bbelts\b/','belt',$q);
  $eligible=array_values(array_filter($rows,static function($r)use($result){return self::fits($r,$result);}));
  $matches=self::words($eligible,$q);if(!$matches&&$q!==''&&preg_match('/^[a-z]+\d+[a-z0-9]*$/',$needle)){
   $similar=array();foreach($eligible as $r)foreach($r['skus'] as $sku){$s=self::sku($sku);if(abs(strlen($s)-strlen($needle))<=1&&levenshtein($needle,$s)===1){$similar[]=$r;break;}}if($similar){$matches=$similar;$result['kind']='similar_sku';$result['notices'][]='Similar SKUs — check the model number before ordering.';}
  }
  if(!$matches&&$q!==''&&!preg_match('/\d/',$q)){
   $vocab=array();foreach($rows as $r)foreach(explode(' ',self::norm($r['title'])) as $word)if(strlen($word)>=4)$vocab[$word]=true;
   $corrected=array();$changed=false;foreach(explode(' ',$q) as $word){if(strlen($word)>=4&&!isset($vocab[$word])){$near=array();foreach($vocab as $candidate=>$unused)if(abs(strlen($candidate)-strlen($word))<=1&&levenshtein($word,$candidate)===1)$near[]=$candidate;if(count($near)===1){$word=$near[0];$changed=true;}}$corrected[]=$word;}
   $fixed=implode(' ',$corrected);if($changed){$matches=self::words($eligible,$fixed);if($matches){$result['kind']='spelling';$result['notices'][]='Showing spelling matches for “'.$fixed.'”.';}}
  }
  $result['ids']=array_values(array_unique(array_column($matches,'id')));return $result;
 }
 private static function fits($r,$state){
  foreach($state['categories'] as $group)if(!array_intersect($group,array_keys($r['cats'])))return false;
  foreach(array('color','size') as $a)if($state['filters'][$a]&&!array_intersect($state['filters'][$a],array_keys($r['attrs'][$a])))return false;
  if($r['variable']&&($state['filters']['color']||$state['filters']['size'])){foreach($r['variants'] as $v)if((!$state['filters']['color']||$v['color']===''||in_array($v['color'],$state['filters']['color'],true))&&(!$state['filters']['size']||$v['size']===''||in_array($v['size'],$state['filters']['size'],true)))return true;return false;}return true;
 }
 private static function words($rows,$q){
  if($q==='')return $rows;$tokens=explode(' ',$q);$scored=array();foreach($rows as $r){$score=0;$ok=true;foreach($tokens as $word){$hit=strpos($r['text'],$word)!==false;foreach($r['skus'] as $sku)if(strpos(self::sku($sku),self::sku($word))!==false){$hit=true;$score+=30;break;}if(!$hit){$ok=false;break;}if(strpos(self::norm($r['title']),$word)!==false)$score+=3;}if($ok){$r['_score']=$score;$scored[]=$r;}}usort($scored,static function($a,$b){return $b['_score']<=>$a['_score']?:$a['id']<=>$b['id'];});return $scored;
 }
 public static function ajax(){
  if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('Vary: Cookie',false);
  $request=self::request();if(mb_strlen($request['q'])<2)wp_send_json_success(array('products'=>array(),'conditions'=>array(),'notices'=>array(),'total'=>0));
  $key='lm_ss_rate_'.substr(hash('sha256',$_SERVER['REMOTE_ADDR']??'local'),0,20);$rate=(int)get_transient($key);if($rate>=120)wp_send_json_error(array('message'=>'Please pause briefly and try again.'),429);set_transient($key,$rate+1,60);
  $state=self::search($request);$allowed=LandmarkUS_Price_Access::mode()==='public'||is_user_logged_in();$products=array();$indexed=array_column(self::index(),null,'id');foreach(array_slice($state['ids'],0,6) as $id){$p=wc_get_product($id);if(!$p||$p->get_status()!=='publish'||$p->get_catalog_visibility()==='hidden')continue;$image=$p->get_image_id();if($state['filters']['color']||$state['filters']['size'])foreach($indexed[$id]['variants']??array() as $v){if((!$state['filters']['color']||$v['color']===''||in_array($v['color'],$state['filters']['color'],true))&&(!$state['filters']['size']||$v['size']===''||in_array($v['size'],$state['filters']['size'],true))&&!empty($v['image'])){$image=$v['image'];break;}}$name=$p->get_sku()?preg_replace('/(?:\s*\|\s*|\s+)'.preg_quote($p->get_sku(),'/').'\s*$/iu','',$p->get_name()):$p->get_name();$products[]=array('id'=>$id,'title'=>$name,'sku'=>$p->get_sku(),'url'=>get_permalink($id),'image'=>wp_get_attachment_image_url($image,'woocommerce_thumbnail')?:wc_placeholder_img_src(),'price_html'=>$allowed?wp_kses_post($p->get_price_html()):'','price_label'=>$allowed?'':'Log in to view prices');}
  wp_send_json_success(array('products'=>$products,'total'=>count($state['ids']),'conditions'=>$state['conditions'],'notices'=>$state['notices'],'kind'=>$state['kind']));
 }
 public static function init(){
  add_action('wp_ajax_landmarkus_smart_search',array(__CLASS__,'ajax'));add_action('wp_ajax_nopriv_landmarkus_smart_search',array(__CLASS__,'ajax'));
  foreach(array('save_post_product','save_post_product_variation','woocommerce_delete_product_transients','set_object_terms','edited_term','delete_term','deleted_post','added_post_meta','updated_post_meta','deleted_post_meta') as $hook)add_action($hook,array(__CLASS__,'invalidate'));
  add_action('woocommerce_product_query',static function($query){if(is_admin()||!$query->is_main_query()||!current_theme_supports('landmarkus-smart-search'))return;$request=self::current_request();if($request['q']==='')return;self::$state=self::search($request);$query->set('lm_original_search',$request['q']);$query->set('s','');$query->set('post__in',self::$state['ids']?:array(0));if(empty($_GET['orderby']))$query->set('orderby','post__in');},40);
  add_filter('woocommerce_redirect_single_search_result',static function($redirect){return self::$state&&self::$state['kind']!=='sku'?false:$redirect;});
 }
}
LandmarkUS_Smart_Search::init();
