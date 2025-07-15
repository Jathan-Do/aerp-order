<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$table = new AERP_Product_Stock_Table();
$table->process_bulk_action();
ob_start();
$message = get_transient('aerp_product_stock_message');
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Báo cáo tồn kho theo kho</h2>
    <div class="user-info text-end">
        Xin chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Tồn kho tại kho</h5>
    </div>
    <div class="card-body">
        <?php if ($message) {
            echo '<div class="notice notice-success alert alert-success alert-dismissible fade show" role="alert">' . esc_html($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_product_stock_message');
        } ?>
        <div id="aerp-product-stock-table-wrapper">
            <?php $table->render(); ?>
        </div>
        <a href="<?php echo home_url('/aerp-warehouses'); ?>" class="btn btn-outline-secondary" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Quay lại
        </a>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Báo cáo tồn kho theo kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
