<?php
defined('ABSPATH') || exit;

function lm_golf_collection_page() {
    $page = get_page_by_path('wholesale-golf-belts');
    return $page && $page->post_status === 'publish' &&
        get_post_meta($page->ID, '_lm_golf_collection_owner', true) === 'PV02' ? $page : null;
}

function lm_golf_collection_active() {
    $page = lm_golf_collection_page();
    return $page && is_page($page->ID) && function_exists('wc_get_product');
}

function lm_golf_collection_product($sku) {
    if (!function_exists('wc_get_product_id_by_sku') || !$sku) return null;
    $id = wc_get_product_id_by_sku($sku);
    if (!$id || !has_term('golf-belts', 'product_cat', $id)) return null;
    $product = wc_get_product($id);
    return $product && $product->get_status() === 'publish' && $product->is_visible() ? $product : null;
}

function lm_golf_collection_selection($page_id) {
    $saved = get_post_meta($page_id, '_lm_golf_collection_selection', true);
    if (is_array($saved) && count($saved) === 6) return $saved;
    return array_map(static function ($sku) {
        return array('sku' => $sku, 'price' => '');
    }, array('LA2115', 'LA2116', 'GB-01', 'LA1197', 'LA1197B', 'LA2091'));
}

function lm_golf_collection_validate($skus, $prices) {
    if (!is_array($skus) || !is_array($prices) || count($skus) !== 6 || count($prices) !== 6) return null;
    $rows = array();
    $used = array();
    foreach (array_values($skus) as $i => $sku) {
        $price = array_values($prices)[$i];
        if (!is_string($sku) || !is_string($price)) return null;
        $product = lm_golf_collection_product(trim($sku));
        $price = trim($price);
        if (!$product || isset($used[$product->get_id()]) ||
            ($price !== '' && !preg_match('/^[0-9]{1,6}(?:\.[0-9]{1,2})?$/D', $price))) return null;
        $used[$product->get_id()] = true;
        $rows[] = array('sku' => $product->get_sku(), 'price' => $price === '' ? '' : wc_format_decimal($price, 2));
    }
    return $rows;
}

function lm_golf_collection_can_display_price() {
    return class_exists('LandmarkUS_Price_Access') && !LandmarkUS_Price_Access::restricted();
}

function lm_golf_collection_admin_url() {
    return admin_url('edit.php?post_type=page&page=landmark-golf-collection');
}

add_action('admin_menu', static function () {
    $page = lm_golf_collection_page();
    if (!$page || !current_user_can('edit_post', $page->ID)) return;
    add_submenu_page('edit.php?post_type=page', 'Golf Collection', 'Golf Collection',
        'edit_pages', 'landmark-golf-collection', 'lm_golf_collection_settings');
});

function lm_golf_collection_settings() {
    $page = lm_golf_collection_page();
    if (!$page || !current_user_can('edit_post', $page->ID)) wp_die('无权编辑 Golf 系列页。');
    $products = array();
    $ids = get_posts(array(
        'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids',
        'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC',
        'tax_query' => array(array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'golf-belts', 'include_children' => false)),
    ));
    foreach ($ids as $id) {
        $candidate = wc_get_product($id);
        if ($candidate && $candidate->get_sku() && $candidate->is_visible()) $products[$candidate->get_sku()] = $candidate;
    }
    $status = isset($_GET['golf_status']) && is_string($_GET['golf_status']) ? sanitize_key(wp_unslash($_GET['golf_status'])) : '';
    ?>
    <div class="wrap">
        <h1>Golf Collection</h1>
        <?php if ($status === 'saved') : ?>
            <div class="notice notice-success"><p>已保存 Golf 系列选品和展示价。</p></div>
        <?php elseif ($status === 'invalid') : ?>
            <div class="notice notice-error"><p>未保存。请选择六个不同的 Golf 商品，展示价留空或填写最多两位小数的非负金额。</p></div>
        <?php elseif ($status === 'error') : ?>
            <div class="notice notice-error"><p>无法保存，请重试。</p></div>
        <?php endif; ?>
        <p>按顺序选择六个商品。展示价单位为美元／条，留空时引导到商品页查看价格。展示价遵循现有登录权限，商品、购物车和结算金额由 WooCommerce 管理。</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="lm_golf_collection_save">
            <?php wp_nonce_field('lm_golf_collection_save_' . $page->ID); ?>
            <table class="widefat striped">
                <thead><tr><th scope="col">位置</th><th scope="col">Golf 商品</th><th scope="col">展示价 (USD)</th></tr></thead>
                <tbody>
                <?php foreach (lm_golf_collection_selection($page->ID) as $i => $row) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($i + 1); ?></th>
                        <td><select name="golf_sku[]" required aria-label="<?php echo esc_attr('位置 ' . ($i + 1) . ' 商品'); ?>">
                            <option value="">选择 Golf 商品</option>
                            <?php foreach ($products as $sku => $product) : ?>
                                <option value="<?php echo esc_attr($sku); ?>" <?php selected($row['sku'], $sku); ?>><?php echo esc_html($sku . ' · ' . $product->get_name()); ?></option>
                            <?php endforeach; ?>
                        </select></td>
                        <td><input name="golf_price[]" type="number" min="0" max="999999.99" step="0.01" value="<?php echo esc_attr($row['price']); ?>"
                            aria-label="<?php echo esc_attr('位置 ' . ($i + 1) . ' 展示价'); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button('保存选品和展示价'); ?>
        </form>
    </div>
    <?php
}

add_action('admin_post_lm_golf_collection_save', static function () {
    $page = lm_golf_collection_page();
    if (!$page || !current_user_can('edit_post', $page->ID)) wp_die('无权编辑 Golf 系列页。', '', array('response' => 403));
    check_admin_referer('lm_golf_collection_save_' . $page->ID);
    $rows = lm_golf_collection_validate(wp_unslash($_POST['golf_sku'] ?? array()), wp_unslash($_POST['golf_price'] ?? array()));
    $status = 'invalid';
    if ($rows !== null) {
        $key = '_lm_golf_collection_selection';
        $status = get_post_meta($page->ID, $key, true) === $rows || update_post_meta($page->ID, $key, $rows)
            ? 'saved' : 'error';
    }
    wp_safe_redirect(add_query_arg('golf_status', $status, lm_golf_collection_admin_url()));
    exit;
});
