<?php defined('ABSPATH') || exit; ?>
<footer class="lm-footer lm-original-footer"><div class="lm-shell lm-footer-grid">
<section class="lm-footer-about" aria-labelledby="lm-footer-about-title"><h2 id="lm-footer-about-title"><?php $widgets=get_option('widget_block_widget',array());echo esc_html($widgets[2]['title']??'About us'); ?></h2><div class="lm-footer-contact-info"><?php lm_shell_footer_contact(); ?></div></section>
<?php lm_shell_footer_news();lm_shell_footer_categories(); ?>
</div><div class="lm-footer-bottom"><div class="lm-shell"><?php lm_shell_copyright(); ?><nav class="lm-footer-help" aria-label="Buying help"><a href="<?php echo esc_url(home_url('/wholesale-buying-guide/')); ?>">Buying guide &amp; FAQs</a></nav></div></div></footer>
<?php wp_footer(); ?></body></html>
