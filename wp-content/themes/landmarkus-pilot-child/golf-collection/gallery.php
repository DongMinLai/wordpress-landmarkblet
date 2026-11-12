<?php
defined('ABSPATH') || exit;
get_header();
?>
<main id="main" class="lm-golf-collection">
    <div class="lm-golf-wrap lm-golf-gallery">
        <a class="lm-golf-gallery-back" href="<?php echo esc_url(get_permalink($golf_page) . '#golf-lineup'); ?>">Back to golf belts</a>
        <h1>Golf Belt Colors</h1>
        <?php foreach ($golf_selection as $golf_row) :
            $golf_belt = lm_golf_collection_product($golf_row['sku']);
            if (!$golf_belt) continue;
            $golf_colors = lm_golf_collection_colors($golf_belt);
            if (!$golf_colors) continue;
            $golf_group = 'golf-colors-' . $golf_belt->get_id();
            ?>
            <section class="lm-golf-gallery-group" aria-labelledby="<?php echo esc_attr($golf_group); ?>">
                <h2 id="<?php echo esc_attr($golf_group); ?>"><?php echo esc_html(lm_pilot_card_name($golf_belt)); ?></h2>
                <div class="lm-golf-gallery-grid">
                    <?php foreach ($golf_colors as $golf_color) : ?>
                        <figure>
                            <?php echo wp_get_attachment_image($golf_color['image_id'], 'woocommerce_single', false,
                                array('alt' => '', 'loading' => 'lazy', 'sizes' => '(max-width: 760px) 44vw, 22vw')); ?>
                            <figcaption><?php echo esc_html($golf_color['name']); ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</main>
<?php get_footer(); ?>
