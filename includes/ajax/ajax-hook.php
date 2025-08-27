<?php

add_action('wp_ajax_aerp_order_filter_orders', 'aerp_order_filter_orders_callback');
add_action('wp_ajax_nopriv_aerp_order_filter_orders', 'aerp_order_filter_orders_callback');
function aerp_order_filter_orders_callback()
{
    $filters = [
        'status_id' => absint($_POST['status_id'] ?? 0),
        'status' => sanitize_text_field($_POST['status'] ?? ''),
        'employee_id' => intval($_POST['employee_id'] ?? 0),
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        'order_type' => sanitize_text_field($_POST['order_type'] ?? ''),
        'customer_source_id' => sanitize_text_field($_POST['customer_source_id'] ?? ''),
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
        // if (!$q && ++$count >= 20) break; // chỉ trả về 20 sản phẩm đầu nếu không search
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
        // if (!$q && ++$count >= 30) break;
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
        // if (!$q && ++$count >= 20) break;
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

    global $wpdb;

    // Chuẩn hóa: chấp nhận cả employee_id hoặc user_id
    $raw = trim((string)$filters['manager_user_id']);
    if ($raw === '') {
        $user_id = get_current_user_id();
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
    } else {
        $maybe_id = intval($raw);
        // Nếu $maybe_id trùng employee_id thì dùng luôn
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
            $maybe_id
        ));
        if (!$employee_id) {
            // Không phải employee_id -> coi như user_id và convert
            $employee_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $maybe_id
            ));
        }
    }

    $filters['manager_user_id'] = $employee_id ? intval($employee_id) : 0;

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
        // if (!$q && ++$count >= 20) break;
    }
    wp_send_json($results);
});
add_action('wp_ajax_aerp_order_search_warehouses_by_user', function () {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
    $user_id = get_current_user_id();
    
    // Nếu có warehouse_id cụ thể, tìm theo ID đó
    if ($warehouse_id > 0) {
        global $wpdb;
        $warehouse = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}aerp_warehouses WHERE id = %d",
            $warehouse_id
        ));
        
        if ($warehouse) {
            wp_send_json([[
                'id' => $warehouse->id,
                'text' => $warehouse->name,
            ]]);
        } else {
            wp_send_json([]);
        }
        return;
    }
    
    // Sử dụng function có sẵn thay vì viết lại logic
    $warehouses = function_exists('aerp_get_warehouses_by_user_select2') ? aerp_get_warehouses_by_user_select2($q, $user_id) : [];
    
    $results = [];
    foreach ($warehouses as $warehouse) {
        $results[] = [
            'id' => $warehouse->id,
            'text' => $warehouse->name,
        ];
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
        // if (!$q && ++$count >= 20) break;
    }
    wp_send_json($results);
});

add_action('wp_ajax_aerp_order_search_implementation_templates', function () {
    global $wpdb;
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    
    $sql = "SELECT id, name, content FROM {$wpdb->prefix}aerp_implementation_templates WHERE is_active = 1";
    $params = [];
    
    if ($q) {
        $sql .= " AND (name LIKE %s OR content LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($q) . '%';
        $params[] = '%' . $wpdb->esc_like($q) . '%';
    }
    
    $sql .= " ORDER BY name ASC LIMIT 20";
    
    $templates = $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql);
    
    $results = [];
    foreach ($templates as $template) {
        $results[] = [
            'id' => $template->id,
            'text' => $template->name,
            'content' => $template->content,
        ];
    }
    wp_send_json($results);
});

add_action('wp_ajax_aerp_order_search_products_in_warehouse_in_worklocation', function () {
    global $wpdb;
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

    $current_user_id = get_current_user_id();
    $results = [];

    // Admin: có thể xem tất cả kho
    if (function_exists('aerp_user_has_role') && aerp_user_has_role($current_user_id, 'admin')) {
        if ($warehouse_id > 0) {
            $warehouse_ids = [$warehouse_id];
        } else {
            $warehouse_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}aerp_warehouses");
        }
    } else {
        // Non-admin: union 3 nhóm kho (quản lý + cùng chi nhánh + cá nhân)
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $current_user_id
        ));

        // Nếu không có employee -> không có quyền
        if (!$employee_id) {
            wp_send_json($results);
            return;
        }

        // Lấy work_location_id của user hiện tại
        $work_location_id = $wpdb->get_var($wpdb->prepare(
            "SELECT work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
            $employee_id
        ));

        // 1) Kho được gán quản lý (có thể khác chi nhánh)
        $managed_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d",
            $employee_id
        ));
        $managed_ids = array_map('intval', (array)$managed_ids);

        // 2) Kho cùng chi nhánh
        $branch_ids = [];
        if ($work_location_id) {
            $branch_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_warehouses WHERE work_location_id = %d",
                $work_location_id
            ));
            $branch_ids = array_map('intval', (array)$branch_ids);
        }

        // 3) Kho cá nhân
        $personal_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_warehouses WHERE warehouse_type = 'personal' AND owner_user_id = %d",
            $employee_id
        ));
        $personal_ids = array_map('intval', (array)$personal_ids);

        // Hợp nhất
        $warehouse_ids = array_values(array_unique(array_merge($managed_ids, $branch_ids, $personal_ids)));

        // Nếu chỉ định kho, chỉ giữ nếu hợp lệ
        if ($warehouse_id > 0) {
            if (in_array($warehouse_id, $warehouse_ids, true)) {
                $warehouse_ids = [$warehouse_id];
            } else {
                wp_send_json($results); // Không có quyền xem kho này
                return;
            }
        }

        if (empty($warehouse_ids)) {
            wp_send_json($results);
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

// Select2: tìm kiếm thiết bị đã nhận theo đơn hàng (để trả thiết bị)
add_action('wp_ajax_aerp_order_search_received_devices', function () {
    global $wpdb;
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $device_id = isset($_GET['device_id']) ? absint($_GET['device_id']) : 0;
    $results = [];

    $current_user_id = get_current_user_id();

    // Base: chỉ lấy thiết bị còn đang nhận
    $sql = "SELECT id, device_name, serial_number, status FROM {$wpdb->prefix}aerp_order_devices WHERE device_status = %s";
    $params = ['received'];

    // Quyền xem theo đơn hàng liên quan
    if (!(function_exists('aerp_user_has_role') && aerp_user_has_role($current_user_id, 'admin'))) {
        $current_user_employee = $wpdb->get_row($wpdb->prepare(
            "SELECT id, work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $current_user_id
        ));
        $employee_id = $current_user_employee ? (int)$current_user_employee->id : 0;

        if ($current_user_employee) {
            if (function_exists('aerp_user_has_permission') && aerp_user_has_permission($current_user_id, 'order_view_full')) {
                if ($current_user_employee->work_location_id) {
                    $branch_employee_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE work_location_id = %d",
                        $current_user_employee->work_location_id
                    ));
                    if (!empty($branch_employee_ids)) {
                        $placeholders = implode(',', array_fill(0, count($branch_employee_ids), '%d'));
                        $sql .= " AND order_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IN ($placeholders) OR created_by = %d)";
                        $params = array_merge($params, array_map('intval', $branch_employee_ids), [$employee_id]);
                    } else {
                        $sql .= " AND order_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_orders WHERE created_by = %d)";
                        $params[] = $employee_id;
                    }
                } else {
                    $sql .= " AND order_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_orders WHERE created_by = %d)";
                    $params[] = $employee_id;
                }
            } else {
                $sql .= " AND order_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id = %d OR created_by = %d)";
                $params[] = $employee_id; // employee_id so với ID nhân sự
                $params[] = $employee_id; // created_by so với ID user
            }
        } else {
            $sql .= " AND order_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_orders WHERE created_by = %d)";
            $params[] = $employee_id;
        }
    }

    // Tìm theo id cụ thể (preselect)
    if ($device_id > 0) {
        $sql .= " AND id = %d";
        $params[] = $device_id;
    }
    // Tìm kiếm theo tên/serial
    if ($q !== '') {
        $sql .= " AND (device_name LIKE %s OR serial_number LIKE %s)";
        $params[] = '%' . $wpdb->esc_like($q) . '%';
        $params[] = '%' . $wpdb->esc_like($q) . '%';
    }

    $sql .= " ORDER BY id DESC LIMIT 30";
    $devices = $wpdb->get_results($wpdb->prepare($sql, ...$params));

    foreach ($devices as $d) {
        $label = $d->device_name;
        if (!empty($d->serial_number)) {
            $label .= ' (' . $d->serial_number . ')';
        }
        if (!empty($d->status)) {
            $label .= ' - ' . $d->status;
        }
        $results[] = [
            'id' => $d->id,
            'text' => $label,
        ];
    }
    wp_send_json($results);
});

add_action('wp_ajax_aerp_order_search_products_in_warehouse', function () {
    global $wpdb;
    $warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $user_id = get_current_user_id();
        $current_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
    $results = [];

    // Xác định danh sách kho được phép
    if (function_exists('aerp_user_has_role') && aerp_user_has_role($user_id, 'admin')) {
        // Admin: thấy tất cả hoặc 1 kho cụ thể nếu chọn
        if ($warehouse_id > 0) {
            $warehouse_ids = [$warehouse_id];
        } else {
            $warehouse_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}aerp_warehouses");
        }
    } else {
        // Non-admin: dùng helper để lấy danh sách kho được phép
        $user_warehouses = function_exists('aerp_get_warehouses_by_user')
            ? aerp_get_warehouses_by_user($current_user_id)
            : [];
        $allowed_ids = array_map('intval', array_column((array)$user_warehouses, 'id'));

        if (empty($allowed_ids)) {
            wp_send_json($results);
            return;
        }

        if ($warehouse_id > 0) {
            if (in_array($warehouse_id, $allowed_ids, true)) {
                $warehouse_ids = [$warehouse_id];
            } else {
                wp_send_json($results);
                return;
            }
        } else {
            $warehouse_ids = $allowed_ids;
        }
    }

    if (empty($warehouse_ids)) {
        wp_send_json($results);
        return;
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

// Từ chối đơn hàng
add_action('wp_ajax_aerp_reject_order', 'aerp_reject_order_ajax');
function aerp_reject_order_ajax() {
    check_ajax_referer('aerp_reject_order_nonce', '_wpnonce');
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : '';
    
    if (!$order_id) {
        wp_send_json_error('Thiếu ID đơn hàng.');
    }
    
    $result = AERP_Frontend_Order_Manager::reject_order($order_id, $reason);
    
    if ($result) {
        wp_send_json_success('Đã từ chối đơn hàng thành công.');
    } else {
        wp_send_json_error('Không thể từ chối đơn hàng.');
    }
}

// Hoàn thành đơn hàng
add_action('wp_ajax_aerp_complete_order', 'aerp_complete_order_ajax');
function aerp_complete_order_ajax() {
    check_ajax_referer('aerp_complete_order_nonce', '_wpnonce');
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    
    if (!$order_id) {
        wp_send_json_error('Thiếu ID đơn hàng.');
    }
    
    $result = AERP_Frontend_Order_Manager::complete_order($order_id);
    
    if ($result) {
        wp_send_json_success('Đã hoàn thành đơn hàng thành công.');
    } else {
        wp_send_json_error('Không thể hoàn thành đơn hàng.');
    }
}

// Thu tiền đơn hàng
add_action('wp_ajax_aerp_mark_paid', 'aerp_mark_paid_ajax');
function aerp_mark_paid_ajax() {
    check_ajax_referer('aerp_mark_paid_nonce', '_wpnonce');
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    
    if (!$order_id) {
        wp_send_json_error('Thiếu ID đơn hàng.');
    }
    
    $result = AERP_Frontend_Order_Manager::mark_order_paid($order_id);
    
    if ($result) {
        wp_send_json_success('Đã thu tiền đơn hàng thành công.');
    } else {
        wp_send_json_error('Không thể thu tiền đơn hàng.');
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
        'progress_id' => intval($_POST['progress_id'] ?? 0),
        'device_status' => sanitize_text_field($_POST['device_status'] ?? ''),
    ];
    $table = new AERP_Device_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_aerp_device_return_filter_device_returns', 'aerp_device_return_filter_device_returns_callback');
add_action('wp_ajax_nopriv_aerp_device_return_filter_device_returns', 'aerp_device_return_filter_device_returns_callback');
function aerp_device_return_filter_device_returns_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
        'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
        'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
        'status' => sanitize_text_field($_POST['status'] ?? ''),
    ];
    $table = new AERP_Device_Return_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_device_progress_filter', 'aerp_device_progress_filter_callback');
add_action('wp_ajax_nopriv_aerp_device_progress_filter', 'aerp_device_progress_filter_callback');
function aerp_device_progress_filter_callback()
{
    $raw_is_active = isset($_POST['is_active']) ? $_POST['is_active'] : '';
    $is_active = ($raw_is_active === '' || $raw_is_active === null) ? '' : intval($raw_is_active);
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
        'is_active' => $is_active,
        'color' => sanitize_text_field($_POST['color'] ?? ''),
    ];
    $table = new AERP_Device_Progress_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_aerp_implementation_template_filter', 'aerp_implementation_template_filter_callback');
add_action('wp_ajax_nopriv_aerp_implementation_template_filter', 'aerp_implementation_template_filter_callback');
function aerp_implementation_template_filter_callback()
{
    $raw_is_active = isset($_POST['is_active']) ? $_POST['is_active'] : '';
    $is_active = ($raw_is_active === '' || $raw_is_active === null) ? '' : intval($raw_is_active);

    $filters = [
        'is_active' => $is_active,
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Implementation_Template_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
