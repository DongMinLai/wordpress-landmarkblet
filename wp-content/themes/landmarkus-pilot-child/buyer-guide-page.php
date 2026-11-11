<?php
/** Template Name: Landmark Wholesale Buying Guide */
defined('ABSPATH') || exit;
get_header();
echo '<main id="main" class="lm-guide-main lm-shell">';
while(have_posts()){the_post();the_content();}
echo '</main>';
get_footer();
