<?php
/** CF7/Flamingo接线；只作用于自有询盘表单，不改旧表单或价格/交易。 */
defined('ABSPATH') || exit;
final class LandmarkUS_Inquiry {
 public static $saved = 0;
 public static function form_id(){
  $posts=get_posts(array('post_type'=>'wpcf7_contact_form','name'=>'landmarkus-inquiry','post_status'=>'publish','numberposts'=>1,'meta_key'=>'_lm_inquiry_owner','meta_value'=>'PV01','fields'=>'ids'));return $posts?(int)$posts[0]:0;
 }
 public static function owned($form){return is_object($form)&&method_exists($form,'id')&&get_post_meta($form->id(),'_lm_inquiry_owner',true)==='PV01';}
 public static function local(){return function_exists('wp_get_environment_type')&&wp_get_environment_type()==='local';}
 public static function rate_key(){return 'lm_inquiry_rate_'.substr(hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'unknown',wp_salt('nonce')),0,24);}
 public static function render(){
  $id=self::form_id();if(!$id||!function_exists('wpcf7_contact_form')||!class_exists('Flamingo_Inbound_Message'))return '<p class="lm-form-unavailable" role="status">The inquiry form is temporarily unavailable. Please <a href="mailto:landmarkbelt@gmail.com">email the wholesale team</a>.</p>';
  $note=self::local()?'<p class="lm-local-inquiry-note">Local preview: inquiries are saved on this computer. Email delivery is disabled.</p>':'';
  return $note.do_shortcode('[contact-form-7 id="'.$id.'"]');
 }
 public static function init(){
  add_shortcode('landmark_inquiry_form',array(__CLASS__,'render'));
  // 导入的正式域名验证码无法在Local验证；仅当前本地自有表单隔离，不改配置/旧表单。
  add_filter('wpcf7_turnstile_sitekey',static function($key){
   if(!self::local())return $key;
   if(isset($_POST['_wpcf7']))return is_scalar($_POST['_wpcf7'])&&(int)$_POST['_wpcf7']===self::form_id()?'':$key;
   return !is_admin()&&is_page('contact-us')?'':$key;
  },20);
  add_filter('wpcf7_spam',static function($spam,$submission){
   if(!self::owned($submission->get_contact_form()))return $spam;
   $trap=$submission->get_posted_data('company-site');if(!empty($trap)||(int)get_transient(self::rate_key())>=10)return true;return $spam;
  },20,2);
  add_action('wpcf7_before_send_mail',static function($form){if(self::owned($form))set_transient(self::rate_key(),(int)get_transient(self::rate_key())+1,10*MINUTE_IN_SECONDS);});
  add_filter('wpcf7_skip_mail',static function($skip,$form){return self::local()&&self::owned($form)&&class_exists('Flamingo_Inbound_Message')?true:$skip;},20,2);
  add_filter('wpcf7_display_message',static function($message,$status){
   $form=class_exists('WPCF7_ContactForm')?WPCF7_ContactForm::get_current():null;
   if(self::local()&&self::owned($form)&&$status==='mail_sent_ok')return 'Your inquiry was saved in this local preview. Email delivery is disabled.';return $message;
  },20,2);
  add_action('wpcf7_after_flamingo',static function($result){
   $form=isset($result['contact_form_id'])?wpcf7_contact_form((int)$result['contact_form_id']):null;if(!self::owned($form))return;
   $id=absint($result['flamingo_inbound_id']??0);if($id&&get_post_type($id)==='flamingo_inbound'){update_post_meta($id,'_lm_inquiry_form',(int)$form->id());self::$saved=$id;}
   if(self::local()&&($result['status']??'')==='mail_sent'&&!self::$saved){$s=WPCF7_Submission::get_instance();if($s){$s->set_status('mail_failed');$s->set_response('Your inquiry could not be saved. Please try again or email landmarkbelt@gmail.com.');}}
  });
  add_filter('wpcf7_feedback_response',static function($response,$result){
   $form=isset($result['contact_form_id'])?wpcf7_contact_form((int)$result['contact_form_id']):null;
   if(self::local()&&self::owned($form)&&($response['status']??'')==='mail_sent'){
    if(self::$saved)$response['message']='Your inquiry was saved in this local preview. Email delivery is disabled.';
    else{$response['status']='mail_failed';$response['message']='Your inquiry could not be saved. Please try again or email landmarkbelt@gmail.com.';}
   }return $response;
  },20,2);
 }
}
LandmarkUS_Inquiry::init();
