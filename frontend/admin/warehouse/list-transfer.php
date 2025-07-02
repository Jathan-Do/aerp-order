<?php
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$table = new AERP_Inventory_Transfer_Table();
$table->process_bulk_action();

$message = get_transient('aerp_inventory_transfer_message');
ob_start();
?>

<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Danh sách phiếu chuyển kho</h2>
    <div class="user-info text-end">
        Xin chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
        <h5 class="mb-0">Danh sách phiếu chuyển kho</h5>
        <div class="d-flex gap-2 flex-column flex-md-row">
            <a href="<?php echo esc_url(home_url('/aerp-inventory-transfers/?action=add')); ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> Thêm phiếu chuyển kho
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if ($message) : ?>
            <div class="notice notice-success alert alert-success alert-dismissible fade show" role="alert">
                <?php echo esc_html($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php delete_transient('aerp_inventory_transfer_message'); ?>
        <?php endif; ?>

        <div id="aerp-inventory-transfer-table-wrapper">
            <?php $table->render(); ?>
        </div>

        <a href="<?php echo home_url('/aerp-warehouses'); ?>" class="btn btn-outline-secondary" style="width: fit-content;">
            <i class="fas fa-arrow-left me-1"></i> Quay lại
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Danh sách phiếu chuyển kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
