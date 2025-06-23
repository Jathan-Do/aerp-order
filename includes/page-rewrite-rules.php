<?php
// === REWRITE RULES FOR FRONTEND DASHBOARD ===
add_action('init', function () {
    add_rewrite_rule('^aerp-order-orders/?$', 'index.php?aerp_order_page=orders', 'top');
    add_rewrite_rule('^aerp-order-orders/([0-9]+)/?$', 'index.php?aerp_order_page=order_detail&aerp_order_id=$matches[1]', 'top');

    $rules = get_option('rewrite_rules');
    if ($rules && !isset($rules['^aerp-order-orders/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-order-orders/([0-9]+)/?$'])) {
        flush_rewrite_rules();
    }
});

add_action('template_redirect', function () {
    $page = get_query_var('aerp_order_page');
    if (in_array($page, ['orders', 'order_detail'], true)) {
        remove_filter('template_redirect', 'redirect_canonical');
    }
}, 0);

add_filter('query_vars', function ($vars) {
    $vars[] = 'aerp_order_page';
    $vars[] = 'aerp_order_id';
    $vars[] = 'action';
    $vars[] = 'paged';
    $vars[] = 's';
    $vars[] = 'orderby';
    $vars[] = 'order';
    return $vars;
});

add_action('template_redirect', function () {
    $aerp_order_page = get_query_var('aerp_order_page');
    $aerp_order_id = get_query_var('aerp_order_id');
    $action_from_get = get_query_var('action') ?? '';

    if ($aerp_order_page) {
        $template_name = '';
        switch ($aerp_order_page) {
            case 'orders':
                switch ($action_from_get) {
                    case 'add':
                        $template_name = 'order/form-add.php';
                        break;
                    case 'edit':
                        $template_name = 'order/form-edit.php';
                        break;
                    case 'delete':
                        AERP_Frontend_Order_Manager::handle_single_delete();
                        return;
                    default:
                        $template_name = 'order/list.php';
                        break;
                }
                break;
            case 'order_detail':
                // Có thể mở rộng chi tiết đơn hàng ở đây
                $template_name = 'order/detail.php';
                break;
        }
        if ($template_name) {
            include AERP_ORDER_PATH . 'frontend/admin/' . $template_name;
            exit;
        }
    }
});

add_filter('template_include', function ($template) {
    // Có thể mở rộng nếu cần
    return $template;
});
