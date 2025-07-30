<?php

add_action('wp_ajax_aerp_order_filter_orders', 'aerp_order_filter_orders_callback');
add_action('wp_ajax_nopriv_aerp_order_filter_orders', 'aerp_order_filter_orders_callback');
function aerp_order_filter_orders_callback()
{
    $filters = [
        'status_id' => absint($_POST['status_id'] ?? 0),
        'employee_id' => intval($_POST['employee_id'] ?? 0),
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        'order_type' => sanitize_text_field($_POST['order_type'] ?? ''),
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Frontend_Order_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

// AJAX hook for deleting order attachments
add_action('wp_ajax_aerp_delete_order_attachment', ['AERP_Frontend_Order_Manager', 'handle_delete_attachment_ajax']);

add_action('wp_ajax_aerp_order_filter_status_logs', 'aerp_order_filter_status_logs_callback');
add_action('wp_ajax_nopriv_aerp_order_filter_status_logs', 'aerp_order_filter_status_logs_callback');
function aerp_order_filter_status_logs_callback()
{
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error('Missing order ID');
    }

    $filters = [
        'order_id' => $order_id,
        'old_status_id' => absint($_POST['old_status_id'] ?? 0),
        'new_status_id' => absint($_POST['new_status_id'] ?? 0),
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];

    $table = new AERP_Frontend_Order_Status_Log_Table($order_id);
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_product_filter_products', 'aerp_product_filter_products_callback');
add_action('wp_ajax_nopriv_aerp_product_filter_products', 'aerp_product_filter_products_callback');
function aerp_product_filter_products_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];

    $table = new AERP_Product_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_aerp_order_search_products', function() {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $products = function_exists('aerp_get_products_select2') ? aerp_get_products_select2($q) : [];
    $results = [];
    $count = 0;
    foreach ($products as $product) {
        $results[] = [
            'id' => $product->id,
            'text' => $product->name . (!empty($product->sku) ? ' (' . $product->sku . ')' : ''),
            'price' => $product->price,
            'unit_name' => $product->unit_name ?? '',
        ];
        if (!$q && ++$count >= 20) break; // chỉ trả về 20 sản phẩm đầu nếu không search
    }
    wp_send_json($results);
});
add_action('wp_ajax_aerp_order_search_all_products', function() {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $products = function_exists('aerp_get_all_products_select2') ? aerp_get_all_products_select2($q) : [];
    $results = [];
    $count = 0;
    foreach ($products as $product) {
        $results[] = [
            'id' => $product->id,
            'text' => $product->name . (!empty($product->sku) ? ' (' . $product->sku . ')' : ''),
            'price' => $product->price,
            'unit_name' => $product->unit_name ?? '',
        ];
        if (!$q && ++$count >= 30) break;
    }
    wp_send_json($results);
});
add_action('wp_ajax_aerp_category_filter_categories', 'aerp_category_filter_categories_callback');
add_action('wp_ajax_nopriv_aerp_category_filter_categories', 'aerp_category_filter_categories_callback');
function aerp_category_filter_categories_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];

    $table = new AERP_Category_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_aerp_unit_filter_units', 'aerp_unit_filter_units_callback');
add_action('wp_ajax_nopriv_aerp_unit_filter_units', 'aerp_unit_filter_units_callback');
function aerp_unit_filter_units_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];

    $table = new AERP_Unit_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_order_status_filter_statuses', 'aerp_order_status_filter_statuses_callback');
add_action('wp_ajax_nopriv_aerp_order_status_filter_statuses', 'aerp_order_status_filter_statuses_callback');
function aerp_order_status_filter_statuses_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'color' => sanitize_text_field($_POST['color'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Order_Status_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_order_search_customers', function() {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $customers = function_exists('aerp_get_customers_select2') ? aerp_get_customers_select2($q) : [];
    $results = [];
    $count = 0;
    foreach ($customers as $customer) {
        $results[] = [
            'id' => $customer->id,
            'text' => $customer->full_name . (!empty($customer->customer_code) ? ' (' . $customer->customer_code . ')' : ''),
        ];
        if (!$q && ++$count >= 20) break;
    }
    wp_send_json($results);
});


add_action('wp_ajax_aerp_inventory_log_filter_inventory_logs', 'aerp_inventory_log_filter_inventory_logs_callback');
add_action('wp_ajax_nopriv_aerp_inventory_log_filter_inventory_logs', 'aerp_inventory_log_filter_inventory_logs_callback');
function aerp_inventory_log_filter_inventory_logs_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
        'status' => sanitize_text_field($_POST['status'] ?? ''),
        'type' => sanitize_text_field($_POST['type'] ?? ''),
        'warehouse_id' => intval($_POST['warehouse_id'] ?? 0),
        'supplier_id' => intval($_POST['supplier_id'] ?? 0),
        'manager_user_id' => sanitize_text_field($_POST['manager_user_id'] ?? ''),

    ];
    $table = new AERP_Inventory_Log_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_get_product_stock', function() {
    global $wpdb;

    $product_id   = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : (isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0);

    $qty = 0;

    if ($product_id && $warehouse_id) {
        $qty = $wpdb->get_var($wpdb->prepare(
            "SELECT quantity FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
            $product_id, $warehouse_id
        ));
        if ($qty === null) $qty = 0;
    }

    wp_send_json_success(['quantity' => intval($qty)]);
});

add_action('wp_ajax_nopriv_aerp_get_product_stock', function() {
    global $wpdb;

    $product_id   = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : (isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0);

    $qty = 0;

    if ($product_id && $warehouse_id) {
        $qty = $wpdb->get_var($wpdb->prepare(
            "SELECT quantity FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
            $product_id, $warehouse_id
        ));
        if ($qty === null) $qty = 0;
    }

    wp_send_json_success(['quantity' => intval($qty)]);
});

add_action('wp_ajax_aerp_warehouse_filter_warehouses', 'aerp_warehouse_filter_warehouses_callback');
add_action('wp_ajax_nopriv_aerp_warehouse_filter_warehouses', 'aerp_warehouse_filter_warehouses_callback');
function aerp_warehouse_filter_warehouses_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
        'manager_user_id' => sanitize_text_field($_POST['manager_user_id'] ?? ''),
        
    ];
    $table = new AERP_Warehouse_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_aerp_product_stock_filter', 'aerp_product_stock_filter_callback');
add_action('wp_ajax_nopriv_aerp_product_stock_filter', 'aerp_product_stock_filter_callback');
function aerp_product_stock_filter_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
        'manager_user_id' => sanitize_text_field($_POST['manager_user_id'] ?? ''),
    ];
    $table = new AERP_Product_Stock_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_inventory_transfer_filter_inventory_transfers', 'aerp_inventory_transfer_filter_inventory_transfers_callback');
add_action('wp_ajax_nopriv_aerp_inventory_transfer_filter_inventory_transfers', 'aerp_inventory_transfer_filter_inventory_transfers_callback');
function aerp_inventory_transfer_filter_inventory_transfers_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
        'manager_user_id' => sanitize_text_field($_POST['manager_user_id'] ?? ''),
    ];
    $table = new AERP_Inventory_Transfer_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_supplier_filter_suppliers', 'aerp_supplier_filter_suppliers_callback');
add_action('wp_ajax_nopriv_aerp_supplier_filter_suppliers', 'aerp_supplier_filter_suppliers_callback');
function aerp_supplier_filter_suppliers_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Supplier_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_order_search_warehouses', function() {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $warehouses = function_exists('aerp_get_warehouses_select2') ? aerp_get_warehouses_select2($q) : [];
    $results = [];
    $count = 0;
    foreach ($warehouses as $warehouse) {
        $results[] = [
            'id' => $warehouse->id,
            'text' => $warehouse->name,
        ];
        if (!$q && ++$count >= 20) break;
    }
    wp_send_json($results);
});
add_action('wp_ajax_aerp_order_search_warehouses_by_user', function() {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $user_id = get_current_user_id();
    $warehouses = function_exists('aerp_get_warehouses_by_user_select2') ? aerp_get_warehouses_by_user_select2($q, $user_id) : [];
    $results = [];
    $count = 0;
    foreach ($warehouses as $warehouse) {
        $results[] = [
            'id' => $warehouse->id,
            'text' => $warehouse->name,
        ];
        if (!$q && ++$count >= 20) break;
    }
    wp_send_json($results);
});
add_action('wp_ajax_aerp_order_search_suppliers', function() {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $suppliers = function_exists('aerp_get_suppliers_select2') ? aerp_get_suppliers_select2($q) : [];
    $results = [];
    $count = 0;
    foreach ($suppliers as $supplier) {
        $results[] = [
            'id' => $supplier->id,
            'text' => $supplier->name,
        ];
        if (!$q && ++$count >= 20) break;
    }
    wp_send_json($results);
});

add_action('wp_ajax_aerp_order_search_products_in_warehouse', function() {
    global $wpdb;
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $current_user_id = get_current_user_id();
    $results = [];
    
    // Lấy tất cả kho mà user quản lý
    $user_warehouses = aerp_get_warehouses_by_user($current_user_id);
    $warehouse_ids = array_column($user_warehouses, 'id');
    
    if (empty($warehouse_ids)) {
        wp_send_json($results);
        return;
    }
    
    // Nếu có warehouse_id cụ thể và > 0, chỉ tìm trong kho đó
    if ($warehouse_id > 0) {
        $warehouse_ids = [$warehouse_id];
    }
    // Nếu warehouse_id = 0, tìm trong tất cả kho user quản lý
    
    $warehouse_ids_str = implode(',', array_map('intval', $warehouse_ids));
    
    $sql = "SELECT DISTINCT p.id, p.name, p.sku, p.price, u.name AS unit_name, w.name AS warehouse_name
            FROM {$wpdb->prefix}aerp_products p
            INNER JOIN {$wpdb->prefix}aerp_product_stocks s ON p.id = s.product_id
            LEFT JOIN {$wpdb->prefix}aerp_units u ON p.unit_id = u.id
            LEFT JOIN {$wpdb->prefix}aerp_warehouses w ON s.warehouse_id = w.id
            WHERE s.warehouse_id IN ($warehouse_ids_str)";
    
    $params = [];
    if ($q !== '') {
        $sql .= " AND (p.name LIKE %s OR p.sku LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($q) . '%';
        $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    
    $sql .= " ORDER BY p.name ASC LIMIT 30";
    $products = $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql);
    
    $count = 0;
    foreach ($products as $product) {
        $display_name = $product->name;
        if (!empty($product->sku)) {
            $display_name .= ' (' . $product->sku . ')';
        }
        if (!empty($product->warehouse_name)) {
            $display_name .= ' - ' . $product->warehouse_name;
        }
        
        $results[] = [
            'id' => $product->id,
            'text' => $display_name,
            'price' => $product->price,
            'unit_name' => $product->unit_name ?? '',
        ];
        if (!$q && ++$count >= 30) break;
    }
    wp_send_json($results);
});

add_action('wp_ajax_aerp_get_users_by_work_location', function() {
    global $wpdb;
    $work_location_id = isset($_GET['work_location_id']) ? intval($_GET['work_location_id']) : 0;
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $current_user_id = get_current_user_id();
    $results = [];
    
    // Lấy branch của user hiện tại
    $current_user_branch = $wpdb->get_var($wpdb->prepare(
        "SELECT work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
        $current_user_id
    ));
    
    $sql = "SELECT e.id, e.full_name, wl.name AS work_location_name 
            FROM {$wpdb->prefix}aerp_hrm_employees e
            LEFT JOIN {$wpdb->prefix}aerp_hrm_work_locations wl ON e.work_location_id = wl.id
            WHERE 1=1 AND e.status = 'active'";
    $params = [];
    
    // Filter theo branch của user hiện tại (nếu có)
    if ($current_user_branch) {
        $sql .= " AND e.work_location_id = %d";
        $params[] = $current_user_branch;
    }
    
    // Filter theo work_location_id được truyền (nếu có)
    if ($work_location_id) {
        $sql .= " AND e.work_location_id = %d";
        $params[] = $work_location_id;
    }
    
    if ($q !== '') {
        $sql .= " AND (e.full_name LIKE %s OR wl.name LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($q) . '%';
        $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    
    $sql .= " ORDER BY e.full_name ASC LIMIT 30";
    $users = $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql);
    
    foreach ($users as $user) {
        $display_name = $user->full_name;
        if (!empty($user->work_location_name)) {
            $display_name .= ' - ' . $user->work_location_name;
        }
        $results[] = [
            'id' => $user->id,
            'text' => $display_name,
        ];
    }
    wp_send_json($results);
});

add_action('wp_ajax_aerp_low_stock_filter_table', 'aerp_low_stock_filter_table_callback');
add_action('wp_ajax_nopriv_aerp_low_stock_filter_table', 'aerp_low_stock_filter_table_callback');
function aerp_low_stock_filter_table_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
        'warehouse_id' => intval($_POST['warehouse_id'] ?? 0),
        'product_id' => intval($_POST['product_id'] ?? 0),
        'threshold' => intval($_POST['threshold'] ?? get_option('aerp_low_stock_threshold', 10)),
        'manager_user_id' => get_current_user_id(),
    ];
    
    $table = new AERP_Low_Stock_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}