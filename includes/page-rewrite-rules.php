<?php
// === REWRITE RULES FOR FRONTEND DASHBOARD ===
add_action('init', function () {
    add_rewrite_rule('^aerp-order-orders/?$', 'index.php?aerp_order_page=orders', 'top');
    add_rewrite_rule('^aerp-order-orders/([0-9]+)/?$', 'index.php?aerp_order_page=order_detail&aerp_order_id=$matches[1]', 'top');
    // Route cho quản lý kho
    add_rewrite_rule('^aerp-products/?$', 'index.php?aerp_product_page=products', 'top');
    add_rewrite_rule('^aerp-inventory-logs/?$', 'index.php?aerp_inventory_log_page=logs', 'top');
    add_rewrite_rule('^aerp-product-categories/?$', 'index.php?aerp_product_page=product-categories', 'top');
    add_rewrite_rule('^aerp-units/?$', 'index.php?aerp_product_page=units', 'top');
    add_rewrite_rule('^aerp-order-statuses/?$', 'index.php?aerp_order_status_page=statuses', 'top');
    add_rewrite_rule('^aerp-stocktake/?$', 'index.php?aerp_inventory_page=stocktake', 'top');
    add_rewrite_rule('^aerp-warehouses/?$', 'index.php?aerp_warehouse_page=warehouses', 'top');
    add_rewrite_rule('^aerp-inventory-transfers/?$', 'index.php?aerp_inventory_transfer_page=transfers', 'top');
    add_rewrite_rule('^aerp-suppliers/?$', 'index.php?aerp_supplier_page=suppliers', 'top');
    $rules = get_option('rewrite_rules');
    if ($rules && !isset($rules['^aerp-order-orders/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-order-orders/([0-9]+)/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-products/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-inventory-logs/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-product-categories/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-units/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-order-statuses/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-stocktake/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-warehouses/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-inventory-transfers/?$'])) {
        flush_rewrite_rules();
    }
    if ($rules && !isset($rules['^aerp-suppliers/?$'])) {
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
    $vars[] = 'aerp_product_page';
    $vars[] = 'product_id';
    $vars[] = 'id';
    $vars[] = 'action';
    $vars[] = 'paged';
    $vars[] = 's';
    $vars[] = 'orderby';
    $vars[] = 'order';
    $vars[] = 'aerp_inventory_log_page';
    $vars[] = 'type';
    $vars[] = 'aerp_order_status_page';
    $vars[] = 'aerp_inventory_page';
    $vars[] = 'aerp_warehouse_page';
    $vars[] = 'aerp_inventory_transfer_page';
    $vars[] = 'aerp_supplier_page';
    return $vars;
});

add_action('template_redirect', function () {
    $aerp_order_page = get_query_var('aerp_order_page');
    $aerp_order_id = get_query_var('aerp_order_id');
    $aerp_product_page = get_query_var('aerp_product_page');
    $product_id = get_query_var('product_id');
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
    // Route cho quản lý kho frontend
    if ($aerp_product_page === 'products') {
        $template_name = '';
        switch ($action_from_get) {
            case 'add':
                $template_name = 'product/product-form.php';
                break;
            case 'edit':
                $template_name = 'product/product-form.php';
                break;
            case 'delete':
                AERP_Product_Manager::handle_single_delete();
                return;
            default:
                $template_name = 'product/list.php';
                break;
        }
        if ($template_name) {
            include AERP_ORDER_PATH . 'frontend/admin/' . $template_name;
            exit;
        }
    }
    $aerp_inventory_log_page = get_query_var('aerp_inventory_log_page');
    if ($aerp_inventory_log_page === 'logs') {
        switch ($action_from_get) {
            case 'add':
                include AERP_ORDER_PATH . 'frontend/admin/inventory/log-form.php';
                break;
            case 'edit':
                include AERP_ORDER_PATH . 'frontend/admin/inventory/log-form.php';
                break;
            case 'delete':
                AERP_Inventory_Log_Manager::handle_single_delete();
                return;
            default:
                include AERP_ORDER_PATH . 'frontend/admin/inventory/log-list.php';
                break;
        }
        exit;
    }
    if ($aerp_product_page === 'product-categories') {
        $template_name = '';
        switch ($action_from_get) {
            case 'add':
                $template_name = 'category/form.php';
                break;
            case 'edit':
                $template_name = 'category/form.php';
                break;
            case 'delete':
                AERP_Category_Manager::handle_single_delete();
                return;
            default:
                $template_name = 'category/list.php';
                break;
        }
        if ($template_name) {
            include AERP_ORDER_PATH . 'frontend/admin/' . $template_name;
            exit;
        }
    }
    if ($aerp_product_page === 'units') {
        $template_name = '';
        switch ($action_from_get) {
            case 'add':
                $template_name = 'unit/form.php';
                break;
            case 'edit':
                $template_name = 'unit/form.php';
                break;
            case 'delete':
                AERP_Unit_Manager::handle_single_delete();
                return;
            default:
                $template_name = 'unit/list.php';
                break;
        }
        if ($template_name) {
            include AERP_ORDER_PATH . 'frontend/admin/' . $template_name;
            exit;
        }
    }
    $aerp_order_status_page = get_query_var('aerp_order_status_page');
    if ($aerp_order_status_page === 'statuses') {
        $template_name = '';
        switch ($action_from_get) {
            case 'add':
                $template_name = 'order-status/form.php';
                break;
            case 'edit':
                $template_name = 'order-status/form.php';
                break;
            case 'delete':
                AERP_Order_Status_Manager::handle_single_delete();
                return;
            default:
                $template_name = 'order-status/list.php';
                break;
        }
        if ($template_name) {
            include AERP_ORDER_PATH . 'frontend/admin/' . $template_name;
            exit;
        }
    }
    $aerp_inventory_page = get_query_var('aerp_inventory_page');
    if ($aerp_inventory_page === 'stocktake') {
        include AERP_ORDER_PATH . 'frontend/admin/inventory/stocktake-form.php';
        exit;
    }
    $aerp_warehouse_page = get_query_var('aerp_warehouse_page');
    if ($aerp_warehouse_page === 'warehouses') {
        switch ($action_from_get) {
            case 'add':
                $template_name = 'warehouse/form.php';
                break;
            case 'edit':
                $template_name = 'warehouse/form.php';
                break;
            case 'delete':
                AERP_Warehouse_Manager::handle_single_delete();
                return;
            case 'stock':
                $template_name = 'warehouse/stock-list.php';
                break;
            default:
                $template_name = 'warehouse/list.php';
                break;
        }
        if ($template_name) {
            include AERP_ORDER_PATH . 'frontend/admin/' . $template_name;
            exit;
        }
    }
    $aerp_inventory_transfer_page = get_query_var('aerp_inventory_transfer_page');
    if ($aerp_inventory_transfer_page === 'transfers') {
        switch ($action_from_get) {
            case 'add':
                $template_name = 'warehouse/transfer-form.php';
                break;
            default:
                $template_name = 'warehouse/list-transfer.php';
                break;
        }
        if ($template_name) {
            include AERP_ORDER_PATH . 'frontend/admin/' . $template_name;
            exit;
        }
        exit;
    }
    $aerp_supplier_page = get_query_var('aerp_supplier_page');
    if ($aerp_supplier_page === 'suppliers') {
        $template_name = '';
        switch ($action_from_get) {
            case 'add':
                $template_name = 'supplier/form.php';
                break;
            case 'edit':
                $template_name = 'supplier/form.php';
                break;
            case 'delete':
                AERP_Supplier_Manager::handle_single_delete();
                return;
            default:
                $template_name = 'supplier/list.php';
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
