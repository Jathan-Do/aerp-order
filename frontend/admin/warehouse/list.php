<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$table = new AERP_Warehouse_Table();
$table->process_bulk_action();
ob_start();
$message = get_transient('aerp_warehouse_message');
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Danh sách kho</h2>
    <div class="user-info text-end">
        Xin chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>
<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách kho</h5>
        <div class="d-flex gap-2 flex-column flex-md-row">
            <a href="<?php echo esc_url(home_url('/aerp-warehouses/?action=add')); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Thêm kho
            </a>
            <a href="<?php echo esc_url(home_url('/aerp-warehouses/?action=stock')); ?>" class="btn btn-info">
                <i class="fas fa-warehouse"></i> Tồn kho
            </a>
            <a href="<?php echo esc_url(home_url('/aerp-inventory-transfers')); ?>" class="btn btn-info">
                <i class="fas fa-exchange-alt"></i> Chuyển kho
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($message) {
            echo '<div class="notice notice-success alert alert-success alert-dismissible fade show" role="alert">' . esc_html($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_warehouse_message');
        } ?>
        <div id="aerp-warehouse-table-wrapper">
            <?php $table->render(); ?>
        </div>
        <a href="<?php echo home_url('/aerp-categories'); ?>" class="btn btn-outline-secondary" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Quay lại
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Danh sách kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
