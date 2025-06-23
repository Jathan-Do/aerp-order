<?php
$current_user = wp_get_current_user();
$table = new AERP_Frontend_Order_Table();
$table->process_bulk_action();
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Quản lý đơn hàng</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách đơn hàng</h5>
        <div class="d-flex gap-2 flex-column flex-md-row">
            <a href="<?php echo esc_url(home_url('/aerp-order-orders/?action=add')); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm mới đơn hàng
            </a>
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form id="aerp-order-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-order-table-wrapper" data-ajax-action="aerp_order_filter_orders">
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-status" class="form-label mb-1">Trạng thái</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="new">Mới</option>
                    <option value="processing">Xử lý</option>
                    <option value="completed">Hoàn tất</option>
                    <option value="cancelled">Hủy</option>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-employee" class="form-label mb-1">Nhân viên</label>
                <select id="filter-employee" name="employee_id" class="form-select">
                    <?php
                    $employees = function_exists('aerp_get_order_assigned_employees') ? aerp_get_order_assigned_employees() : [];
                    aerp_safe_select_options($employees, '', 'user_id', 'full_name', true);
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-customer" class="form-label mb-1">Khách hàng</label>
                <select id="filter-customer" name="customer_id" class="form-select">
                    <?php
                    $customers = function_exists('aerp_get_customers') ? aerp_get_customers() : [];
                    aerp_safe_select_options($customers, '', 'id', 'full_name', true);
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>
        <?php $message = get_transient('aerp_order_message');
        if ($message) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                . esc_html($message) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_order_message');
        }
        ?>
        <div id="aerp-order-table-wrapper">
            <?php $table->render(); ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Quản lý đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php'); 