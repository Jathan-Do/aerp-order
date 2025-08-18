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

// Lấy các tham số lọc
$month = isset($_GET['month']) ? $_GET['month'] : '';
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
$work_location_filter = isset($_GET['work_location']) ? intval($_GET['work_location']) : 0;
$employee_filter = isset($_GET['employee']) ? intval($_GET['employee']) : 0;
$today = date('Y-m-d');
// Xác định khoảng thời gian để truy vấn
$time_period = '';
$time_label = '';

if (!empty($month)) {
    // Nếu chọn tháng cụ thể
    $start_date = date('Y-m-01', strtotime($month));
    $end_date = date('Y-m-t', strtotime($month));
    $time_period = 'month';
    $time_label = 'Tháng ' . date('m/Y', strtotime($month));
} elseif (!empty($start_date) && !empty($end_date)) {
    // Nếu chọn khoảng ngày
    $time_period = 'date_range';
    $time_label = 'Từ ' . date('d/m/Y', strtotime($start_date)) . ' đến ' . date('d/m/Y', strtotime($end_date));
} else {
    // Mặc định: 12 tháng gần nhất
    $time_period = '12_months';
    $time_label = '12 tháng gần nhất';
    $start_date = date('Y-m-01');
    $end_date = $today;
}

$employee = function_exists('aerp_get_employee_by_user_id') ? aerp_get_employee_by_user_id($user_id) : null;
$work_location_id = $employee ? $employee->work_location_id : 0;
$warehouses = class_exists('AERP_Warehouse_Manager') ? AERP_Warehouse_Manager::aerp_get_warehouses_by_user($user_id) : [];

// Lấy danh sách chi nhánh để lọc
$work_locations = $wpdb->get_results("
    SELECT id, name 
    FROM {$wpdb->prefix}aerp_hrm_work_locations 
    ORDER BY name ASC
", ARRAY_A);

// Lấy danh sách nhân viên để lọc
$employees = $wpdb->get_results("
    SELECT e.id, e.full_name, wl.name as work_location_name
    FROM {$wpdb->prefix}aerp_hrm_employees e
    LEFT JOIN {$wpdb->prefix}aerp_hrm_work_locations wl ON e.work_location_id = wl.id
    WHERE e.status = 'active'
    ORDER BY e.full_name ASC
", ARRAY_A);

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
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="fas fa-filter"></i> Bộ lọc báo cáo</h5>
                <form method="get" class="row g-3">
                    <div class="col-md-2">
                        <label for="month" class="form-label">Tháng:</label>
                        <input class="form-control" type="month" id="month" name="month" value="<?= esc_attr($month) ?>"
                            max="<?= date('Y-m') ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Từ ngày:</label>
                        <input class="form-control" type="date" id="start_date" name="start_date"
                            value="<?= esc_attr($start_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">Đến ngày:</label>
                        <input class="form-control" type="date" id="end_date" name="end_date"
                            value="<?= esc_attr($end_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="work_location" class="form-label">Chi nhánh:</label>
                        <select class="form-select" id="work_location" name="work_location">
                            <option value="">Tất cả chi nhánh</option>
                            <?php foreach ($work_locations as $wl): ?>
                                <option value="<?= $wl['id'] ?>" <?= $work_location_filter == $wl['id'] ? 'selected' : '' ?>>
                                    <?= esc_html($wl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="employee" class="form-label">Nhân viên:</label>
                        <?php if (aerp_user_has_role($current_user->ID, 'admin')): ?>
                            <select class="form-select employee-select-all" id="employee" name="employee">
                            <?php else: ?>
                                <select class="form-select employee-select" id="employee" name="employee">
                                <?php endif; ?>
                                <!-- <option value="">Tất cả nhân viên</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= $employee_filter == $emp['id'] ? 'selected' : '' ?>>
                                    <?= esc_html($emp['full_name']) ?> (<?= esc_html($emp['work_location_name']) ?>)
                                </option>
                            <?php endforeach; ?> -->
                                </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Lọc
                        </button>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Xóa lọc
                        </a>
                    </div>
                </form>

                <!-- Nút xem 12 tháng gần nhất -->
                <div class="mt-3">
                    <a href="?month=" class="btn btn-outline-primary">
                        <i class="fas fa-calendar-alt"></i> Xem 12 tháng gần nhất
                    </a>
            </div>
            </div>
        </div>
    </div>

    <!-- <div class="mb-3">
        <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                
                <form method="post" action="<?= admin_url('admin-post.php') ?>" style="display: inline;">
                    <?php wp_nonce_field('aerp_export_excel', 'aerp_export_nonce'); ?>
                    <input type="hidden" name="action" value="aerp_export_excel_common">
                    <input type="hidden" name="callback" value="aerp_dashboard_export">
                    <input type="hidden" name="report_month" value="<?= esc_attr($month) ?>" id="report-month-hidden">
                    <input type="hidden" name="report_start_date" value="<?= esc_attr($start_date) ?>"
                        id="report-start-date-hidden">
                    <input type="hidden" name="report_end_date" value="<?= esc_attr($end_date) ?>"
                        id="report-end-date-hidden">
                    <input type="hidden" name="report_work_location" value="<?= esc_attr($work_location_filter) ?>"
                        id="report-work-location-hidden">
                    <input type="hidden" name="report_employee" value="<?= esc_attr($employee_filter) ?>"
                        id="report-employee-hidden">
                    <button type="submit" name="aerp_export_excel" class="btn btn-success">📥 Xuất Excel</button>
                </form>
            </div>
        </div>
    </div> -->

    <?php if ($order_active): ?>
        <?php
        // Xây dựng điều kiện WHERE cho các truy vấn
        $where_conditions = [];
        $where_params = [];

        // Điều kiện theo thời gian
        if ($time_period === 'month' || $time_period === 'date_range') {
            $where_conditions[] = "o.order_date BETWEEN %s AND %s";
            $where_params[] = $start_date;
            $where_params[] = $end_date;
        } elseif ($time_period === '12_months') {
                // Tạo danh sách 12 tháng gần nhất
                $months_to_show = [];
                for ($i = 11; $i >= 0; $i--) {
                    $months_to_show[] = date('Y-m', strtotime("-{$i} months"));
            }
            $where_conditions[] = "DATE_FORMAT(o.order_date, '%Y-%m') IN ('" . implode("','", $months_to_show) . "')";
        }

        // Điều kiện theo chi nhánh
        if (!empty($work_location_filter)) {
            $where_conditions[] = "e.work_location_id = %d";
            $where_params[] = $work_location_filter;
        } else {
            // Nếu không chọn chi nhánh cụ thể, chỉ lấy chi nhánh của user hiện tại
            if ($work_location_id) {
                $where_conditions[] = "e.work_location_id = %d";
                $where_params[] = $work_location_id;
            }
        }

        // Điều kiện theo nhân viên
        if (!empty($employee_filter)) {
            $where_conditions[] = "o.employee_id = %d";
            $where_params[] = $employee_filter;
        }

        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

        // Tạo danh sách 12 tháng gần nhất (luôn cần thiết)
        $months_to_show = [];
        for ($i = 11; $i >= 0; $i--) {
            $months_to_show[] = date('Y-m', strtotime("-{$i} months"));
        }

        // Thống kê tổng quan
        $total_orders_query = "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}aerp_order_orders o
            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
            $where_clause
        ";
        $total_orders = $wpdb->get_var($wpdb->prepare($total_orders_query, $where_params));

        // Thống kê doanh thu (chỉ tính đơn có status = 'paid')
        $revenue_query = "
            SELECT SUM(o.total_amount) 
            FROM {$wpdb->prefix}aerp_order_orders o
            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
            $where_clause AND o.status = 'paid'
        ";
        $total_revenue = $wpdb->get_var($wpdb->prepare($revenue_query, $where_params));

        // Thống kê chi phí (chỉ tính đơn có status = 'paid')
        $cost_query = "
            SELECT SUM(o.cost) 
            FROM {$wpdb->prefix}aerp_order_orders o
            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
            $where_clause AND o.status = 'paid' AND o.cost IS NOT NULL
        ";
        $total_cost = $wpdb->get_var($wpdb->prepare($cost_query, $where_params));

        // Thống kê đơn hàng theo trạng thái
        if ($time_period === '12_months') {
            // Cho 12 tháng: tạo dữ liệu theo từng tháng
            $status_query = "
                        SELECT o.status,
                       DATE_FORMAT(o.order_date, '%Y-%m') as month,
                               COUNT(*) as count
                        FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                $where_clause
                GROUP BY o.status, month
                ORDER BY o.status, month ASC
            ";
            $orders_by_status_month = $wpdb->get_results($wpdb->prepare($status_query, $where_params), ARRAY_A);

            // Tạo dữ liệu đầy đủ 12 tháng cho từng trạng thái
            $statuses = array_unique(array_column($orders_by_status_month, 'status'));
            $orders_by_status = [];

            foreach ($statuses as $status) {
                $status_data = array_filter($orders_by_status_month, function ($item) use ($status) {
                    return $item['status'] === $status;
                });

                // Tạo mảng dữ liệu cho 12 tháng
                $monthly_data = [];
                foreach ($months_to_show as $month) {
                    $month_data = array_filter($status_data, function ($item) use ($month) {
                        return $item['month'] === $month;
                    });
                    $monthly_data[] = !empty($month_data) ? array_values($month_data)[0]['count'] : 0;
                }

                $orders_by_status[] = [
                    'status' => $status,
                    'count' => array_sum($monthly_data), // Tổng cho 12 tháng
                    'monthly_data' => $monthly_data // Dữ liệu theo từng tháng
                ];
            }
        } else {
            // Cho tháng cụ thể hoặc khoảng ngày: sử dụng logic cũ
            $status_query = "
                SELECT o.status, COUNT(*) as count
                            FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                $where_clause
                GROUP BY o.status
            ";
            $orders_by_status = $wpdb->get_results($wpdb->prepare($status_query, $where_params), ARRAY_A);
        }

        // Thống kê đơn hàng theo thời gian
        if ($time_period === 'month' || $time_period === 'date_range') {
            // Theo ngày
            $time_query = "
                SELECT DATE(o.order_date) as time_unit, 
                       COUNT(*) as total, 
                       SUM(CASE WHEN o.status = 'paid' THEN o.total_amount ELSE 0 END) as revenue
                FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                $where_clause
                GROUP BY time_unit 
                ORDER BY time_unit ASC
            ";
            $orders_by_time = $wpdb->get_results($wpdb->prepare($time_query, $where_params), ARRAY_A);
            $time_labels = array_column($orders_by_time, 'time_unit');
                } else {
            // Theo tháng (12 tháng) - Tạo mảng dữ liệu đầy đủ 12 tháng

            // Lấy dữ liệu thực tế
            $time_query = "
                SELECT DATE_FORMAT(o.order_date, '%Y-%m') as time_unit, 
                       COUNT(*) as total, 
                       SUM(CASE WHEN o.status = 'paid' THEN o.total_amount ELSE 0 END) as revenue
                FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                $where_clause
                GROUP BY time_unit 
                ORDER BY time_unit ASC
            ";
            $orders_by_time_raw = $wpdb->get_results($wpdb->prepare($time_query, $where_params), ARRAY_A);

            // Tạo mảng dữ liệu đầy đủ 12 tháng với giá trị 0 cho tháng không có dữ liệu
            $orders_by_time = [];
            $month_data = array_column($orders_by_time_raw, 'total', 'time_unit');
            $month_revenue = array_column($orders_by_time_raw, 'revenue', 'time_unit');

                    foreach ($months_to_show as $month) {
                $orders_by_time[] = [
                    'time_unit' => $month,
                            'total' => isset($month_data[$month]) ? $month_data[$month] : 0,
                            'revenue' => isset($month_revenue[$month]) ? $month_revenue[$month] : 0
                        ];
                    }

            $time_labels = array_column($orders_by_time, 'time_unit');
        }

        // Thống kê đơn hàng theo nguồn khách hàng
        if ($time_period === '12_months') {
            // Cho 12 tháng: tạo dữ liệu theo từng tháng

            $source_query = "
                        SELECT cs.name as source_name, cs.color as source_color, cs.id as customer_source_id,
                               DATE_FORMAT(o.order_date, '%Y-%m') as month,
                               COUNT(*) as count
                        FROM {$wpdb->prefix}aerp_crm_customer_sources cs
                        INNER JOIN {$wpdb->prefix}aerp_order_orders o ON cs.id = o.customer_source_id
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                $where_clause AND o.customer_source_id IS NOT NULL AND o.customer_source_id != 0
                        GROUP BY cs.id, month
                        ORDER BY cs.name, month ASC
            ";
            $orders_by_source_month = $wpdb->get_results($wpdb->prepare($source_query, $where_params), ARRAY_A);

            // Tạo dữ liệu đầy đủ 12 tháng cho từng nguồn
            $sources = array_unique(array_column($orders_by_source_month, 'source_name'));
            $orders_by_source = [];

            foreach ($sources as $source_name) {
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

                $orders_by_source[] = [
                    'source_name' => $source_name,
                    'source_color' => $source_color,
                    'customer_source_id' => array_values($source_data)[0]['customer_source_id'] ?? 0,
                    'count' => array_sum($monthly_data), // Tổng cho 12 tháng
                    'monthly_data' => $monthly_data // Dữ liệu theo từng tháng
                ];
            }
        } else {
            // Cho tháng cụ thể hoặc khoảng ngày: sử dụng logic cũ
            $source_query = "
                        SELECT cs.name as source_name, cs.color as source_color, cs.id as customer_source_id, 
                               COALESCE(order_count.count, 0) as count
                        FROM {$wpdb->prefix}aerp_crm_customer_sources cs
                        LEFT JOIN (
                            SELECT o.customer_source_id, COUNT(*) as count
                            FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                    $where_clause AND o.customer_source_id IS NOT NULL AND o.customer_source_id != 0
                            GROUP BY o.customer_source_id
                        ) order_count ON cs.id = order_count.customer_source_id
                        ORDER BY count DESC
            ";
            $orders_by_source = $wpdb->get_results($wpdb->prepare($source_query, $where_params), ARRAY_A);
                }

                // Thống kê khách hàng quay lại và phân bố khách hàng
        // Tách riêng logic đếm khách hàng và logic tính toán doanh thu
        $customer_stats_query = "
            SELECT 
                COUNT(DISTINCT CASE WHEN order_count > 1 THEN o.customer_id END) as returning_customers,
                COUNT(DISTINCT CASE WHEN order_count = 1 THEN o.customer_id END) as new_customers,
                AVG(CASE WHEN o.status = 'paid' THEN o.total_amount ELSE NULL END) as avg_order_revenue,
                COUNT(CASE WHEN o.status = 'paid' AND (o.total_amount = 0 OR o.total_amount IS NULL) THEN 1 END) as zero_amount_orders,
                COUNT(CASE WHEN o.status = 'paid' AND (o.total_amount - COALESCE(o.cost, 0)) > 0 THEN 1 END) as profitable_orders,
                COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders,
                COUNT(CASE WHEN o.status = 'paid' THEN 1 END) as total_paid_orders
                            FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
            LEFT JOIN (
                SELECT customer_id, COUNT(*) as order_count
                                FROM {$wpdb->prefix}aerp_order_orders o2
                                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e2 ON o2.employee_id = e2.id
                WHERE 1=1
                " . (!empty($work_location_filter) ? "AND e2.work_location_id = " . intval($work_location_filter) : "") . "
                " . (!empty($employee_filter) ? "AND o2.employee_id = " . intval($employee_filter) : "") . "
                " . ($work_location_id && empty($work_location_filter) ? "AND e2.work_location_id = " . intval($work_location_id) : "") . "
                GROUP BY customer_id
            ) customer_counts ON o.customer_id = customer_counts.customer_id
            $where_clause
        ";

        $customer_stats = $wpdb->get_row($wpdb->prepare($customer_stats_query, $where_params), ARRAY_A);

        $returning_customers = $customer_stats ? $customer_stats['returning_customers'] : 0;
        $new_customers_with_orders = $customer_stats ? $customer_stats['new_customers'] : 0;
        $avg_order_revenue = $customer_stats ? $customer_stats['avg_order_revenue'] : 0;
        $zero_amount_orders = $customer_stats ? $customer_stats['zero_amount_orders'] : 0;
        $profitable_orders = $customer_stats ? $customer_stats['profitable_orders'] : 0;
        $cancelled_orders = $customer_stats ? $customer_stats['cancelled_orders'] : 0;
        $total_paid_orders = $customer_stats ? $customer_stats['total_paid_orders'] : 0;

        $total_profit = ($total_revenue ?? 0) - ($total_cost ?? 0);

        // Nếu xem 12 tháng, tạo dữ liệu theo từng tháng cho phân bố KH và báo cáo doanh thu
        if ($time_period === '12_months') {
            // Phân bố khách hàng theo tháng (new vs returning) – phân loại theo số đơn trong CHÍNH THÁNG đó
            // Cách làm giống khi lọc 1 tháng: >1 đơn trong tháng => quay lại, =1 => mới
            $customer_month_query = "
                SELECT month,
                       SUM(CASE WHEN order_per_customer > 1 THEN 1 ELSE 0 END) AS returning_customers,
                       SUM(CASE WHEN order_per_customer = 1 THEN 1 ELSE 0 END) AS new_customers
                FROM (
                    SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS month,
                           o.customer_id,
                           COUNT(*) AS order_per_customer
                            FROM {$wpdb->prefix}aerp_order_orders o
                            LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                    $where_clause
                    GROUP BY month, o.customer_id
                ) t
                GROUP BY month
                ORDER BY month ASC
            ";
            $customer_month_rows = $wpdb->get_results($wpdb->prepare($customer_month_query, $where_params), ARRAY_A);
            $tmp_ret = array_column($customer_month_rows, 'returning_customers', 'month');
            $tmp_new = array_column($customer_month_rows, 'new_customers', 'month');
            $customer_month_returning = [];
            $customer_month_new = [];
            foreach ($months_to_show as $m) {
                $customer_month_returning[] = isset($tmp_ret[$m]) ? intval($tmp_ret[$m]) : 0;
                $customer_month_new[] = isset($tmp_new[$m]) ? intval($tmp_new[$m]) : 0;
            }

            // Báo cáo doanh thu theo tháng (tách rõ: các chỉ số đếm và chỉ số tiền)
            $rev_month_query = "
                SELECT DATE_FORMAT(o.order_date, '%Y-%m') AS month,
                       COUNT(CASE WHEN o.status = 'paid' THEN 1 END) AS total_paid_orders,
                       AVG(CASE WHEN o.status = 'paid' THEN o.total_amount END) AS avg_order_revenue,
                       COUNT(CASE WHEN o.status = 'paid' AND (o.total_amount = 0 OR o.total_amount IS NULL) THEN 1 END) AS zero_amount_orders,
                       COUNT(CASE WHEN o.status = 'paid' AND (o.total_amount - COALESCE(o.cost, 0)) > 0 THEN 1 END) AS profitable_orders,
                       COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) AS cancelled_orders
                FROM {$wpdb->prefix}aerp_order_orders o
                LEFT JOIN {$wpdb->prefix}aerp_hrm_employees e ON o.employee_id = e.id
                $where_clause
                GROUP BY month
                ORDER BY month ASC
            ";
            $rev_month_rows = $wpdb->get_results($wpdb->prepare($rev_month_query, $where_params), ARRAY_A);
            $tmp_paid = array_column($rev_month_rows, 'total_paid_orders', 'month');
            $tmp_avg = array_column($rev_month_rows, 'avg_order_revenue', 'month');
            $tmp_zero = array_column($rev_month_rows, 'zero_amount_orders', 'month');
            $tmp_prof = array_column($rev_month_rows, 'profitable_orders', 'month');
            $tmp_cancel = array_column($rev_month_rows, 'cancelled_orders', 'month');
            $rev_month_total_paid = $rev_month_avg = $rev_month_zero = $rev_month_profitable = $rev_month_cancelled = [];
            foreach ($months_to_show as $m) {
                $rev_month_total_paid[] = isset($tmp_paid[$m]) ? intval($tmp_paid[$m]) : 0;
                $rev_month_avg[] = isset($tmp_avg[$m]) ? floatval($tmp_avg[$m]) : 0;
                $rev_month_zero[] = isset($tmp_zero[$m]) ? intval($tmp_zero[$m]) : 0;
                $rev_month_profitable[] = isset($tmp_prof[$m]) ? intval($tmp_prof[$m]) : 0;
                $rev_month_cancelled[] = isset($tmp_cancel[$m]) ? intval($tmp_cancel[$m]) : 0;
            }
        }
        ?>

            <section class="dashboard-section mb-5">
                <h2><i class="fas fa-shopping-cart"></i> Báo cáo đơn hàng</h2>

            <!-- Hiển thị thông tin bộ lọc đang áp dụng -->
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle"></i>
                <strong>Bộ lọc đang áp dụng:</strong> <?= $time_label ?>
                <?php if ($work_location_filter): ?>
                    | Chi nhánh:
                    <strong><?= $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}aerp_hrm_work_locations WHERE id = %d", $work_location_filter)) ?></strong>
                <?php endif; ?>
                <?php if ($employee_filter): ?>
                    | Nhân viên:
                    <strong><?= $wpdb->get_var($wpdb->prepare("SELECT full_name FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d", $employee_filter)) ?></strong>
                <?php endif; ?>
            </div>

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
                            <div class="summary-label">Doanh thu (đã thu tiền)</div>
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
                        <h5><i class="fas fa-chart-bar"></i> Đơn hàng & Doanh thu theo
                            <?= $time_period === 'month' || $time_period === 'date_range' ? 'ngày' : 'tháng' ?>
                            </h5>
                        <?php if (empty($orders_by_time)): ?>
                            <div class="no-data">Không có dữ liệu đơn hàng trong khoảng thời gian này</div>
                            <?php else: ?>
                                <canvas id="orderChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="chart-container card">
                        <h5><i class="fas fa-chart-bar"></i> Báo cáo doanh thu (chỉ tính đơn đã thu tiền)</h5>
                        <canvas id="revenueReportChart"></canvas>
                    </div>
                </div>
                <div class="col-12">
                    <div class="chart-container card">
                        <h5><i class="fas fa-chart-bar"></i> Đơn hàng theo trạng thái</h5>
                            <?php if (empty($orders_by_status)): ?>
                                <div class="no-data">Không có dữ liệu trạng thái</div>
                            <?php else: ?>
                                <canvas id="orderStatusChart"></canvas>
                            <?php endif; ?>
                    </div>
                </div>

                    <div class="col-12">
                        <div class="chart-container card">
                        <h5><i class="fas fa-chart-bar"></i> Đơn hàng theo nguồn khách hàng</h5>
                            <?php if (empty($orders_by_source)): ?>
                                <div class="no-data">Không có dữ liệu nguồn</div>
                            <?php else: ?>
                                <canvas id="orderSourceChart"></canvas>
                            <?php endif; ?>
                    </div>
                </div>

                    <div class="col-12">
                        <div class="chart-container card">
                        <h5><i class="fas fa-chart-bar"></i> Phân bố khách hàng</h5>
                        <canvas id="customerDistributionChart"></canvas>
                        </div>
                    </div>
                </div>


                <script>
                        var orderChartData = {
                    labels: <?= json_encode($time_labels) ?>,
                    orders: <?= json_encode(array_column($orders_by_time, 'total')) ?>,
                    revenue: <?= json_encode(array_column($orders_by_time, 'revenue')) ?>
                };

                    var orderStatusData = {
                        labels: <?= json_encode(array_column($orders_by_status, 'status')) ?>,
                        data: <?= json_encode(array_column($orders_by_status, 'count')) ?>
                    };

                var orderSourceSimpleData = {
                    labels: <?= json_encode(array_column($orders_by_source, 'source_name')) ?>,
                    data: <?= json_encode(array_column($orders_by_source, 'count')) ?>,
                    colors: <?= json_encode(array_column($orders_by_source, 'source_color')) ?>
                };

                // Dữ liệu cho Phân bố khách hàng
                <?php if ($time_period === '12_months'): ?>
                    var customerDistributionData = {
                        labels: <?= json_encode($months_to_show) ?>,
                        datasets: [{
                                label: 'Khách hàng quay lại (≥2 đơn)',
                                data: <?= json_encode($customer_month_returning ?? []) ?>,
                                backgroundColor: '#0d6efd',
                                borderColor: '#0d6efd'
                            },
                            {
                                label: 'Khách hàng mới (1 đơn)',
                                data: <?= json_encode($customer_month_new ?? []) ?>,
                                backgroundColor: '#20c997',
                                borderColor: '#20c997'
                            }
                        ]
                    };
                <?php else: ?>
                    var customerDistributionData = {
                        labels: ['Khách hàng quay lại (≥2 đơn)', 'Khách hàng mới (1 đơn)'],
                        datasets: [{
                            label: 'Số khách',
                            data: [<?= intval($returning_customers) ?>, <?= intval($new_customers_with_orders) ?>],
                            backgroundColor: ['#0d6efd', '#20c997'],
                            borderColor: ['#0d6efd', '#20c997']
                        }]
                    };
                <?php endif; ?>

                // Dữ liệu cho Báo cáo doanh thu (kết hợp số lượng + tiền như biểu đồ đầu)
                <?php if ($time_period === '12_months'): ?>
                    var revenueReportData = {
                        labels: <?= json_encode($months_to_show) ?>,
                        datasets: [{
                                label: 'Số đơn đã thu tiền',
                                type: 'bar',
                                data: <?= json_encode($rev_month_total_paid ?? []) ?>,
                                backgroundColor: 'rgba(13, 202, 240, 0.5)',
                                borderColor: '#0dcaf0',
                                yAxisID: 'y'
                            },
                            {
                                label: 'Đơn hàng 0đ',
                                type: 'bar',
                                data: <?= json_encode($rev_month_zero ?? []) ?>,
                                backgroundColor: 'rgba(255, 193, 7, 0.5)',
                                borderColor: '#ffc107',
                                yAxisID: 'y'
                            },
                            {
                                label: 'Đơn có lợi nhuận',
                                type: 'bar',
                                data: <?= json_encode($rev_month_profitable ?? []) ?>,
                                backgroundColor: 'rgba(25, 135, 84, 0.5)',
                                borderColor: '#198754',
                                yAxisID: 'y'
                            },
                            {
                                label: 'Đơn hủy',
                                type: 'bar',
                                data: <?= json_encode($rev_month_cancelled ?? []) ?>,
                                backgroundColor: 'rgba(220, 53, 69, 0.5)',
                                borderColor: '#dc3545',
                                yAxisID: 'y'
                            },
                            {
                                label: 'Doanh thu TB/đơn (VND)',
                                type: 'line',
                                data: <?= json_encode(array_map('intval', $rev_month_avg ?? [])) ?>,
                                backgroundColor: 'rgba(111, 66, 193, 0.2)',
                                borderColor: '#6f42c1',
                                yAxisID: 'y1',
                                tension: 0.3,
                                pointRadius: 6,
                                borderWidth: 3,
                                pointBackgroundColor: 'rgb(228, 228, 228)',
                                pointBorderColor: '#333',
                                pointBorderWidth: 3
                            }
                        ]
                    };
                <?php else: ?>
                    var revenueReportData = {
                        labels: ['Tổng đơn đã thu tiền', 'Đơn hàng 0đ', 'Đơn có lợi nhuận', 'Đơn hủy'],
                        datasets: [{
                                label: 'Số đơn',
                                type: 'bar',
                                data: [<?= intval($total_paid_orders ?? 0) ?>, <?= intval($zero_amount_orders ?? 0) ?>, <?= intval($profitable_orders ?? 0) ?>, <?= intval($cancelled_orders ?? 0) ?>],
                                backgroundColor: ['rgba(13, 202, 240, 0.5)', 'rgba(255, 193, 7, 0.5)', 'rgba(25, 135, 84, 0.5)', 'rgba(220, 53, 69, 0.5)'],
                                borderColor: ['#0dcaf0', '#ffc107', '#198754', '#dc3545'],
                                yAxisID: 'y'
                            },
                            {
                                label: 'Doanh thu TB/đơn (VND)',
                                type: 'line',
                                data: [<?= intval(round($avg_order_revenue ?? 0)) ?>, <?= intval(round($avg_order_revenue ?? 0)) ?>, <?= intval(round($avg_order_revenue ?? 0)) ?>, <?= intval(round($avg_order_revenue ?? 0)) ?>],
                                backgroundColor: 'rgba(111, 66, 193, 0.2)',
                                borderColor: '#6f42c1',
                                yAxisID: 'y1',
                                tension: 0.3,
                                pointRadius: 6,
                                borderWidth: 3,
                                pointBackgroundColor: 'rgb(228, 228, 228)',
                                pointBorderColor: '#333',
                                pointBorderWidth: 3
                            }
                        ]
                    };
                <?php endif; ?>

                <?php if ($time_period === '12_months'): ?>
                    // Dữ liệu cho biểu đồ cột nhóm theo tháng (12 tháng)
                    var orderSourceGroupData = {
                        labels: <?= json_encode($months_to_show) ?>,
                        sources: <?= json_encode(array_unique(array_column($orders_by_source, 'source_name'))) ?>,
                        colors: <?= json_encode(array_unique(array_column($orders_by_source, 'source_color'))) ?>,
                            datasets: []
                        };

                    // Tạo datasets cho từng nguồn
                        <?php
                    if (!empty($orders_by_source)):
                        foreach ($orders_by_source as $source):
                            if (isset($source['monthly_data'])):
                    ?>
                                orderSourceGroupData.datasets.push({
                                    label: '<?= $source['source_name'] ?>',
                                    data: <?= json_encode($source['monthly_data']) ?>,
                                    backgroundColor: '<?= $source['source_color'] ?>',
                                    borderColor: '<?= str_replace('0.5', '1', $source['source_color']) ?>',
                                    borderWidth: 1
                                });
                        <?php
                            endif;
                            endforeach;
                        endif;
                        ?>

                    // Dữ liệu cho biểu đồ trạng thái theo tháng (12 tháng)
                        var orderStatusGroupData = {
                            labels: <?= json_encode($months_to_show) ?>,
                            datasets: []
                        };

                        // Tạo datasets cho từng trạng thái
                        <?php
                    if (!empty($orders_by_status)):
                        foreach ($orders_by_status as $status):
                            if (isset($status['monthly_data'])):
                                // Xác định màu và tên tiếng Việt cho từng trạng thái
                                $status_color = '#cccccc';
                                $status_name_vi = $status['status'];
                                switch ($status['status']) {
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
                                        $status_name_vi = $status['status'];
                                }
                        ?>
                                orderStatusGroupData.datasets.push({
                                    label: '<?= $status_name_vi ?>',
                                    data: <?= json_encode($status['monthly_data']) ?>,
                                    backgroundColor: '<?= $status_color ?>',
                                    borderColor: '<?= $status_color ?>',
                                    borderWidth: 1
                                });
                        <?php
                        endif;
                            endforeach;
                        endif;
                        ?>
                    <?php endif; ?>

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
                                        label: 'Số đơn theo <?= $time_period === 'month' || $time_period === 'date_range' ? 'ngày' : 'tháng' ?>',
                                        data: orderChartData.orders,
                                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1,
                                        yAxisID: 'y'
                                        },
                                        {
                                        label: 'Doanh thu (đã thu tiền)',
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
                                        anchor: function(ctx) {
                                            return ctx.datasetIndex === 1 ? 'end' : 'top';
                                        },
                                        align: function(ctx) {
                                            return ctx.datasetIndex === 1 ? 'top' : 'top';
                                        },
                                            offset: 5,
                                            formatter: function(value, context) {
                                                if (context.datasetIndex === 0) {
                                                // Cột số đơn
                                                    return value > 0 ? value : '';
                                                }
                                            if (context.datasetIndex === 1) {
                                                // Đường doanh thu: hiển thị trên điểm tròn
                                                return value > 0 ? new Intl.NumberFormat('vi-VN', {
                                                    maximumFractionDigits: 0
                                                }).format(value) + ' đ' : '';
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
                        <?php if ($time_period === '12_months'): ?>
                            // Sử dụng biểu đồ cột nhóm cho 12 tháng
                            if (typeof orderStatusGroupData !== 'undefined' && orderStatusGroupData.datasets.length > 0) {
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
                            } else {
                                // Fallback: sử dụng biểu đồ cột đơn giản
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
                            }
                        <?php else: ?>
                            // Sử dụng biểu đồ cột đơn giản cho tháng cụ thể hoặc khoảng ngày
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
                        <?php endif; ?>
                    }

                    // Customer Distribution Chart
                    if (typeof Chart !== 'undefined' && $('#customerDistributionChart').length) {
                        new Chart(document.getElementById('customerDistributionChart'), {
                            type: 'bar',
                            data: {
                                labels: customerDistributionData.labels,
                                datasets: customerDistributionData.datasets
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
                                        position: 'bottom'
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
                                            return value > 0 ? Math.round(value) : '';
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // Revenue Report Chart
                    if (typeof Chart !== 'undefined' && $('#revenueReportChart').length) {
                        new Chart(document.getElementById('revenueReportChart'), {
                            type: 'bar',
                            data: {
                                labels: revenueReportData.labels,
                                datasets: revenueReportData.datasets
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        position: 'left'
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
                                    legend: {
                                        position: 'bottom'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                if (context.dataset.label.indexOf('Doanh thu TB/đơn') === 0) {
                                                    return context.label + ': ' + new Intl.NumberFormat('vi-VN', {
                                                        style: 'currency',
                                                        currency: 'VND'
                                                    }).format(context.raw);
                                                }
                                                return context.dataset.label + ': ' + context.raw;
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
                                        formatter: function(value, ctx) {
                                            if (ctx.dataset && ctx.dataset.label.indexOf('Doanh thu TB/đơn') === 0) {
                                                return value > 0 ? new Intl.NumberFormat('vi-VN', {
                                                    maximumFractionDigits: 0
                                                }).format(value) + ' đ' : '';
                                            }
                                            return value > 0 ? value : '';
                                        }
                                    }
                                }
                            }
                        });
                        }

                        // Order Source Chart
                        if (typeof Chart !== 'undefined' && $('#orderSourceChart').length) {
                        <?php if ($time_period === '12_months'): ?>
                            // Sử dụng biểu đồ cột nhóm cho 12 tháng
                            if (typeof orderSourceGroupData !== 'undefined' && orderSourceGroupData.datasets.length > 0) {
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
                            } else {
                                // Fallback: sử dụng biểu đồ cột đơn giản
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
                            }
                        <?php else: ?>
                            // Sử dụng biểu đồ cột đơn giản cho tháng cụ thể hoặc khoảng ngày
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
                        <?php endif; ?>
                        }
                    });
                </script>
            </section>
        <?php endif; ?>
</div>

        <script>
    // Cập nhật các giá trị ẩn khi user thay đổi bộ lọc
            document.getElementById('month').addEventListener('change', function() {
        document.getElementById('report-month-hidden').value = this.value;
    });

    document.getElementById('start_date').addEventListener('change', function() {
        document.getElementById('report-start-date-hidden').value = this.value;
    });

    document.getElementById('end_date').addEventListener('change', function() {
        document.getElementById('report-end-date-hidden').value = this.value;
    });

    document.getElementById('work_location').addEventListener('change', function() {
        document.getElementById('report-work-location-hidden').value = this.value;
    });

    document.getElementById('employee').addEventListener('change', function() {
        document.getElementById('report-employee-hidden').value = this.value;
            });
        </script>


<style>
    .select2-container--default .select2-selection--single {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
        min-height: 38px !important;
        padding: 6px 12px !important;
        background: #fff !important;
        font-size: 1rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
        right: 0.75rem !important;
    }

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
