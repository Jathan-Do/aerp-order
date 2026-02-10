<?php

// ============================
// COMMON FUNCTIONS FOR ORDER MODULE
// ============================

/**
 * Lấy danh sách đơn hàng
 */
function aerp_get_orders()
{
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aerp_order_orders ORDER BY created_at DESC");
}

/**
 * Lấy thông tin đơn hàng theo ID
 */
function aerp_get_order($order_id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aerp_order_orders WHERE id = %d",
        $order_id
    ));
}

/**
 * Lấy danh sách sản phẩm trong đơn
 */
function aerp_get_order_items($order_id)
{
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aerp_order_items WHERE order_id = %d",
        $order_id
    ));
}

/**
 * Lấy lịch sử trạng thái đơn hàng
 */
function aerp_get_order_status_logs($order_id)
{
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aerp_order_status_logs WHERE order_id = %d ORDER BY changed_at DESC",
        $order_id
    ));
}

/**
 * Lấy file đính kèm của đơn hàng
 */
function aerp_get_order_attachments($order_id)
{
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}aerp_order_attachments WHERE order_id = %d ORDER BY uploaded_at DESC",
        $order_id
    ));
}

/**
 * Lấy danh sách nhân viên đã từng được gán cho đơn hàng (dùng cho filter)
 */
function aerp_get_order_assigned_employees()
{
    global $wpdb;
    $user_ids = $wpdb->get_col("SELECT DISTINCT employee_id FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IS NOT NULL AND employee_id != ''");
    if (empty($user_ids)) return [];
    $employees = [];
    foreach ($user_ids as $uid) {
        $employees[] = (object)[
            'user_id' => $uid,
            'full_name' => aerp_get_customer_assigned_name($uid) // hoặc hàm lấy tên nhân viên từ HRM
        ];
    }
    return $employees;
}

if (!function_exists('aerp_get_products_select2')) {
    function aerp_get_products_select2($q = '')
    {
        global $wpdb;
        $where = '';
        if ($q !== '') {
            $q_like = '%' . $wpdb->esc_like($q) . '%';
            $where = $wpdb->prepare(" AND (p.name LIKE %s OR p.sku LIKE %s )", $q_like, $q_like);
        }
        return $wpdb->get_results(
            "SELECT p.*, u.name AS unit_name
         FROM {$wpdb->prefix}aerp_products p
            INNER JOIN {$wpdb->prefix}aerp_product_stocks s ON p.id = s.product_id
            LEFT JOIN {$wpdb->prefix}aerp_units u ON p.unit_id = u.id
            WHERE 1=1 $where
            GROUP BY p.id
            ORDER BY p.name ASC"
        );
    }
}
if (!function_exists('aerp_get_all_products_select2')) {
    function aerp_get_all_products_select2($q = '')
    {
        global $wpdb;
        $where = '';
        if ($q !== '') {
            $q_like = '%' . $wpdb->esc_like($q) . '%';
            $where = $wpdb->prepare(" AND (p.name LIKE %s OR p.sku LIKE %s )", $q_like, $q_like);
        }
        return $wpdb->get_results(
            "SELECT p.*, u.name AS unit_name
             FROM {$wpdb->prefix}aerp_products p
             LEFT JOIN {$wpdb->prefix}aerp_units u ON p.unit_id = u.id
             WHERE 1=1 $where
             ORDER BY p.name ASC
             LIMIT 30"
        );
    }
}

if (!function_exists('aerp_get_product')) {
    function aerp_get_product($product_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_products WHERE id = %d", $product_id));
    }
}

function aerp_get_stock_qty($product_id, $warehouse_id)
{
    global $wpdb;

    $product_id = absint($product_id);
    $warehouse_id = absint($warehouse_id);

    if (!$product_id || !$warehouse_id) return 0;

    $qty = $wpdb->get_var($wpdb->prepare(
        "SELECT quantity FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
        $product_id,
        $warehouse_id
    ));

    return ($qty !== null) ? intval($qty) : 0;
}

/**
 * Lấy danh sách tình trạng đơn hàng
 */
function aerp_get_status_order()
{
    global $wpdb;
    return $wpdb->get_results("SELECT status FROM {$wpdb->prefix}aerp_order_order");
}
/**
 * Lấy danh sách trạng thái đơn hàng
 */
function aerp_get_order_statuses()
{
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aerp_order_statuses ORDER BY name ASC");
}
function aerp_get_order_status($status_id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_order_statuses WHERE id = %d", $status_id));
}

function aerp_get_warehouses()
{
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aerp_warehouses ORDER BY name ASC");
}
function aerp_get_warehouses_by_user($user_id)
{
    global $wpdb;
    $table = $wpdb->prefix . 'aerp_warehouses';
    $manager_table = $wpdb->prefix . 'aerp_warehouse_managers';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT w.* FROM $table w
                 INNER JOIN $manager_table m ON w.id = m.warehouse_id
                 WHERE m.user_id = %d
                 ORDER BY w.name ASC",
        $user_id
    ));
}
function aerp_get_warehouse($id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_warehouses WHERE id = %d", $id));
}

function aerp_get_product_stock($product_id, $warehouse_id)
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT quantity FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
        $product_id,
        $warehouse_id
    ));
}

function aerp_get_supplier($id)
{
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_suppliers WHERE id = %d", $id));
}
function aerp_get_suppliers()
{
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aerp_suppliers ORDER BY name ASC");
}
if (!function_exists('aerp_get_warehouses_select2')) {
    function aerp_get_warehouses_select2($q = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_warehouses';
        $sql = "SELECT id, name FROM $table";
        if ($q) {
            $sql .= $wpdb->prepare(" WHERE name LIKE %s", '%' . $wpdb->esc_like($q) . '%');
        }
        $sql .= " ORDER BY name ASC LIMIT 30";
        return $wpdb->get_results($sql);
    }
}
if (!function_exists('aerp_get_warehouses_by_user_select2')) {
    function aerp_get_warehouses_by_user_select2($q = '', $user_id = null)
    {
        global $wpdb;
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Nếu là admin thì lấy tất cả kho
        if (function_exists('aerp_user_has_role') && aerp_user_has_role($user_id, 'admin')) {
            $table = $wpdb->prefix . 'aerp_warehouses';
            $sql = "SELECT id, name FROM $table";
            $params = [];
            if ($q) {
                $sql .= " WHERE name LIKE %s";
                $params[] = '%' . $wpdb->esc_like($q) . '%';
            }
            $sql .= " ORDER BY name ASC LIMIT 30";
            return $wpdb->get_results($params ? $wpdb->prepare($sql, ...$params) : $sql);
        }
        
        // Lấy employee_id từ user_id
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
        
        if (!$employee_id) {
            return [];
        }
        
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
        
        // 3. Gộp tất cả warehouse IDs
        $all_warehouse_ids = array_unique(array_merge($user_warehouse_ids, $branch_warehouse_ids));
        
        if (empty($all_warehouse_ids)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($all_warehouse_ids), '%d'));
        $table = $wpdb->prefix . 'aerp_warehouses';
        $sql = "SELECT id, name FROM $table WHERE id IN ($placeholders)";
        $params = $all_warehouse_ids;
        
        if ($q) {
            $sql .= " AND name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($q) . '%';
        }
        $sql .= " ORDER BY name ASC LIMIT 30";
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
}
if (!function_exists('aerp_get_suppliers_select2')) {
    function aerp_get_suppliers_select2($q = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_suppliers';
        $sql = "SELECT id, name FROM $table";
        if ($q) {
            $sql .= $wpdb->prepare(" WHERE name LIKE %s", '%' . $wpdb->esc_like($q) . '%');
        }
        $sql .= " ORDER BY name ASC LIMIT 30";
        return $wpdb->get_results($sql);
    }
}

if (!function_exists('aerp_get_products_in_warehouse_select2')) {
    function aerp_get_products_in_warehouse_select2($warehouse_id, $q = '')
    {
        global $wpdb;
        $where = '';
        $params = [$warehouse_id];
        if ($q !== '') {
            $q_like = '%' . $wpdb->esc_like($q) . '%';
            $where = " AND (p.name LIKE %s OR p.sku LIKE %s)";
            $params[] = $q_like;
            $params[] = $q_like;
        }
        $sql = "SELECT p.*, u.name AS unit_name
                FROM {$wpdb->prefix}aerp_product_stocks s
                INNER JOIN {$wpdb->prefix}aerp_products p ON s.product_id = p.id
                LEFT JOIN {$wpdb->prefix}aerp_units u ON p.unit_id = u.id
                WHERE s.warehouse_id = %d $where
                GROUP BY p.id
                ORDER BY p.name ASC
                LIMIT 30";
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
}
if (!function_exists('aerp_get_order_code_by_id')) {
    /**
     * Lấy mã đơn hàng dựa vào id của đơn hàng
     *
     * @param int $order_id
     * @return string|null
     */
    function aerp_get_order_code_by_id($order_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_orders';
        $order = $wpdb->get_row($wpdb->prepare("SELECT order_code FROM $table WHERE id = %d", $order_id));
        return $order ? $order->order_code : '--';
    }
}

if (!function_exists('aerp_get_device_name_by_id')) {
    /**
     * Lấy tên thiết bị dựa vào mã thiết bị (id)
     *
     * @param int $device_id
     * @return string|null
     */
    function aerp_get_device_name_by_id($device_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_devices';
        $device = $wpdb->get_row($wpdb->prepare("SELECT device_name FROM $table WHERE id = %d", $device_id));
        return $device ? $device->device_name : '--';
    }
}
if (!function_exists('aerp_get_devices_select2')) {
    /**
     * Lấy tên thiết bị dựa vào mã thiết bị (id)
     *
     * @param int $device_id
     * @return string|null
     */
    function aerp_get_devices_select2($q = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_devices';
        $sql = "SELECT id, device_name FROM $table";
        if ($q) {
            $sql .= $wpdb->prepare(" WHERE device_name LIKE %s", '%' . $wpdb->esc_like($q) . '%');
        }
        $sql .= " ORDER BY device_name ASC LIMIT 30";
        return $wpdb->get_results($sql);
    }
}

