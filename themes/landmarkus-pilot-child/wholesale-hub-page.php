<?php defined('ABSPATH') || exit;get_header(); ?>
<main id="main" class="lm-hub-main lm-shell"><?php while(have_posts()){the_post();the_content();} ?></main>
<?php get_footer(); ?>
