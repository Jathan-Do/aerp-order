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
        'customer_source' => sanitize_text_field($_POST['customer_source'] ?? ''),
        'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
        'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
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
add_action('wp_ajax_aerp_order_search_products', function () {
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
add_action('wp_ajax_aerp_order_search_all_products', function () {
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

add_action('wp_ajax_aerp_order_search_customers', function () {
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

add_action('wp_ajax_aerp_get_product_stock', function () {
    global $wpdb;

    $product_id   = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : (isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0);

    $qty = 0;

    if ($product_id && $warehouse_id) {
        $qty = $wpdb->get_var($wpdb->prepare(
            "SELECT quantity FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
            $product_id,
            $warehouse_id
        ));
        if ($qty === null) $qty = 0;
    }

    wp_send_json_success(['quantity' => intval($qty)]);
});

add_action('wp_ajax_nopriv_aerp_get_product_stock', function () {
    global $wpdb;

    $product_id   = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : (isset($_POST['warehouse_id']) ? intval($_POST['warehouse_id']) : 0);

    $qty = 0;

    if ($product_id && $warehouse_id) {
        $qty = $wpdb->get_var($wpdb->prepare(
            "SELECT quantity FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
            $product_id,
            $warehouse_id
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

add_action('wp_ajax_aerp_order_search_warehouses', function () {
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
add_action('wp_ajax_aerp_order_search_warehouses_by_user', function () {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $user_id = get_current_user_id();
    global $wpdb;
    $employee_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
        $user_id
    ));
    $warehouses = function_exists('aerp_get_warehouses_by_user_select2') ? aerp_get_warehouses_by_user_select2($q, $employee_id) : [];
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
add_action('wp_ajax_aerp_order_search_suppliers', function () {
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

add_action('wp_ajax_aerp_order_search_products_in_warehouse_in_worklocation', function () {
    global $wpdb;
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $current_user_id = get_current_user_id();
    $employee_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
        $current_user_id
    ));
    $results = [];

    // Lấy work_location_id của user hiện tại
    $work_location_id = $wpdb->get_var($wpdb->prepare(
        "SELECT work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
        $employee_id
    ));

    // 1. Lấy tất cả kho mà user hiện tại quản lý (không phụ thuộc chi nhánh)
    $user_warehouse_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d",
        $employee_id
    ));
    $user_warehouse_ids = array_map('intval', $user_warehouse_ids);

    // 2. Lấy tất cả kho thuộc cùng chi nhánh với user hiện tại
    $branch_warehouse_ids = [];
    if ($work_location_id) {
        $branch_warehouse_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_warehouses WHERE work_location_id = %d",
            $work_location_id
        ));
        $branch_warehouse_ids = array_map('intval', $branch_warehouse_ids);
    }

    // 3. Xác định danh sách kho hợp lệ
    if (!empty($user_warehouse_ids)) {
        // Nếu user có quản lý kho, lấy cả kho quản lý và kho cùng chi nhánh
        $warehouse_ids = array_unique(array_merge($user_warehouse_ids, $branch_warehouse_ids));
    } else {
        // Nếu không quản lý kho nào, chỉ lấy kho cùng chi nhánh
        $warehouse_ids = $branch_warehouse_ids;
    }

    // Nếu không có kho nào hợp lệ thì trả về rỗng
    if (empty($warehouse_ids)) {
        wp_send_json($results);
        return;
    }

    // Nếu có warehouse_id cụ thể và > 0, chỉ tìm trong kho đó nếu kho đó hợp lệ
    if ($warehouse_id > 0) {
        if (in_array($warehouse_id, $warehouse_ids)) {
            $warehouse_ids = [$warehouse_id];
        } else {
            wp_send_json($results); // Không có quyền xem kho này
            return;
        }
    }

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
add_action('wp_ajax_aerp_order_search_products_in_warehouse', function () {
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

// Hủy đơn hàng
add_action('wp_ajax_aerp_cancel_order', 'aerp_cancel_order_ajax');
function aerp_cancel_order_ajax() {
    check_ajax_referer('aerp_cancel_order_nonce', '_wpnonce');
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
    
    if (!$order_id) {
        wp_send_json_error('Thiếu ID đơn hàng.');
    }
    
    if (empty($reason)) {
        wp_send_json_error('Vui lòng nhập lý do hủy đơn.');
    }
    
    $result = AERP_Frontend_Order_Manager::cancel_order($order_id, $reason);
    
    if ($result) {
        wp_send_json_success('Đã hủy đơn hàng thành công.');
    } else {
        wp_send_json_error('Không thể hủy đơn hàng.');
    }
}

add_action('wp_ajax_aerp_device_filter_devices', 'aerp_device_filter_devices_callback');
add_action('wp_ajax_nopriv_aerp_device_filter_devices', 'aerp_device_filter_devices_callback');
function aerp_device_filter_devices_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
        'partner_id' => intval($_POST['partner_id'] ?? 0),
    ];
    $table = new AERP_Device_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}