<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$type = isset($_GET['type']) && $_GET['type'] === 'export' ? 'export' : 'import';
ob_start();
?>
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
</style>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2><?php echo $type === 'import' ? 'Tạo phiếu nhập kho' : 'Tạo phiếu xuất kho'; ?></h2>
    <div class="user-info text-end">
        Chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <form method="post">
            <?php wp_nonce_field('aerp_save_inventory_log_action', 'aerp_save_inventory_log_nonce'); ?>
            <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="warehouse_id" class="form-label">Kho</label>
                    <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                        <option value="">-- Chọn kho --</option>
                        <?php foreach (AERP_Warehouse_Manager::get_all() as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>">
                                <?php echo AERP_Warehouse_Manager::get_full_warehouse_name($w->id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="product_id" class="form-label">Sản phẩm</label>
                    <select class="form-select product-select" id="product_id" name="product_id" required style="width:100%">
                        <option value="">-- Chọn sản phẩm --</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="quantity" class="form-label">Số lượng</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                </div>
                <div class="col-12 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea name="note" id="note" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" name="aerp_save_inventory_log" class="btn btn-primary">
                    <?php echo $type === 'import' ? 'Nhập kho' : 'Xuất kho'; ?>
                </button>
                <a href="<?php echo home_url('/aerp-inventory-logs'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = $type === 'import' ? 'Tạo phiếu nhập kho' : 'Tạo phiếu xuất kho';
?>
<?php
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
