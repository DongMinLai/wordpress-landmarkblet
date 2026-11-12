<?php
defined('ABSPATH') || exit;
$golf_page = lm_golf_collection_page();
$golf_assets = get_stylesheet_directory_uri() . '/golf-collection/media/';
$golf_uploads = trailingslashit(wp_get_upload_dir()['baseurl']) . '2026/05/';
$golf_selection = lm_golf_collection_selection($golf_page->ID);
$golf_gallery = lm_golf_collection_gallery_page($golf_page);
$golf_show_price = lm_golf_collection_can_display_price();
$golf_requires_login = !$golf_show_price && class_exists('LandmarkUS_Price_Access');
$golf_term = get_term_by('slug', 'golf-belts', 'product_cat');
$golf_catalog = $golf_term ? get_term_link($golf_term) : wc_get_page_permalink('shop');
if (is_wp_error($golf_catalog)) $golf_catalog = wc_get_page_permalink('shop');
$golf_woven = lm_golf_collection_product('LA2115');
$golf_reversible = lm_golf_collection_product('GB-01');
$golf_ratchet = lm_golf_collection_product('LA2091');
get_header();
?>
<main id="main" class="lm-golf-collection">
    <section class="lm-golf-hero" aria-labelledby="lm-golf-title">
        <img class="lm-golf-hero-image" src="<?php echo esc_url($golf_assets . 'golf-hero-3.jpg'); ?>" width="3000" height="1688"
            fetchpriority="high" alt="Golfer wearing a light woven belt at the end of his swing">
        <div class="lm-golf-wrap lm-golf-hero-copy">
            <p class="lm-golf-eyebrow">LandmarkUS Golf Collection</p>
            <h1 id="lm-golf-title">Wholesale<br>Golf Belts</h1>
            <p class="lm-golf-deck">Choose golf belts for your pro shop.</p>
            <p class="lm-golf-order-note">12 belts total · Mix styles · Priced per belt</p>
            <a class="lm-golf-button" href="#golf-lineup">Browse featured golf belts</a>
        </div>
    </section>

    <section class="lm-golf-wrap lm-golf-lineup" id="golf-lineup" aria-labelledby="lm-golf-lineup-title">
        <div class="lm-golf-section-head">
            <h2 id="lm-golf-lineup-title">Featured Golf Belts</h2>
            <?php if ($golf_gallery) : ?>
                <a href="<?php echo esc_url(get_permalink($golf_gallery)); ?>">View All Colors</a>
            <?php endif; ?>
        </div>
        <?php if ($golf_requires_login) : ?>
            <p class="lm-golf-price-note">Browse styles and sizes before signing in. <a href="<?php echo esc_url(LandmarkUS_Price_Access::login_url(get_permalink($golf_page))); ?>">Log in for wholesale prices</a>.</p>
        <?php endif; ?>
        <div class="lm-golf-products">
            <?php foreach ($golf_selection as $golf_row) :
                $golf_belt = lm_golf_collection_product($golf_row['sku']);
                if (!$golf_belt) continue;
                $golf_name = lm_pilot_card_name($golf_belt);
                $golf_colors = lm_golf_collection_colors($golf_belt);
                $golf_default_color = reset($golf_colors);
                foreach ($golf_colors as $golf_color) {
                    if ((int) $golf_color['image_id'] === (int) $golf_belt->get_image_id()) {
                        $golf_default_color = $golf_color;
                        break;
                    }
                }
                $golf_image_attrs = array('loading' => 'lazy', 'alt' => '', 'sizes' => '(max-width: 760px) 46vw, 31vw');
                ?>
                <article class="lm-golf-product">
                    <a class="lm-golf-product-image" href="<?php echo esc_url($golf_belt->get_permalink()); ?>" aria-label="<?php echo esc_attr($golf_name); ?>">
                        <?php echo $golf_default_color
                            ? wp_get_attachment_image($golf_default_color['image_id'], 'woocommerce_single', false, $golf_image_attrs)
                            : $golf_belt->get_image('woocommerce_single', $golf_image_attrs); ?>
                    </a>
                    <div class="lm-golf-product-info">
                        <p class="lm-golf-sku"><?php echo esc_html($golf_belt->get_sku()); ?></p>
                        <h3><a href="<?php echo esc_url($golf_belt->get_permalink()); ?>"><?php echo esc_html($golf_name); ?></a></h3>
                        <?php if (count($golf_colors) > 1) : ?>
                            <div class="lm-golf-colors" hidden>
                                <p class="lm-golf-color-name" aria-live="polite"><?php echo esc_html($golf_default_color['name']); ?></p>
                                <div class="lm-golf-swatches" role="group" aria-label="<?php echo esc_attr($golf_name . ' colors'); ?>">
                                    <?php foreach ($golf_colors as $golf_color) : ?>
                                        <button type="button" class="lm-golf-swatch"
                                            aria-pressed="<?php echo $golf_color === $golf_default_color ? 'true' : 'false'; ?>"
                                            aria-label="<?php echo esc_attr($golf_color['name']); ?>"
                                            title="<?php echo esc_attr($golf_color['name']); ?>"
                                            data-src="<?php echo esc_url($golf_color['src']); ?>"
                                            data-srcset="<?php echo esc_attr($golf_color['srcset']); ?>">
                                            <?php echo wp_get_attachment_image($golf_color['image_id'], 'woocommerce_gallery_thumbnail', false, array('alt' => '', 'loading' => 'lazy', 'sizes' => '34px')); ?>
                                        </button>
                                    <?php endforeach; ?>
                                    <button type="button" class="lm-golf-swatch-toggle" aria-expanded="false"
                                        aria-label="Show more colors" hidden>+</button>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($golf_show_price && $golf_row['price'] !== '') : ?>
                            <p class="lm-golf-price"><?php echo wp_kses_post(wc_price($golf_row['price'], array('currency' => 'USD'))); ?> <span>/ belt</span></p>
                        <?php elseif (!$golf_requires_login) : ?>
                            <a class="lm-golf-price-link" href="<?php echo esc_url($golf_belt->get_permalink()); ?>">See product pricing</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($golf_show_price && array_filter(array_column($golf_selection, 'price'), static function ($price) { return $price !== ''; })) : ?>
            <p class="lm-golf-price-note">Collection display prices are per belt. See each product page for current ordering prices.</p>
        <?php endif; ?>
    </section>

    <section class="lm-golf-style-band" id="golf-styles" aria-labelledby="lm-golf-styles-title">
        <div class="lm-golf-wrap">
            <div class="lm-golf-section-head">
                <div><p class="lm-golf-eyebrow">The pro shop lineup</p>
                    <h2 id="lm-golf-styles-title">Compare golf belt styles</h2></div>
                <a href="<?php echo esc_url($golf_catalog); ?>">Shop all golf belts</a>
            </div>
            <div class="lm-golf-looks">
                <article>
                    <figure class="lm-golf-look-photo">
                        <img src="<?php echo esc_url($golf_assets . 'woven-la2115-skyblue-approved.jpg'); ?>" width="1122" height="1402"
                            loading="lazy" alt="LA2115 Skyblue woven belt styling with ivory golf trousers">
                    </figure>
                    <div class="lm-golf-look-copy">
                        <p class="lm-golf-eyebrow">LA2115</p>
                        <h3>Woven stretch</h3>
                        <p>A braided stretch strap with a classic pin buckle.</p>
                        <?php if ($golf_woven) : ?><a href="<?php echo esc_url($golf_woven->get_permalink()); ?>">Explore LA2115</a><?php endif; ?>
                    </div>
                </article>
                <article>
                    <figure class="lm-golf-look-photo">
                        <img src="<?php echo esc_url($golf_assets . 'gb-01-model-11.jpg'); ?>" width="894" height="894"
                            loading="lazy" alt="Brown GB-01 belt with a round ball-marker buckle, worn with a navy polo and light golf trousers">
                    </figure>
                    <div class="lm-golf-look-copy">
                        <p class="lm-golf-eyebrow">GB-01</p>
                        <h3>Reversible leather</h3>
                        <p>A two-sided leather strap with a ball marker in the buckle.</p>
                        <?php if ($golf_reversible) : ?><a href="<?php echo esc_url($golf_reversible->get_permalink()); ?>">Explore GB-01</a><?php endif; ?>
                    </div>
                </article>
                <?php if ($golf_ratchet) : ?>
                    <article>
                        <figure class="lm-golf-look-photo">
                            <img src="<?php echo esc_url($golf_assets . 'la2091-ratchet-model.jpg'); ?>" width="1254" height="1254"
                                loading="lazy" alt="Red ratchet belt worn with a navy polo and light golf trousers">
                        </figure>
                        <div class="lm-golf-look-copy">
                            <p class="lm-golf-eyebrow">LA2091</p>
                            <h3>Leather ratchet</h3>
                            <p>A leather strap with a ratchet buckle.</p>
                            <a href="<?php echo esc_url($golf_ratchet->get_permalink()); ?>">Explore LA2091</a>
                        </div>
                    </article>
                <?php endif; ?>
            </div>
            <details class="lm-golf-disclosure lm-golf-detail">
                <summary>See LA2115 construction details</summary>
                <div class="lm-golf-detail-grid">
                    <figure>
                        <img src="<?php echo esc_url($golf_uploads . 'LA2115_skyblue_4_1500_1000.jpg'); ?>" width="1500" height="1000" loading="lazy"
                             alt="Actual LA2115 belt laid flat, showing its braided strap, buckle, keeper and tip">
                        <figcaption>Full belt</figcaption>
                    </figure>
                    <?php foreach (array(
                        array('5', 'Stretch weave', 'Hands showing the braided structure of the elastic strap'),
                        array('2', 'Single-pin buckle', 'Close-up of the metal single-pin buckle on the woven belt'),
                        array('3', 'Stitched suede trim', 'Close-up of the stitched belt tip and keeper'),
                    ) as $golf_detail) : ?>
                        <figure>
                            <img src="<?php echo esc_url($golf_uploads . 'LA2115_skyblue_' . $golf_detail[0] . '_1000_1000.jpg'); ?>"
                                width="1000" height="1000" loading="lazy" alt="<?php echo esc_attr($golf_detail[2]); ?>">
                            <figcaption><?php echo esc_html($golf_detail[1]); ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
    </section>

    <section class="lm-golf-wrap lm-golf-finish" aria-labelledby="lm-golf-finish-title">
        <div class="lm-golf-scorecard">
            <div><p class="lm-golf-eyebrow">The pro shop scorecard</p>
                <h2 id="lm-golf-finish-title">Build your golf assortment</h2></div>
            <div class="lm-golf-score-rows">
                <p><span>01</span><strong>Stock your pro shop</strong><a href="<?php echo esc_url($golf_catalog); ?>">Shop all golf belts</a></p>
                <p><span>02</span><strong>Plan a club or tournament project</strong><a href="<?php echo esc_url(home_url('/custom-belts/')); ?>">Explore custom belts</a></p>
            </div>
        </div>
        <p class="lm-golf-guide-link"><a href="<?php echo esc_url(home_url('/wholesale-buying-guide/')); ?>">Wholesale buying guide</a></p>
        <details class="lm-golf-disclosure lm-golf-buying-details">
            <summary>Golf belt buying notes</summary>
            <div class="lm-golf-guide"><?php echo apply_filters('the_content', $golf_page->post_content); ?></div>
        </details>
    </section>
</main>
<?php get_footer(); ?>
