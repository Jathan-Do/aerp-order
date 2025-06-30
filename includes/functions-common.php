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

if (!function_exists('aerp_get_products')) {
    function aerp_get_products($search = '')
    {
        global $wpdb;
        $sql = "SELECT p.*, u.name as unit_name FROM {$wpdb->prefix}aerp_products p LEFT JOIN {$wpdb->prefix}aerp_units u ON p.unit_id = u.id";
        if ($search) {
            $sql .= $wpdb->prepare(" WHERE name LIKE %s OR sku LIKE %s", '%' . $wpdb->esc_like($search) . '%', '%' . $wpdb->esc_like($search) . '%');
        }
        $sql .= " ORDER BY name ASC";
        return $wpdb->get_results($sql);
    }
}

if (!function_exists('aerp_get_product')) {
    function aerp_get_product($product_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_products WHERE id = %d", $product_id));
    }
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