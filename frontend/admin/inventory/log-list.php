<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
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
        </div>
    </div>
    <div class="card-body">
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
