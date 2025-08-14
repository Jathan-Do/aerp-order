<?php

/**
 * Frontend Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}
// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

// Danh sách điều kiện, chỉ cần 1 cái đúng là qua
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}
$hrm_active = function_exists('aerp_hrm_init') || is_plugin_active('aerp-hrm/aerp-hrm.php');
$order_active = function_exists('aerp_order_init') || is_plugin_active('aerp-order/aerp-order.php');
$crm_active = function_exists('aerp_crm_init') || is_plugin_active('aerp-crm/aerp-crm.php');
$warehouse_active = $order_active; // kho nằm trong order

global $wpdb;
$month = isset($_GET['month']) ? $_GET['month'] : '';
$employee = function_exists('aerp_get_employee_by_user_id') ? aerp_get_employee_by_user_id($user_id) : null;
$work_location_id = $employee ? $employee->work_location_id : 0;
$warehouses = class_exists('AERP_Warehouse_Manager') ? AERP_Warehouse_Manager::aerp_get_warehouses_by_user($user_id) : [];
$user_warehouse_ids = array_map(function ($w) {
    return $w->id;
}, $warehouses);
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Báo cáo Đơn hàng</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="dashboard-wrapper">
    <div class="mb-3">
        <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label class="fw-bold" for="month">Tháng:</label>
                <input class="form-control w-auto" type="month" id="month" name="month" value="<?= esc_attr($month) ?>" max="<?= date('Y-m') ?>">

                <!-- Form xem báo cáo -->
                <form method="get" style="display: inline;">
                    <input type="hidden" name="month" value="<?= esc_attr($month) ?>" id="month-hidden">
                    <button type="submit" class="btn btn-primary">Xem</button>
                </form>

                <!-- Nút xem 12 tháng gần nhất -->
                <a href="?month=" class="btn btn-outline-secondary">12 tháng gần nhất</a>
            </div>
            <div>
                <!-- Nút xuất Excel -->
                <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display: inline;">
                    <?php wp_nonce_field('aerp_export_excel', 'aerp_export_nonce'); ?>
                    <input type="hidden" name="action" value="aerp_export_excel_common">
                    <input type="hidden" name="callback" value="aerp_dashboard_export">
                    <input type="hidden" name="report_month" value="<?= esc_attr($month) ?>" id="report-month-hidden">
                    <button type="submit" name="aerp_export_excel" class="btn btn-success">📥 Xuất Excel</button>
                </form>
            </div>

        </div>
    </div>
    <?php if ($order_active): ?>
        <?php
        // Báo cáo đơn hàng theo chi nhánh user hiện tại
        if (!$work_location_id) {
            echo '<div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                Bạn chưa được gán chi nhánh, không thể xem báo cáo đơn hàng.
            </div>';
        } else {
            // Lấy tháng được chọn hoặc mặc định là rỗng (xem nhiều tháng)
            $selected_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '';

            // Lấy danh sách nhân viên thuộc chi nhánh hiện tại
            $employee_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE work_location_id = %d",
                $work_location_id
            ));

            if (!empty($employee_ids)) {
                $employee_ids_sql = implode(',', array_map('intval', $employee_ids));

                // Tạo danh sách 12 tháng gần nhất
                $months_to_show = [];
                for ($i = 11; $i >= 0; $i--) {
                    $months_to_show[] = date('Y-m', strtotime("-{$i} months"));
                }

                // Thống kê tổng quan (luôn lấy tổng)
                $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IN ($employee_ids_sql)");

                // Nếu chọn tháng cụ thể
                if (!empty($selected_month)) {
                    $start_date = date('Y-m-01', strtotime($selected_month));
                    $end_date = date('Y-m-t', strtotime($selected_month));

                    // Thống kê cho tháng được chọn
                    $new_orders = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IN ($employee_ids_sql) AND order_date BETWEEN %s AND %s",
                        $start_date,
                        $end_date
                    ));
                    $total_revenue = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(total_amount) FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IN ($employee_ids_sql) AND order_date BETWEEN %s AND %s",
                        $start_date,
                        $end_date
                    ));
                    $total_cost = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(cost) FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IN ($employee_ids_sql) AND cost IS NOT NULL AND order_date BETWEEN %s AND %s",
                        $start_date,
                        $end_date
                    ));
                    $total_profit = ($total_revenue ?? 0) - ($total_cost ?? 0);

                    // Đơn hàng theo ngày trong tháng được chọn
                    $orders_by_day = $wpdb->get_results($wpdb->prepare("
                        SELECT DATE(order_date) as day, COUNT(*) as total, SUM(total_amount) as revenue
                        FROM {$wpdb->prefix}aerp_order_orders 
                        WHERE employee_id IN ($employee_ids_sql) AND order_date BETWEEN %s AND %s
                        GROUP BY day 
                        ORDER BY day ASC
                    ", $start_date, $end_date), ARRAY_A);

                    // Đơn hàng theo trạng thái trong tháng được chọn
                    $orders_by_status = $wpdb->get_results($wpdb->prepare("
                        SELECT status, COUNT(*) as count
                        FROM {$wpdb->prefix}aerp_order_orders 
                        WHERE employee_id IN ($employee_ids_sql) AND order_date BETWEEN %s AND %s
                        GROUP BY status
                    ", $start_date, $end_date), ARRAY_A);

                    // Đơn hàng theo trạng thái và ngày trong tháng được chọn (cho biểu đồ cột nhóm)
                    $orders_by_status_month = $wpdb->get_results($wpdb->prepare("
                        SELECT o.status,
                               DATE(o.order_date) as day,
                               COUNT(*) as count
                        FROM {$wpdb->prefix}aerp_order_orders o
                        WHERE o.employee_id IN ($employee_ids_sql)
                        AND o.order_date BETWEEN %s AND %s
                        GROUP BY o.status, day
                        ORDER BY o.status, day ASC
                    ", $start_date, $end_date), ARRAY_A);

                    // Đơn hàng theo nguồn khách hàng trong tháng được chọn
                    $orders_by_source = $wpdb->get_results($wpdb->prepare("
                        SELECT cs.name as source_name, cs.color as source_color, cs.id as customer_source_id, 
                               COALESCE(order_count.count, 0) as count
                        FROM {$wpdb->prefix}aerp_crm_customer_sources cs
                        LEFT JOIN (
                            SELECT o.customer_source_id, COUNT(*) as count
                            FROM {$wpdb->prefix}aerp_order_orders o
                            WHERE o.employee_id IN ($employee_ids_sql)
                            AND o.customer_source_id IS NOT NULL AND o.customer_source_id != 0
                            AND o.order_date BETWEEN %s AND %s
                            GROUP BY o.customer_source_id
                        ) order_count ON cs.id = order_count.customer_source_id
                        ORDER BY count DESC
                    ", $start_date, $end_date), ARRAY_A);

                    // Đơn hàng theo nguồn và ngày trong tháng được chọn (cho biểu đồ cột nhóm)
                    $orders_by_source_month = $wpdb->get_results($wpdb->prepare("
                        SELECT cs.name as source_name, cs.color as source_color, cs.id as customer_source_id,
                               DATE(o.order_date) as day,
                               COUNT(*) as count
                        FROM {$wpdb->prefix}aerp_crm_customer_sources cs
                        INNER JOIN {$wpdb->prefix}aerp_order_orders o ON cs.id = o.customer_source_id
                        WHERE o.employee_id IN ($employee_ids_sql)
                        AND o.customer_source_id IS NOT NULL AND o.customer_source_id != 0
                        AND o.order_date BETWEEN %s AND %s
                        GROUP BY cs.id, day
                        ORDER BY cs.name, day ASC
                    ", $start_date, $end_date), ARRAY_A);
                } else {
                    // Mặc định: hiển thị 12 tháng gần nhất
                    $current_month = date('Y-m');
                    $start_date = date('Y-m-01', strtotime($current_month));
                    $end_date = date('Y-m-t', strtotime($current_month));

                    // Thống kê cho tháng hiện tại
                    $new_orders = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IN ($employee_ids_sql) AND order_date BETWEEN %s AND %s",
                        $start_date,
                        $end_date
                    ));
                    $total_revenue = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(total_amount) FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IN ($employee_ids_sql) AND order_date BETWEEN %s AND %s",
                        $start_date,
                        $end_date
                    ));
                    $total_cost = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(cost) FROM {$wpdb->prefix}aerp_order_orders WHERE employee_id IN ($employee_ids_sql) AND cost IS NOT NULL AND order_date BETWEEN %s AND %s",
                        $start_date,
                        $end_date
                    ));
                    $total_profit = ($total_revenue ?? 0) - ($total_cost ?? 0);

                    // Đơn hàng theo từng tháng (12 tháng gần nhất) - MẶC ĐỊNH HIỂN THỊ
                    $orders_by_month_raw = $wpdb->get_results("
                        SELECT DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as total, SUM(total_amount) as revenue
                        FROM {$wpdb->prefix}aerp_order_orders 
                        WHERE employee_id IN ($employee_ids_sql) 
                        AND DATE_FORMAT(order_date, '%Y-%m') IN ('" . implode("','", $months_to_show) . "')
                        GROUP BY month 
                        ORDER BY month ASC
                    ", ARRAY_A);

                    // Tạo mảng đầy đủ 12 tháng với giá trị 0 cho tháng không có dữ liệu
                    $orders_by_month = [];
                    $month_data = array_column($orders_by_month_raw, 'total', 'month');
                    $month_revenue = array_column($orders_by_month_raw, 'revenue', 'month');

                    foreach ($months_to_show as $month) {
                        $orders_by_month[] = [
                            'month' => $month,
                            'total' => isset($month_data[$month]) ? $month_data[$month] : 0,
                            'revenue' => isset($month_revenue[$month]) ? $month_revenue[$month] : 0
                        ];
                    }

                    // Đơn hàng theo trạng thái (tổng hợp 12 tháng)
                    $orders_by_status = $wpdb->get_results("
                        SELECT status, COUNT(*) as count
                        FROM {$wpdb->prefix}aerp_order_orders 
                        WHERE employee_id IN ($employee_ids_sql) 
                        AND DATE_FORMAT(order_date, '%Y-%m') IN ('" . implode("','", $months_to_show) . "')
                        GROUP BY status
                    ", ARRAY_A);

                    // Đơn hàng theo trạng thái và tháng (cho biểu đồ cột nhóm)
                    $orders_by_status_month = $wpdb->get_results("
                        SELECT o.status,
                               DATE_FORMAT(o.order_date, '%Y-%m') as month,
                               COUNT(*) as count
                        FROM {$wpdb->prefix}aerp_order_orders o
                        WHERE o.employee_id IN ($employee_ids_sql)
                        AND DATE_FORMAT(o.order_date, '%Y-%m') IN ('" . implode("','", $months_to_show) . "')
                        GROUP BY o.status, month
                        ORDER BY o.status, month ASC
                    ", ARRAY_A);

                    // Đơn hàng theo nguồn khách hàng (tổng hợp 12 tháng)
                    $orders_by_source = $wpdb->get_results("
                        SELECT cs.name as source_name, cs.color as source_color, cs.id as customer_source_id, 
                               COALESCE(order_count.count, 0) as count
                        FROM {$wpdb->prefix}aerp_crm_customer_sources cs
                        LEFT JOIN (
                            SELECT o.customer_source_id, COUNT(*) as count
                            FROM {$wpdb->prefix}aerp_order_orders o
                            WHERE o.employee_id IN ($employee_ids_sql)
                            AND o.customer_source_id IS NOT NULL AND o.customer_source_id != 0
                            AND DATE_FORMAT(o.order_date, '%Y-%m') IN ('" . implode("','", $months_to_show) . "')
                            GROUP BY o.customer_source_id
                        ) order_count ON cs.id = order_count.customer_source_id
                        WHERE cs.id IS NOT NULL
                        ORDER BY count DESC
                    ", ARRAY_A);

                    // Đơn hàng theo nguồn và tháng (cho biểu đồ cột nhóm)
                    $orders_by_source_month = $wpdb->get_results("
                        SELECT cs.name as source_name, cs.color as source_color, cs.id as customer_source_id,
                               DATE_FORMAT(o.order_date, '%Y-%m') as month,
                               COUNT(*) as count
                        FROM {$wpdb->prefix}aerp_crm_customer_sources cs
                        INNER JOIN {$wpdb->prefix}aerp_order_orders o ON cs.id = o.customer_source_id
                        WHERE o.employee_id IN ($employee_ids_sql)
                        AND o.customer_source_id IS NOT NULL AND o.customer_source_id != 0
                        AND DATE_FORMAT(o.order_date, '%Y-%m') IN ('" . implode("','", $months_to_show) . "')
                        GROUP BY cs.id, month
                        ORDER BY cs.name, month ASC
                    ", ARRAY_A);

                    // Debug: Kiểm tra dữ liệu nguồn khách hàng
                    if (empty($orders_by_source)) {
                        // Nếu không có dữ liệu, tạo dữ liệu mẫu để hiển thị
                        $orders_by_source = $wpdb->get_results("
                            SELECT cs.name as source_name, cs.color as source_color, cs.id as customer_source_id, 0 as count
                            FROM {$wpdb->prefix}aerp_crm_customer_sources cs
                            LIMIT 5
                        ", ARRAY_A);
                    }
                }

                // Thống kê khách hàng quay lại và phân bố khách hàng
                if (!empty($employee_ids)) {
                    if (!empty($selected_month)) {
                        // Nếu chọn tháng cụ thể: tính cho tháng đó
                        $returning_customers = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(DISTINCT o.customer_id) 
                            FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                WHERE e.work_location_id = %d
                  AND o.order_date BETWEEN %s AND %s
                              AND o.customer_id IN (
                                SELECT o2.customer_id 
                                FROM {$wpdb->prefix}aerp_order_orders o2
                                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e2 ON o2.employee_id = e2.id
                                WHERE e2.work_location_id = %d
                                  AND o2.order_date BETWEEN %s AND %s
                                GROUP BY o2.customer_id 
                                HAVING COUNT(*) > 1
                            )
                        ", $work_location_id, $start_date, $end_date, $work_location_id, $start_date, $end_date));

                        $new_customers_with_orders = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(DISTINCT o.customer_id) 
                            FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                WHERE e.work_location_id = %d
                  AND o.order_date BETWEEN %s AND %s
                              AND o.customer_id IN (
                                SELECT o2.customer_id 
                                FROM {$wpdb->prefix}aerp_order_orders o2
                                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e2 ON o2.employee_id = e2.id
                                WHERE e2.work_location_id = %d
                                  AND o2.order_date BETWEEN %s AND %s
                                GROUP BY o2.customer_id 
                                HAVING COUNT(*) = 1
                            )
                        ", $work_location_id, $start_date, $end_date, $work_location_id, $start_date, $end_date));

                        // Doanh thu trung bình mỗi đơn hàng trong tháng
                        $avg_order_revenue = $wpdb->get_var($wpdb->prepare("
                            SELECT AVG(o.total_amount) 
                            FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                WHERE e.work_location_id = %d
                              AND o.total_amount > 0 AND o.order_date BETWEEN %s AND %s
                        ", $work_location_id, $start_date, $end_date));

                        // Số đơn hàng 0đ trong tháng
                        $zero_amount_orders = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) 
                            FROM {$wpdb->prefix}aerp_order_orders o
                            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                            WHERE e.work_location_id = %d
                              AND (o.total_amount = 0 OR o.total_amount IS NULL) 
                  AND o.order_date BETWEEN %s AND %s
                        ", $work_location_id, $start_date, $end_date));

                        // Số đơn hàng có lợi nhuận trong tháng
                        $profitable_orders = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) 
                            FROM {$wpdb->prefix}aerp_order_orders o
                            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                            WHERE e.work_location_id = %d
                              AND (o.total_amount - COALESCE(o.cost, 0)) > 0 
                              AND o.order_date BETWEEN %s AND %s
                        ", $work_location_id, $start_date, $end_date));
                    } else {
                        // Mặc định: tính cho 12 tháng gần nhất
                        $twelve_months_ago = date('Y-m-01', strtotime('-11 months'));
                        $current_month_end = date('Y-m-t');

                        $returning_customers = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(DISTINCT o.customer_id) 
                FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                WHERE e.work_location_id = %d
                  AND o.order_date BETWEEN %s AND %s
                              AND o.customer_id IN (
                                SELECT o2.customer_id 
                                FROM {$wpdb->prefix}aerp_order_orders o2
                                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e2 ON o2.employee_id = e2.id
                                WHERE e2.work_location_id = %d
                                  AND o2.order_date BETWEEN %s AND %s
                                GROUP BY o2.customer_id 
                                HAVING COUNT(*) > 1
                            )
                        ", $work_location_id, $twelve_months_ago, $current_month_end, $work_location_id, $twelve_months_ago, $current_month_end));

                        $new_customers_with_orders = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(DISTINCT o.customer_id) 
                FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                WHERE e.work_location_id = %d
                  AND o.order_date BETWEEN %s AND %s
                              AND o.customer_id IN (
                                SELECT o2.customer_id 
                                FROM {$wpdb->prefix}aerp_order_orders o2
                                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e2 ON o2.employee_id = e2.id
                                WHERE e2.work_location_id = %d
                                  AND o2.order_date BETWEEN %s AND %s
                                GROUP BY o2.customer_id 
                                HAVING COUNT(*) = 1
                            )
                        ", $work_location_id, $twelve_months_ago, $current_month_end, $work_location_id, $twelve_months_ago, $current_month_end));

                        // Doanh thu trung bình mỗi đơn hàng trong 12 tháng
                        $avg_order_revenue = $wpdb->get_var($wpdb->prepare("
                            SELECT AVG(o.total_amount) 
                            FROM {$wpdb->prefix}aerp_order_orders o
                            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                            WHERE e.work_location_id = %d
                              AND o.total_amount > 0 AND o.order_date BETWEEN %s AND %s
                        ", $work_location_id, $twelve_months_ago, $current_month_end));

                        // Số đơn hàng 0đ trong 12 tháng
                        $zero_amount_orders = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) 
                            FROM {$wpdb->prefix}aerp_order_orders o
                            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                            WHERE e.work_location_id = %d
                              AND (o.total_amount = 0 OR o.total_amount IS NULL) 
                              AND o.order_date BETWEEN %s AND %s
                        ", $work_location_id, $twelve_months_ago, $current_month_end));

                        // Số đơn hàng có lợi nhuận trong 12 tháng
                        $profitable_orders = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) 
                            FROM {$wpdb->prefix}aerp_order_orders o
                            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                            WHERE e.work_location_id = %d
                              AND (o.total_amount - COALESCE(o.cost, 0)) > 0 
                              AND o.order_date BETWEEN %s AND %s
                        ", $work_location_id, $twelve_months_ago, $current_month_end));
                    }
                } else {
                    $returning_customers = $new_customers_with_orders = $avg_order_revenue = $zero_amount_orders = $profitable_orders = 0;
                }
            } else {
                $total_orders = $new_orders = $total_revenue = $total_cost = $total_profit = 0;
                $orders_by_month = $orders_by_day = $orders_by_status = $orders_by_source = [];
            }
        ?>
            <section class="dashboard-section mb-5">
                <h2><i class="fas fa-shopping-cart"></i> Báo cáo đơn hàng</h2>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="summary-card card">
                            <div class="summary-icon orders">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-label">Tổng đơn hàng</div>
                                <div class="summary-value"><?= number_format($total_orders) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card card">
                            <div class="summary-icon revenue">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-label">Tổng doanh thu</div>
                                <div class="summary-value"><?= number_format($total_revenue) ?> đ</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card card">
                            <div class="summary-icon cost">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-label">Tổng chi phí</div>
                                <div class="summary-value"><?= number_format($total_cost) ?> đ</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="summary-card card">
                            <div class="summary-icon <?= $total_profit >= 0 ? 'profit' : 'loss' ?>">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-label">Lợi nhuận</div>
                                <div class="summary-value <?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($total_profit) ?> đ
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container card">
                            <h5><i class="fas fa-chart-bar"></i>
                                <?php if (!empty($selected_month)): ?>
                                    Đơn hàng & Doanh thu theo ngày (<?= date('m/Y', strtotime($selected_month)) ?>)
                                <?php else: ?>
                                    Đơn hàng & Doanh thu theo tháng (12 tháng gần nhất)
                                <?php endif; ?>
                            </h5>
                            <?php if (empty($selected_month) && empty($orders_by_month)): ?>
                                <div class="no-data">Không có dữ liệu đơn hàng</div>
                            <?php elseif (!empty($selected_month) && empty($orders_by_day)): ?>
                                <div class="no-data">Không có dữ liệu đơn hàng trong tháng này</div>
                            <?php else: ?>
                                <canvas id="orderChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="chart-container card">
                            <h5><i class="fas fa-chart-bar"></i> Đơn hàng theo trạng thái
                                <?php if (!empty($selected_month)): ?>
                                    (<?= date('m/Y', strtotime($selected_month)) ?>)
                                <?php else: ?>
                                    (12 tháng gần nhất)
                                <?php endif; ?>
                            </h5>
                            <?php if (empty($orders_by_status)): ?>
                                <div class="no-data">Không có dữ liệu trạng thái</div>
                            <?php else: ?>
                                <canvas id="orderStatusChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container card">
                            <h5><i class="fas fa-chart-bar"></i> Đơn hàng theo nguồn khách hàng
                                <?php if (!empty($selected_month)): ?>
                                    (<?= date('m/Y', strtotime($selected_month)) ?>)
                                <?php else: ?>
                                    (12 tháng gần nhất)
                                <?php endif; ?>
                            </h5>
                            <?php if (empty($orders_by_source)): ?>
                                <div class="no-data">Không có dữ liệu nguồn</div>
                            <?php else: ?>
                                <canvas id="orderSourceChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container card">
                            <h5><i class="fas fa-chart-pie"></i> Phân bố khách hàng
                                <?php if (!empty($selected_month)): ?>
                                    (<?= date('m/Y', strtotime($selected_month)) ?>)
                                <?php else: ?>
                                    (12 tháng gần nhất)
                                <?php endif; ?>
                            </h5>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="metric-item">
                                        <div class="metric-value text-primary"><?= number_format($returning_customers) ?></div>
                                        <div class="metric-label">Khách hàng quay lại</div>
                                        <small class="text-muted">(≥2 đơn)</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="metric-item">
                                        <div class="metric-value text-success"><?= number_format($new_customers_with_orders) ?></div>
                                        <div class="metric-label">Khách hàng mới</div>
                                        <small class="text-muted">(1 đơn)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="chart-container card">
                            <h5><i class="fas fa-chart-bar"></i> Báo cáo doanh thu
                                <?php if (!empty($selected_month)): ?>
                                    (<?= date('m/Y', strtotime($selected_month)) ?>)
                                <?php else: ?>
                                    (12 tháng gần nhất)
                                <?php endif; ?>
                            </h5>
                            <div class="row text-center">
                                <div class="col-3">
                                    <div class="metric-item">
                                        <div class="metric-value text-primary"><?= number_format($avg_order_revenue) ?> đ</div>
                                        <div class="metric-label">Doanh thu TB/đơn</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="metric-item">
                                        <div class="metric-value text-warning"><?= number_format($zero_amount_orders) ?></div>
                                        <div class="metric-label">Đơn hàng 0đ</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="metric-item">
                                        <div class="metric-value text-success"><?= number_format($profitable_orders) ?></div>
                                        <div class="metric-label">Đơn có lợi nhuận</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="metric-item">
                                        <div class="metric-value text-danger"><?= number_format($total_orders - $profitable_orders) ?></div>
                                        <div class="metric-label">Đơn không lợi nhuận</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    <?php if (!empty($selected_month)): ?>
                        // Biểu đồ theo ngày trong tháng được chọn
                        var orderChartData = {
                            labels: <?= json_encode(array_column($orders_by_day, 'day')) ?>,
                            orders: <?= json_encode(array_column($orders_by_day, 'total')) ?>,
                            revenue: <?= json_encode(array_column($orders_by_day, 'revenue')) ?>
                        };
                    <?php else: ?>
                        // Biểu đồ theo tháng (12 tháng gần nhất) - MẶC ĐỊNH
                        var orderChartData = {
                            labels: <?= json_encode(array_column($orders_by_month, 'month')) ?>,
                            orders: <?= json_encode(array_column($orders_by_month, 'total')) ?>,
                            revenue: <?= json_encode(array_column($orders_by_month, 'revenue')) ?>
                        };
                    <?php endif; ?>

                    var orderStatusData = {
                        labels: <?= json_encode(array_column($orders_by_status, 'status')) ?>,
                        data: <?= json_encode(array_column($orders_by_status, 'count')) ?>
                    };

                    // Tạo dữ liệu cho biểu đồ trạng thái theo tháng/ngày
                    <?php if (!empty($selected_month)): ?>
                        // Dữ liệu cho biểu đồ cột nhóm theo ngày trong tháng
                        var orderStatusGroupData = {
                            labels: <?= json_encode(array_unique(array_column($orders_by_status_month, 'day'))) ?>,
                            statuses: <?= json_encode(array_unique(array_column($orders_by_status_month, 'status'))) ?>,
                            datasets: []
                        };

                        // Tạo datasets cho từng trạng thái
                        <?php
                        $statuses = array_unique(array_column($orders_by_status_month, 'status'));
                        if (!empty($statuses)):
                            foreach ($statuses as $status):
                                $status_data = array_filter($orders_by_status_month, function ($item) use ($status) {
                                    return $item['status'] === $status;
                                });
                                $status_color = '';
                                $status_name_vi = '';
                                switch ($status) {
                                    case 'new':
                                        $status_color = '#6c757d';
                                        $status_name_vi = 'Mới tiếp nhận';
                                        break;
                                    case 'assigned':
                                        $status_color = '#0d6efd';
                                        $status_name_vi = 'Đã phân đơn';
                                        break;
                                    case 'rejected':
                                        $status_color = '#ffca2c';
                                        $status_name_vi = 'Đơn từ chối';
                                        break;
                                    case 'completed':
                                        $status_color = '#31d2f2';
                                        $status_name_vi = 'Đã hoàn thành';
                                        break;
                                    case 'paid':
                                        $status_color = '#198754';
                                        $status_name_vi = 'Đã thu tiền';
                                        break;
                                    case 'cancelled':
                                        $status_color = '#dc3545';
                                        $status_name_vi = 'Đã hủy';
                                        break;
                                    default:
                                        $status_color = '#cccccc';
                                        $status_name_vi = $status;
                                }
                        ?>
                                orderStatusGroupData.datasets.push({
                                    label: '<?= $status_name_vi ?>',
                                    data: <?= json_encode(array_column($status_data, 'count')) ?>,
                                    backgroundColor: '<?= $status_color ?>',
                                    borderColor: '<?= $status_color ?>',
                                    borderWidth: 1
                                });
                        <?php
                            endforeach;
                        endif;
                        ?>
                    <?php else: ?>
                        // Dữ liệu cho biểu đồ cột nhóm theo tháng (12 tháng)
                        var orderStatusGroupData = {
                            labels: <?= json_encode($months_to_show) ?>,
                            statuses: <?= json_encode(array_unique(array_column($orders_by_status_month, 'status'))) ?>,
                            datasets: []
                        };

                        // Tạo datasets cho từng trạng thái
                        <?php
                        $statuses = array_unique(array_column($orders_by_status_month, 'status'));
                        if (!empty($statuses)):
                            foreach ($statuses as $status):
                                $status_data = array_filter($orders_by_status_month, function ($item) use ($status) {
                                    return $item['status'] === $status;
                                });
                                $status_color = '';
                                $status_name_vi = '';
                                switch ($status) {
                                    case 'new':
                                        $status_color = '#6c757d';
                                        $status_name_vi = 'Mới tiếp nhận';
                                        break;
                                    case 'assigned':
                                        $status_color = '#0d6efd';
                                        $status_name_vi = 'Đã phân đơn';
                                        break;
                                    case 'rejected':
                                        $status_color = '#ffca2c';
                                        $status_name_vi = 'Đơn từ chối';
                                        break;
                                    case 'completed':
                                        $status_color = '#31d2f2';
                                        $status_name_vi = 'Đã hoàn thành';
                                        break;
                                    case 'paid':
                                        $status_color = '#198754';
                                        $status_name_vi = 'Đã thu tiền';
                                        break;
                                    case 'cancelled':
                                        $status_color = '#dc3545';
                                        $status_name_vi = 'Đã hủy';
                                        break;
                                    default:
                                        $status_color = '#cccccc';
                                        $status_name_vi = $status;
                                }

                                // Tạo mảng dữ liệu cho 12 tháng
                                $monthly_data = [];
                                foreach ($months_to_show as $month) {
                                    $month_data = array_filter($status_data, function ($item) use ($month) {
                                        return $item['month'] === $month;
                                    });
                                    $monthly_data[] = !empty($month_data) ? array_values($month_data)[0]['count'] : 0;
                                }
                        ?>
                                orderStatusGroupData.datasets.push({
                                    label: '<?= $status_name_vi ?>',
                                    data: <?= json_encode($monthly_data) ?>,
                                    backgroundColor: '<?= $status_color ?>',
                                    borderColor: '<?= $status_color ?>',
                                    borderWidth: 1
                                });
                        <?php
                            endforeach;
                        endif;
                        ?>
                    <?php endif; ?>

                    <?php if (!empty($selected_month)): ?>
                        // Dữ liệu cho biểu đồ cột nhóm theo ngày trong tháng
                        var orderSourceGroupData = {
                            labels: <?= json_encode(array_unique(array_column($orders_by_source_month, 'day'))) ?>,
                            sources: <?= json_encode(array_unique(array_column($orders_by_source_month, 'source_name'))) ?>,
                            colors: <?= json_encode(array_unique(array_column($orders_by_source_month, 'source_color'))) ?>,
                            datasets: []
                        };

                        // Tạo datasets cho từng nguồn
                        <?php
                        $sources = array_unique(array_column($orders_by_source_month, 'source_name'));
                        if (!empty($sources)):
                            foreach ($sources as $source_name):
                                $source_data = array_filter($orders_by_source_month, function ($item) use ($source_name) {
                                    return $item['source_name'] === $source_name;
                                });
                                $source_color = array_values($source_data)[0]['source_color'] ?? '#cccccc';
                        ?>
                                orderSourceGroupData.datasets.push({
                                    label: '<?= $source_name ?>',
                                    data: <?= json_encode(array_column($source_data, 'count')) ?>,
                                    backgroundColor: '<?= $source_color ?>',
                                    borderColor: '<?= str_replace('0.5', '1', $source_color) ?>',
                                    borderWidth: 1
                                });
                        <?php
                            endforeach;
                        endif;
                        ?>
                    <?php else: ?>
                        // Dữ liệu cho biểu đồ cột nhóm theo tháng (12 tháng)
                        var orderSourceGroupData = {
                            labels: <?= json_encode($months_to_show) ?>,
                            sources: <?= json_encode(array_unique(array_column($orders_by_source_month, 'source_name'))) ?>,
                            colors: <?= json_encode(array_unique(array_column($orders_by_source_month, 'source_color'))) ?>,
                            datasets: []
                        };

                        // Tạo datasets cho từng nguồn
                        <?php
                        $sources = array_unique(array_column($orders_by_source_month, 'source_name'));
                        if (!empty($sources)):
                            foreach ($sources as $source_name):
                                $source_data = array_filter($orders_by_source_month, function ($item) use ($source_name) {
                                    return $item['source_name'] === $source_name;
                                });
                                $source_color = array_values($source_data)[0]['source_color'] ?? '#cccccc';

                                // Tạo mảng dữ liệu cho 12 tháng
                                $monthly_data = [];
                                foreach ($months_to_show as $month) {
                                    $month_data = array_filter($source_data, function ($item) use ($month) {
                                        return $item['month'] === $month;
                                    });
                                    $monthly_data[] = !empty($month_data) ? array_values($month_data)[0]['count'] : 0;
                                }
                        ?>
                                orderSourceGroupData.datasets.push({
                                    label: '<?= $source_name ?>',
                                    data: <?= json_encode($monthly_data) ?>,
                                    backgroundColor: '<?= $source_color ?>',
                                    borderColor: '<?= str_replace('0.5', '1', $source_color) ?>',
                                    borderWidth: 1
                                });
                        <?php
                            endforeach;
                        endif;
                        ?>
                    <?php endif; ?>

                    // Tạo dữ liệu cho biểu đồ cột đơn giản của nguồn khách hàng
                    var orderSourceSimpleData = {
                        labels: <?= json_encode(array_column($orders_by_source, 'source_name')) ?>,
                        data: <?= json_encode(array_column($orders_by_source, 'count')) ?>,
                        colors: <?= json_encode(array_column($orders_by_source, 'source_color')) ?>
                    };

                    jQuery(function($) {
                        // Đăng ký plugin DataLabels
                        if (typeof ChartDataLabels !== 'undefined') {
                            Chart.register(ChartDataLabels);
                        }

                        // Order Chart
                        if (typeof Chart !== 'undefined' && $('#orderChart').length && orderChartData.labels.length > 0) {
                            new Chart(document.getElementById('orderChart'), {
                                type: 'bar',
                                data: {
                                    labels: orderChartData.labels,
                                    datasets: [{
                                            label: <?php if (!empty($selected_month)): ?> 'Số đơn theo ngày'
                                        <?php else: ?> 'Số đơn theo tháng'
                                        <?php endif; ?>,
                                        data: orderChartData.orders,
                                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1,
                                        yAxisID: 'y'
                                        },
                                        {
                                            label: 'Doanh thu',
                                            data: orderChartData.revenue,
                                            backgroundColor: 'rgba(255, 206, 86, 0.2)',
                                            borderColor: 'rgba(255, 206, 86, 1)',
                                            borderWidth: 3,
                                            type: 'line',
                                            yAxisID: 'y1',
                                            tension: 0.3,
                                            pointRadius: 6,
                                            pointBackgroundColor: 'rgba(255, 206, 86, 1)',
                                            pointBorderColor: '#333',
                                            pointBorderWidth: 3
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        },
                                        y1: {
                                            beginAtZero: true,
                                            position: 'right',
                                            grid: {
                                                drawOnChartArea: false
                                            },
                                            ticks: {
                                                callback: function(value) {
                                                    return new Intl.NumberFormat('vi-VN', {
                                                        style: 'currency',
                                                        currency: 'VND'
                                                    }).format(value);
                                                }
                                            }
                                        }
                                    },
                                    plugins: {
                                        datalabels: {
                                            color: '#000',
                                            font: {
                                                weight: 'bold',
                                                size: 12
                                            },
                                            anchor: 'top',
                                            align: 'top',
                                            offset: 5,
                                            formatter: function(value, context) {
                                                if (context.datasetIndex === 0) {
                                                    return value > 0 ? value : '';
                                                }
                                                return '';
                                            }
                                        }
                                    }
                                }
                            });
                        }

                        // Order Status Chart
                        if (typeof Chart !== 'undefined' && $('#orderStatusChart').length) {
                            if (orderStatusGroupData.datasets.length > 0) {
                                // Sử dụng biểu đồ cột nhóm theo tháng/ngày
                                new Chart(document.getElementById('orderStatusChart'), {
                                    type: 'bar',
                                    data: {
                                        labels: orderStatusGroupData.labels,
                                        datasets: orderStatusGroupData.datasets
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: {
                                                    stepSize: 1
                                                }
                                            }
                                        },
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    usePointStyle: true,
                                                    padding: 20
                                                }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        return context.dataset.label + ': ' + context.raw + ' đơn';
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                color: '#000',
                                                font: {
                                                    weight: 'bold',
                                                    size: 12
                                                },
                                                anchor: 'top',
                                                align: 'top',
                                                offset: 5,
                                                formatter: function(value) {
                                                    return value > 0 ? value : '';
                                                }
                                            }
                                        }
                                    }
                                });
                            } else if (orderStatusData.labels.length > 0) {
                                // Fallback: sử dụng biểu đồ cột đơn giản nếu không có dữ liệu theo tháng
                                new Chart(document.getElementById('orderStatusChart'), {
                                    type: 'bar',
                                    data: {
                                        labels: orderStatusData.labels.map(function(status) {
                                            var statusMap = {
                                                'new': 'Mới tiếp nhận',
                                                'assigned': 'Đã phân đơn',
                                                'rejected': 'Đơn từ chối',
                                                'completed': 'Đã hoàn thành',
                                                'paid': 'Đã thu tiền',
                                                'cancelled': 'Đã hủy'
                                            };
                                            return statusMap[status] || status;
                                        }),
                                        datasets: [{
                                            label: 'Số đơn hàng',
                                            data: orderStatusData.data,
                                            backgroundColor: [
                                                '#6c757d', '#0d6efd', '#ffca2c', '#31d2f2',
                                                '#198754', '#dc3545'
                                            ],
                                            borderColor: [
                                                '#6c757d', '#0d6efd', '#ffca2c', '#31d2f2',
                                                '#198754', '#dc3545'
                                            ],
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: {
                                                    stepSize: 1
                                                }
                                            }
                                        },
                                        plugins: {
                                            legend: {
                                                display: false
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        return context.label + ': ' + context.raw + ' đơn';
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                color: '#000',
                                                font: {
                                                    weight: 'bold',
                                                    size: 12
                                                },
                                                anchor: 'top',
                                                align: 'top',
                                                offset: 5,
                                                formatter: function(value) {
                                                    return value > 0 ? value : '';
                                                }
                                            }
                                        }
                                    }
                                });
                            } else {
                                // Hiển thị thông báo không có dữ liệu
                                $('#orderStatusChart').parent().html('<div class="no-data">Không có dữ liệu trạng thái để hiển thị</div>');
                            }
                        }

                        // Order Source Chart
                        if (typeof Chart !== 'undefined' && $('#orderSourceChart').length) {
                            if (orderSourceGroupData.datasets.length > 0) {
                                new Chart(document.getElementById('orderSourceChart'), {
                                    type: 'bar',
                                    data: {
                                        labels: orderSourceGroupData.labels,
                                        datasets: orderSourceGroupData.datasets
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: {
                                                    stepSize: 1
                                                }
                                            }
                                        },
                                        plugins: {
                                            legend: {
                                                position: 'bottom',
                                                labels: {
                                                    usePointStyle: true,
                                                    padding: 20
                                                }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        return context.dataset.label + ': ' + context.raw + ' đơn hàng';
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                color: '#000',
                                                font: {
                                                    weight: 'bold',
                                                    size: 12
                                                },
                                                anchor: 'top',
                                                align: 'top',
                                                offset: 5,
                                                formatter: function(value) {
                                                    return value > 0 ? value : '';
                                                }
                                            }
                                        }
                                    }
                                });
                            } else if (orderSourceSimpleData.labels.length > 0) {
                                // Sử dụng biểu đồ cột đơn giản nếu không có dữ liệu phức tạp
                                new Chart(document.getElementById('orderSourceChart'), {
                                    type: 'bar',
                                    data: {
                                        labels: orderSourceSimpleData.labels,
                                        datasets: [{
                                            label: 'Số đơn hàng',
                                            data: orderSourceSimpleData.data,
                                            backgroundColor: orderSourceSimpleData.colors,
                                            borderColor: orderSourceSimpleData.colors.map(color => color.replace('0.5', '1')),
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: {
                                                    stepSize: 1
                                                }
                                            }
                                        },
                                        plugins: {
                                            legend: {
                                                display: false
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        return context.label + ': ' + context.raw + ' đơn hàng';
                                                    }
                                                }
                                            },
                                            datalabels: {
                                                color: '#000',
                                                font: {
                                                    weight: 'bold',
                                                    size: 12
                                                },
                                                anchor: 'top',
                                                align: 'top',
                                                offset: 5,
                                                formatter: function(value) {
                                                    return value > 0 ? value : '';
                                                }
                                            }
                                        }
                                    }
                                });
                            } else {
                                // Hiển thị thông báo không có dữ liệu
                                $('#orderSourceChart').parent().html('<div class="no-data">Không có dữ liệu nguồn khách hàng để hiển thị</div>');
                            }
                        }
                    });
                </script>
            <?php } ?>
            </section>
        <?php endif; ?>

        <script>
            // Cập nhật tháng khi user thay đổi
            document.getElementById('month').addEventListener('change', function() {
                var selectedMonth = this.value;
                document.getElementById('month-hidden').value = selectedMonth;
                document.getElementById('report-month-hidden').value = selectedMonth;
            });
        </script>
</div>

<style>
    .dashboard-section {
        margin-bottom: 40px;
    }

    .summary-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 20px;
        margin-bottom: 16px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: 1px solidrgb(205, 206, 207);
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    }

    .summary-card .summary-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 15px;
        font-size: 1.5rem;
        color: white;
    }

    .summary-icon {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .summary-icon.active {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }

    .summary-icon.resigned {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    }

    .summary-icon.turnover {
        background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    }

    .summary-icon.orders {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }

    .summary-icon.revenue {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }

    .summary-icon.cost {
        background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
    }

    .summary-icon.profit {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }

    .summary-icon.loss {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
    }

    .summary-icon.warehouses {
        background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
    }

    .summary-icon.products {
        background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
    }

    .summary-icon.low-stock {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    }

    .summary-icon.out-of-stock {
        background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
    }

    .summary-icon.customers {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    }

    .summary-icon.new-customers {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }

    .summary-icon.active-customers {
        background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
    }

    .summary-icon.growth {
        background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
    }

    .summary-content {
        text-align: center;
    }

    .summary-label {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .summary-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: #212529;
    }

    .dashboard-section h2 {
        font-size: 1.5rem;
        margin-bottom: 24px;
        color: #495057;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 10px;
    }

    .dashboard-section h2 i {
        margin-right: 10px;
        color: #007bff;
    }

    .chart-container {
        min-height: 300px;
        max-width: 100%;
        margin-bottom: 24px;
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solidrgb(205, 205, 206);
        position: relative;
    }

    .chart-container h5 {
        margin-bottom: 20px;
        color: #495057;
        font-weight: 600;
    }

    .chart-container h5 i {
        margin-right: 8px;
        color: #007bff;
    }

    .chart-container canvas {
        max-width: 100% !important;
        max-height: 250px !important;
    }

    .no-data {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 200px;
        color: #6c757d;
        font-style: italic;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }

    .low-stock-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .low-stock-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.2s ease;
    }

    .low-stock-item:hover {
        background-color: #f8f9fa;
    }

    .low-stock-item:last-child {
        border-bottom: none;
    }

    .low-stock-item .product-name {
        font-weight: 500;
        color: #495057;
        flex: 1;
    }

    .low-stock-item .warehouse-name {
        font-size: 0.8rem;
        color: #6c757d;
        margin-right: 10px;
    }

    .low-stock-item .quantity {
        font-weight: bold;
        padding: 4px 8px;
        border-radius: 4px;
        min-width: 40px;
        text-align: center;
    }

    .low-stock-item .quantity.low-stock {
        background-color: #fff3cd;
        color: #856404;
    }

    .low-stock-item .quantity.out-of-stock {
        background-color: #f8d7da;
        color: #721c24;
    }

    .alert {
        border-radius: 8px;
        border: none;
        padding: 15px 20px;
    }

    .alert i {
        margin-right: 8px;
    }

    .metric-item {
        padding: 15px 10px;
        border-radius: 8px;
        background: #f8f9fa;
        margin-bottom: 10px;
        transition: all 0.3s ease;
        border: 1px solid rgb(205, 206, 207);
    }

    .metric-item:hover {
        background: #e9ecef;
        transform: translateY(-2px);
    }

    .metric-value {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .metric-label {
        font-size: 0.9rem;
        color: #6c757d;
        font-weight: 500;
    }

    @media (max-width: 768px) {
        .summary-card {
            margin-bottom: 20px;
        }

        .chart-container {
            min-height: 250px;
        }

        .summary-value {
            font-size: 1.5rem;
        }
    }
</style>
<?php
$content = ob_get_clean();
$title = 'AERP Order Dashboard';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
