<?php
/** Template Name: Landmark Wholesale Pilot */
defined('ABSPATH') || exit;
get_header();
echo '<main id="main" class="lm-main">';
while(have_posts()){the_post();the_content();}
echo '</main>';
get_footer();
