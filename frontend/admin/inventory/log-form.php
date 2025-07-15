<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !aerp_user_has_role($user_id, 'admin')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
$type = isset($_GET['type']) && $_GET['type'] === 'export' ? 'export' : 'import';
$id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
$log = $id ? AERP_Inventory_Log_Manager::get_log_by_id($id) : null;
ob_start();
?>
<style>
    .select2-container--default .select2-selection--single {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
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
    <h2>
        <?php echo $id ? 'Xác nhận phiếu ' . ($type === 'import' ? 'nhập' : 'xuất') . ' kho' : 'Tạo phiếu ' . ($type === 'import' ? 'nhập' : 'xuất') . ' kho'; ?>
    </h2>
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
            <?php if ($id): ?>
                <input type="hidden" name="log_id" value="<?php echo esc_attr($id); ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="warehouse_id" class="form-label">Kho</label>
                    <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                        <option value="">-- Chọn kho --</option>
                        <?php foreach (AERP_Warehouse_Manager::get_all() as $w): ?>
                            <option value="<?php echo esc_attr($w->id); ?>" <?php selected($log && $log->warehouse_id == $w->id); ?>>
                                <?php echo AERP_Warehouse_Manager::get_full_warehouse_name($w->id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="product_id" class="form-label">Sản phẩm</label>
                    <select class="form-select product-select" id="product_id" name="product_id" required style="width:100%">
                        <option value="">-- Chọn sản phẩm --</option>
                        <?php if ($log && $log->product_id): ?>
                            <?php $product = function_exists('aerp_get_product') ? aerp_get_product($log->product_id) : null; ?>
                            <?php if ($product): ?>
                                <option value="<?php echo esc_attr($product->id); ?>" selected>
                                    <?php echo esc_html($product->name); ?>
                                </option>
                            <?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="quantity" class="form-label">Số lượng</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" required
                           value="<?php echo esc_attr($log->quantity ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="note" class="form-label">Ghi chú</label>
                    <textarea name="note" id="note" class="form-control" rows="1"><?php echo esc_textarea($log->note ?? ''); ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2">
                <?php if (!$id): ?>
                    <button type="submit" name="aerp_save_inventory_log" class="btn btn-primary">
                        <?php echo $type === 'import' ? 'Tạo phiếu nhập kho' : 'Tạo phiếu xuất kho'; ?>
                    </button>
                <?php else: ?>
                    <button type="submit" name="aerp_confirm_inventory_log" class="btn btn-success" onclick="return confirm('Xác nhận phiếu này?')">
                        Xác nhận
                    </button>
                <?php endif; ?>
                <a href="<?php echo home_url('/aerp-inventory-logs'); ?>" class="btn btn-secondary">Quay lại</a>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = $id ? 'Xác nhận phiếu' : ($type === 'import' ? 'Tạo phiếu nhập kho' : 'Tạo phiếu xuất kho');
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
