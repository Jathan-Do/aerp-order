<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$table = new AERP_Inventory_Log_Table();
$table->process_bulk_action();
$message = get_transient('aerp_inventory_log_message');
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Lịch sử nhập/xuất kho</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách phiếu nhập/xuất kho</h5>
        <div class="d-flex gap-2 flex-column flex-md-row">
            <a href="<?php echo esc_url(home_url('/aerp-inventory-logs/?action=add&type=import')); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Nhập kho
            </a>
            <a href="<?php echo esc_url(home_url('/aerp-inventory-logs/?action=add&type=export')); ?>" class="btn btn-danger">
                <i class="fas fa-minus"></i> Xuất kho
            </a>
            <a href="<?php echo esc_url(home_url('/aerp-stocktake')); ?>" class="btn btn-warning">
                <i class="fas fa-check"></i> Kiểm kho
            </a>
            <!-- <a href="<?php echo esc_url(home_url('/aerp-stock-report')); ?>" class="btn btn-info">
                <i class="fas fa-chart-line"></i> Báo cáo tồn kho
            </a> -->
        </div>
    </div>
    <div class="card-body">
        <!-- Filter Form -->
        <form id="aerp-inventory-log-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-inventory-log-table-wrapper" data-ajax-action="aerp_inventory_log_filter_inventory_logs">
            <div class="col-12 col-md-3 mb-2">
                <label for="filter-type" class="form-label mb-1">Loại phiếu</label>
                <select id="filter-type" name="type" class="form-select">
                    <option value="">-- Tất cả --</option>
                    <option value="import">Nhập kho</option>
                    <option value="export">Xuất kho</option>
                    <option value="stocktake">Kiểm kho</option>
                </select>
            </div>
            <div class="col-12 col-md-3 mb-2">
                <label for="filter-status" class="form-label mb-1">Trạng thái</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">-- Tất cả --</option>
                    <option value="confirmed">Đã xác nhận</option>
                    <option value="draft">Nháp</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>
        <?php if ($message) {
            echo '<div class="notice notice-success alert alert-success alert-dismissible fade show" role="alert">' . esc_html($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_inventory_log_message');
        } ?>
        <div id="aerp-inventory-log-table-wrapper">
            <?php $table->render(); ?>
        </div>
        <a href="<?php echo home_url('/aerp-categories'); ?>" class="btn btn-outline-secondary" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Quay lại
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Lịch sử nhập/xuất kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
