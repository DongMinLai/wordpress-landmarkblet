<?php defined('ABSPATH') || exit;get_header(); ?>
<main id="main" class="lm-commerce-main lm-shell">
<?php if(is_product()): ?><div class="lm-product-detail"><?php woocommerce_content(); ?></div>
<?php else: lm_catalog_heading(); ?><div class="lm-catalog-layout"><div class="lm-catalog-filter-placeholder"><?php lm_catalog_filters(); ?></div><section class="lm-products lm-commerce-catalog lm-catalog-results" aria-label="Products"><?php if(!woocommerce_product_loop()){echo '<div class="lm-catalog-mobile-tools">';lm_catalog_filter_button();echo '</div>';} ?><?php lm_catalog_active_filters(); ?><?php add_action('woocommerce_before_shop_loop','lm_catalog_toolbar_open',15);add_action('woocommerce_before_shop_loop','lm_catalog_toolbar_close',99);woocommerce_content();remove_action('woocommerce_before_shop_loop','lm_catalog_toolbar_open',15);remove_action('woocommerce_before_shop_loop','lm_catalog_toolbar_close',99); ?></section></div><dialog id="lm-catalog-filter-dialog" class="lm-catalog-filter-dialog" aria-labelledby="lm-catalog-dialog-title"><header class="lm-catalog-dialog-header"><h2 id="lm-catalog-dialog-title">Filters</h2><button type="button" class="lm-catalog-close" aria-label="Close filters">×</button></header><div class="lm-catalog-dialog-content"></div></dialog>
<?php endif; ?>
</main>
<?php get_footer(); ?>
