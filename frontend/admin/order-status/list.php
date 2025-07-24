<?php
if (!defined('ABSPATH')) exit;
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
    aerp_user_has_permission($user_id,'order_status_view'),

];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$table = new AERP_Order_Status_Table();
$table->process_bulk_action();
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Quản lý trạng thái đơn hàng</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách trạng thái đơn hàng</h5>
        <a href="<?php echo esc_url(home_url('/aerp-order-statuses/?action=add')); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Thêm mới
        </a>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form id="aerp-order-status-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-order-status-table-wrapper" data-ajax-action="aerp_order_status_filter_statuses">
            <div class="col-12 col-md-3 mb-2">
                <label for="filter-color" class="form-label mb-1">Màu sắc</label>
                <select id="filter-color" name="color" class="form-select">
                    <option value="">-- Tất cả --</option>
                    <option value="primary">Xanh dương</option>
                    <option value="secondary">Xám</option>
                    <option value="success">Xanh lá</option>
                    <option value="danger">Đỏ</option>
                    <option value="warning">Vàng</option>
                    <option value="info">Xanh nhạt</option>
                    <option value="dark">Đen</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>
        <?php $message = get_transient('aerp_order_status_message');
        if ($message) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">'
                . esc_html($message) .
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_order_status_message');
        } ?>
        <div id="aerp-order-status-table-wrapper">
            <?php $table->render(); ?>
        </div>
        <a href="<?php echo home_url('/aerp-order-orders'); ?>" class="btn btn-outline-secondary" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Quản lý trạng thái đơn hàng';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php'); 