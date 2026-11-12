<?php
/**
 * Template Name: LandmarkUS Golf Color Gallery
 * Template Post Type: page
 */

defined('ABSPATH') || exit;
require_once __DIR__ . '/collection.php';

$golf_page = lm_golf_collection_page();
$gallery = get_queried_object();
if (!$golf_page || !($gallery instanceof WP_Post) || !is_page() ||
    !function_exists('wc_get_product') || $gallery->post_name !== 'colors' ||
    (int) $gallery->post_parent !== (int) $golf_page->ID ||
    ($gallery->post_status !== 'publish' && !(is_preview() && current_user_can('edit_post', $gallery->ID)))) {
    $fallback = locate_template(array('page.php', 'singular.php', 'index.php'));
    if ($fallback) load_template($fallback);
    return;
}

add_action('wp_enqueue_scripts', static function () {
    wp_enqueue_style(
        'landmark-golf-collection',
        get_stylesheet_directory_uri() . '/golf-collection/golf.css',
        array('landmark-shared-shell'),
        (string) filemtime(__DIR__ . '/golf.css')
    );
}, 135);

$golf_selection = lm_golf_collection_selection($golf_page->ID);
require __DIR__ . '/gallery.php';
