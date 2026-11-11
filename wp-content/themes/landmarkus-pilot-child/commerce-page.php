<?php defined('ABSPATH') || exit;get_header(); ?>
<main id="main" class="lm-commerce-main lm-shell"><header class="lm-commerce-title"><h1><?php echo esc_html(is_order_received_page()?'Order received':get_the_title()); ?></h1></header><?php while(have_posts()){the_post();the_content();} ?></main>
<?php get_footer(); ?>
